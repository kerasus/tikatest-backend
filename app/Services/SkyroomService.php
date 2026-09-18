<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SkyroomService
{
    // دسترسی‌های اتاق
    public const ACCESS_NORMAL    = 1; // کاربر عادی
    public const ACCESS_PRESENTER = 2; // ارائه‌دهنده
    public const ACCESS_OPERATOR  = 3; // اپراتور
    public const ACCESS_ADMIN     = 4; // مدیر

    // وضعیت‌ها
    public const STATUS_DISABLED  = 0; // غیرفعال
    public const STATUS_ENABLED   = 1; // فعال

    protected string $apiKey;
    protected string $baseUrl = 'https://www.skyroom.online/skyroom/api/';

    public function __construct()
    {
        $this->apiKey = config('services.skyroom.key');
    }

    /**
     * متد پایه برای ارسال درخواست به وب‌سرویس اسکای‌روم
     *
     * @param string $action
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function call(string $action, array $params = [])
    {
        $url = $this->baseUrl . $this->apiKey;

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->asJson()
                ->acceptJson()
                ->post($url, [
                    'action' => $action,
                    'params' => (object) $params,
                ]);

            if ($response->failed()) {
                throw new Exception("خطای سرور یا شبکه در ارتباط با اسکای‌روم: HTTP " . $response->status());
            }

            $data = $response->json();

            if (isset($data['ok']) && $data['ok'] === false) {
                $code = $data['error_code'] ?? 0;
                $msg  = $data['error_message'] ?? 'خطای نامشخص در اسکای‌روم';
                throw new Exception("خطای وب‌سرویس اسکای‌روم [{$code}]: {$msg}", (int) $code);
            }

            return $data['result'] ?? null;
        } catch (Exception $e) {
            Log::error('Skyroom API Call Failed', [
                'action' => $action,
                'params' => $params,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * ۱. دریافت لیست تمام کلاس‌ها (اتاق‌ها)
     *
     * @return array
     * @throws Exception
     */
    public function getRooms(): array
    {
        return $this->call('getRooms') ?? [];
    }

    /**
     * ۲. دریافت آدرس ورود مستقیم به اتاق (بدون نیاز به لاگین و ساخت کاربر)
     *
     * @param int|string $roomId شناسه اتاق
     * @param string|int $userId شناسه یکتای کاربر در سامانه شما (برای جلوگیری از ورود همزمان چند نفر با یک لینک)
     * @param string $nickname نام نمایشی کاربر داخل کلاس
     * @param int $access سطح دسترسی (پیش‌فرض: کاربر عادی)
     * @param int $ttl مدت زمان اعتبار لینک به ثانیه (پیش‌فرض: ۳۶۰۰ ثانیه معادل ۱ ساعت)
     * @param int $concurrent سقف ورود همزمان با این لینک (پیش‌فرض: ۱ نفر)
     * @param string $language زبان محیط کاربری ('fa' یا 'en')
     * @return string
     * @throws Exception
     */
    public function createLoginUrl(
        $roomId,
        $userId,
        string $nickname,
        int $access = self::ACCESS_NORMAL,
        int $ttl = 3600,
        int $concurrent = 1,
        string $language = 'fa'
    ): string {
        return $this->call('createLoginUrl', [
            'room_id'    => $roomId,
            'user_id'    => (string) $userId,
            'nickname'   => $nickname,
            'access'     => $access,
            'ttl'        => $ttl,
            'concurrent' => $concurrent,
            'language'   => $language,
        ]);
    }

    /**
     * ۳. دریافت مشخصات و وضعیت یک اتاق
     * (با استفاده از شناسه عددی یا نام لاتین اتاق)
     *
     * @param int|string $roomIdentifier شناسه عددی اتاق یا نام لاتین (name)
     * @return array
     * @throws Exception
     */
    public function getRoom($roomIdentifier): array
    {
        $params = is_numeric($roomIdentifier)
            ? ['room_id' => (int) $roomIdentifier]
            : ['name' => (string) $roomIdentifier];

        return $this->call('getRoom', $params) ?? [];
    }

    /**
     * بررسی فعال بودن یک اتاق (وضعیت اتاق)
     *
     * @param int|string $roomIdentifier
     * @return bool
     * @throws Exception
     */
    public function isRoomActive($roomIdentifier): bool
    {
        $room = $this->getRoom($roomIdentifier);
        return isset($room['status']) && (int) $room['status'] === self::STATUS_ENABLED;
    }

    /**
     * ۴. دریافت لیست و وضعیت سرویس‌های فعال/غیرفعال اسکای‌روم
     *
     * @return array
     * @throws Exception
     */
    public function getServices(): array
    {
        return $this->call('getServices') ?? [];
    }
}
