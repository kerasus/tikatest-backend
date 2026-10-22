<?php

namespace App\Services\Bale;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BaleService
{
    public function buildDeepLink(string $token): string
    {
        $username = ltrim((string) config('bale.bot_username'), '@');

        if ($username === '') {
            throw new RuntimeException('نام کاربری بازوی بله تنظیم نشده است.');
        }

        return 'https://ble.ir/'.rawurlencode($username).'?start='.rawurlencode($token);
    }

    public function sendMessage(string $chatId, string $text, array $options = []): array
    {
        return $this->request('sendMessage', array_merge($options, [
            'chat_id' => $chatId,
            'text' => $text,
        ]));
    }

    public function setWebhook(string $url): array
    {
        return $this->request('setWebhook', ['url' => $url]);
    }

    private function request(string $method, array $parameters): array
    {
        $token = (string) config('bale.token');

        \Log::info('Bale: request checkpoint', [
            'method' => $method,
            'token_type' => gettype($token),
            'token_is_null' => $token === null,
            'token_is_empty' => $token === '',
            'token_length' => is_string($token) ? strlen($token) : null,
        ]);

        if (!is_string($token) || trim($token) === '') {
            \Log::error('Bale: token missing', ['method' => $method]);
            throw new RuntimeException('توکن بازوی بله تنظیم نشده است.');
        }

        \Log::info('Bale: before HTTP request', ['method' => $method]);



        try {
            $response = Http::timeout(10)->post(
                'https://tapi.bale.ai/bot'.$token.'/'.$method,
                $parameters
            );

            \Log::info('Bale: HTTP response received', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $response->throw();
        } catch (\Throwable $e) {
            \Log::error('Bale: HTTP request failed', [
                'method' => $method,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['ok'] ?? false) !== true) {
            throw new RuntimeException('درخواست به بازوی بله ناموفق بود.');
        }

        return $data;
    }

    private function maskParams(array $parameters): array
    {
        $copy = $parameters;
        if (isset($copy['text'])) {
            $copy['text'] = \Str::limit($copy['text'], 80);
        }
        return $copy;
    }
}
