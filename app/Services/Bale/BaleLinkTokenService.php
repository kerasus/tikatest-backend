<?php

namespace App\Services\Bale;

use RuntimeException;
use Illuminate\Support\Facades\Cache;

class BaleLinkTokenService
{
    public function __construct(private readonly BaleService $baleService) {}

    public function issueTokenForUser(int $userId): array
    {
        \Log::debug('Bale token: issuing', ['user_id' => $userId]);

        $ttl = (int) config('bale.link_token_ttl_seconds', 300);

        if ($ttl < 1) {
            throw new RuntimeException('مدت اعتبار توکن اتصال بله نامعتبر است.');
        }

        $token = bin2hex(random_bytes(32));
        $deepLink = $this->baleService->buildDeepLink($token);

        // هر کاربر در هر لحظه فقط یک توکن فعال داشته باشد.
        $this->revokeTokensForUser($userId);

        Cache::put($this->key($token), $userId, $ttl);
        Cache::put($this->userKey($userId), $token, $ttl);

        \Log::info('Bale token: issued', [
            'user_id' => $userId,
            'token_prefix' => substr($token, 0, 8).'…',
            'ttl' => $ttl,
            'cache_store' => config('cache.default'),
        ]);

        return [
            'token' => $token,
            'deep_link' => $deepLink,
            'expires_in' => $ttl,
        ];
    }

    public function consumeToken(string $token): ?int
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return null;
        }

        $tokenKey = $this->key($token);
        $userId = Cache::get($tokenKey);

        \Log::debug('Bale token: consume attempt', [
            'token_prefix' => substr($token, 0, 8).'…',
            'found' => $userId !== null,
            'cache_store' => config('cache.default'),
        ]);

        if ($userId === null) {
            return null;
        }

        $userId = (int) $userId;
        $userKey = $this->userKey($userId);

        // فقط اگر این توکن هنوز توکن فعالِ ثبت‌شده برای کاربر باشد، آن نگاشت را پاک کن.
        if (hash_equals((string) Cache::get($userKey, ''), $token)) {
            Cache::forget($userKey);
        }

        // توکن یک‌بارمصرف است.
        Cache::forget($tokenKey);

        return $userId;
    }

    public function revokeTokensForUser(int $userId): void
    {
        $userKey = $this->userKey($userId);
        $token = Cache::get($userKey);

        if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            Cache::forget($this->key($token));
        }

        Cache::forget($userKey);
    }

    private function key(string $token): string
    {
        return $this->prefix().$token;
    }

    private function userKey(int $userId): string
    {
        return $this->prefix().'user:'.$userId;
    }

    private function prefix(): string
    {
        return (string) config('bale.redis_prefix', 'bale:link_token:');
    }
}
