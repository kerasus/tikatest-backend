<?php

namespace App\Services\Bale;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BaleService
{
    public function buildDeepLink(string $token): string
    {
        $username = ltrim(
            (string) config('bale.bot_username'),
            '@'
        );

        if ($username === '') {
            throw new RuntimeException(
                'نام کاربری بازوی بله تنظیم نشده است.'
            );
        }

        return sprintf(
            'https://ble.ir/%s?start=%s',
            rawurlencode($username),
            rawurlencode($token),
        );
    }

    public function sendMessage(
        string $chatId,
        string $text,
        array $options = [],
    ): array {
        return $this->request(
            'sendMessage',
            array_merge($options, [
                'chat_id' => $chatId,
                'text' => $text,
            ]),
        );
    }

    public function setWebhook(string $url): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
        ]);
    }

    private function request(
        string $method,
        array $parameters,
    ): array {
        $token = (string) config('bale.token');

        if (trim($token) === '') {
            Log::error('Bale token is missing', [
                'method' => $method,
            ]);

            throw new RuntimeException(
                'توکن بازوی بله تنظیم نشده است.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'bale.api_url',
                'https://tapi.bale.ai'
            ),
            '/'
        );

        $timeout = (int) config('bale.timeout', 10);
        $retryTimes = (int) config('bale.retry_times', 2);
        $retrySleep = (int) config('bale.retry_sleep_ms', 300);

        Log::debug('Bale request started', [
            'method' => $method,
            'parameters' => $this->maskParams($parameters),
        ]);

        try {
            /** @var Response $response */
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry(
                    $retryTimes,
                    $retrySleep,
                    throw: false,
                )
                ->post(
                    "{$baseUrl}/bot{$token}/{$method}",
                    $parameters,
                );

            Log::debug('Bale response received', [
                'method' => $method,
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            $response->throw();
        } catch (Throwable $exception) {
            Log::error('Bale HTTP request failed', [
                'method' => $method,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'ارتباط با سرویس بله ناموفق بود.',
                previous: $exception,
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::error('Invalid Bale response format', [
                'method' => $method,
                'status' => $response->status(),
            ]);

            throw new RuntimeException(
                'پاسخ دریافتی از سرویس بله نامعتبر است.'
            );
        }

        if (($data['ok'] ?? false) !== true) {
            Log::warning('Bale API request rejected', [
                'method' => $method,
                'description' => $data['description'] ?? null,
                'error_code' => $data['error_code'] ?? null,
            ]);

            throw new RuntimeException(
                $data['description']
                ?? 'درخواست به بازوی بله ناموفق بود.'
            );
        }

        return $data;
    }

    private function maskParams(array $parameters): array
    {
        $masked = $parameters;

        if (isset($masked['text']) && is_string($masked['text'])) {
            $masked['text'] = Str::limit(
                $masked['text'],
                80
            );
        }

        return $masked;
    }
}
