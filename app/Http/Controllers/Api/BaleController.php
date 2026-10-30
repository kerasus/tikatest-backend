<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Bale\BaleLinkTokenService;
use App\Services\Bale\BaleService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BaleController extends Controller
{
    public function link(Request $request, BaleLinkTokenService $tokens): JsonResponse
    {
        if ($request->user()->refresh()->hasBaleConnected()) {
            return $this->jsonResponseOk(['connected' => true]);
        }

        $link = $tokens->issueTokenForUser($request->user()->id);

        return $this->jsonResponseOk([
            'connected' => false,
            'deep_link' => $link['deep_link'],
            'expires_in' => $link['expires_in'],
        ]);
    }

    public function disconnect(Request $request, BaleLinkTokenService $tokens): JsonResponse
    {
        $tokens->revokeTokensForUser($request->user()->id);
        $request->user()->update(['bale_chat_id' => null, 'bale_username' => null]);

        return $this->jsonResponseOk(['connected' => false]);
    }

    public function webhook(Request $request, BaleLinkTokenService $tokens, BaleService $bale): JsonResponse
    {
//        \Log::info('Bale webhook: HIT', [
//            'ip' => $request->ip(),
//            'method' => $request->method(),
//            'has_secret' => $request->hasHeader('X-Bale-Webhook-Secret') || $request->filled('secret'),
//            'content_type' => $request->header('Content-Type'),
//            'raw_len' => strlen($request->getContent()),
//            'update_id' => $request->input('update_id'),
//        ]);

        $secret = (string) config('bale.webhook_secret');
        $provided = (string) ($request->header('X-Bale-Webhook-Secret') ?: $request->query('secret', ''));

        if ($secret === '' || ! hash_equals($secret, $provided)) {
//            \Log::warning('Bale webhook: secret mismatch', [
//                'ip' => $request->ip(),
//                'secret_configured' => $secret !== '',
//                'provided_len' => strlen($provided),
//            ]);
            return $this->jsonResponseError('دسترسی به وبهوک مجاز نیست.', 403);
        }

        $message = $request->input('message');
        $text = data_get($message, 'text');
        $chatId = data_get($message, 'chat.id');
        $chatType = data_get($message, 'chat.type');

//        \Log::debug('Bale webhook received: parsed', [
//            'update_id' => $request->input('update_id'),
//            'chat_id' => $chatId,
//            'chat_type' => data_get($message, 'chat.type'),
//            'text_len' => is_string($text) ? strlen($text) : null,
//            'text' => is_string($text) ? \Str::limit($text, 120) : null,
//            'starts_with_start' => is_string($text) && str_starts_with($text, '/start'),
//        ]);

        if (! is_string($chatId) && ! is_int($chatId)) {
            return $this->jsonResponseOk(['received' => true]);
        }

        $chatId = (string) $chatId;

        if ($chatType !== 'private' || strlen($chatId) > 64 || ! ctype_digit($chatId)) {
            return $this->jsonResponseOk(['received' => true]);
        }

        if (is_string($text) && preg_match('/\A\/start(?:@\w+)?\s+([a-f0-9]{64})\s*\z/D', $text, $matches)) {
//            \Log::info('Bale webhook: /start with token', [
//                'token_prefix' => substr($matches[1], 0, 8).'…',
//                'chat_id' => $chatId,
//            ]);

            $userId = $tokens->consumeToken($matches[1]);

            if ($userId === null) {
//                \Log::warning('Bale webhook: token invalid/expired', ['token_prefix' => substr($matches[1], 0, 8).'…']);
                $this->reply($bale, $chatId, 'لینک اتصال نامعتبر یا منقضی شده است. لطفاً از پنل لینک جدید دریافت کنید.');

                return $this->jsonResponseError('توکن اتصال نامعتبر یا استفاده‌شده است.', 422);
            }

//            \Log::info('Bale webhook: token consumed', ['user_id' => $userId, 'chat_id' => $chatId]);

            $user = User::find($userId);

            if ($user === null || $user->hasBaleConnected()) {
//                if ($user === null) {
//                    \Log::error('Bale webhook: user not found for valid token', ['user_id' => $userId]);
//                }
//
//                if ($user->hasBaleConnected()) {
//                    \Log::warning('Bale webhook: user already connected', ['user_id' => $userId]);
//                }

                $this->reply($bale, $chatId, 'اتصال امکان‌پذیر نیست. لطفاً وضعیت اتصال را در پنل بررسی کنید.');

                return $this->jsonResponseError('اتصال امکان‌پذیر نیست.', 409);
            }

            $username = data_get($message, 'from.username') ?? data_get($message, 'chat.username');
            $username = is_string($username) ? ltrim($username, '@') : null;

            try {
                $user->update([
                    'bale_chat_id' => $chatId,
                    'bale_username' => $username !== '' && strlen((string) $username) <= 64 ? $username : null,
                ]);
//                \Log::info('Bale webhook: user connected', [
//                    'user_id' => $userId,
//                    'chat_id' => $chatId,
//                    'username' => $username,
//                ]);
            } catch (UniqueConstraintViolationException) {
//                \Log::warning('Bale webhook: chat_id already taken', ['chat_id' => $chatId, 'user_id' => $userId]);
                $this->reply($bale, $chatId, 'این حساب بله قبلاً به کاربر دیگری متصل شده است.');

                return $this->jsonResponseError('این حساب بله قبلاً متصل شده است.', 409);
            }

            $this->reply($bale, $chatId, 'حساب بله شما با موفقیت به تیکاتست متصل شد.');

            return $this->jsonResponseOk(['connected' => true]);
        }

//        \Log::info('Bale webhook: /start without token', ['chat_id' => $chatId, 'text' => \Str::limit((string) $text, 80)]);
        $this->reply($bale, $chatId, 'برای اتصال حساب، از پنل تیکاتست روی «اتصال به بله» بزنید و لینک جدید را باز کنید.');

        return $this->jsonResponseOk(['received' => true]);
    }

    private function reply(BaleService $bale, string $chatId, string $text): void
    {
//        \Log::info('Bale reply: sending', ['chat_id' => $chatId, 'preview' => \Str::limit($text, 60)]);

        try {
            $bale->sendMessage($chatId, $text);
//            \Log::info('Bale reply: sent', ['chat_id' => $chatId]);
        } catch (Throwable $exception) {
//            \Log::error('Bale reply: FAILED', [
//                'chat_id' => $chatId,
//                'exception' => $exception::class,
//                'message' => $exception->getMessage(),
//            ]);
        }
    }
}
