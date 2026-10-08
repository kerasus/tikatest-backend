<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use InvalidArgumentException;

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

    protected ?string $apiKey = null;
    protected string $baseUrl = 'https://www.skyroom.online/skyroom/api/';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.skyroom.key');
    }

    /**
     * تنظیم یا تغییر API Key به صورت زنجیره‌ای (Fluent)
     */
    public function usingApiKey(string $apiKey): self
    {
        $this->apiKey = trim($apiKey);

        return $this;
    }

    /**
     * دریافت کلید فعال فعلی
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * متد پایه برای ارسال درخواست به وب‌سرویس اسکای‌روم
     *
     * @param string $action
     * @param array $params
     * @param string|null $apiKey کلید اختصاصی برای این درخواست (در صورت عدم ارسال از کلید ست‌شده استفاده می‌شود)
     * @return mixed
     * @throws Exception
     */
    public function call(string $action, array $params = [], ?string $apiKey = null)
    {
        $effectiveApiKey = $apiKey ? trim($apiKey) : $this->apiKey;

        if (empty($effectiveApiKey)) {
            throw new InvalidArgumentException("کلید API اسکای‌روم مشخص نشده است. لطفاً کلید معتبر اکانت مدرسه را ارسال کنید.");
        }

        $url = $this->baseUrl . $effectiveApiKey;

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
                'action'  => $action,
                'params'  => $params,
                'api_key' => substr($effectiveApiKey, 0, 10) . '***', // لاگ امن بدون لو رفتن کلید کامل
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * ۱. دریافت لیست تمام کلاس‌ها (اتاق‌ها)
     */
    public function getRooms(?string $apiKey = null): array
    {
        return $this->call('getRooms', [], $apiKey) ?? [];
    }

    /**
     * ۲. دریافت آدرس ورود مستقیم به اتاق (بدون نیاز به لاگین و ساخت کاربر)
     *
     * @param int|string $roomId شناسه اتاق
     * @param string|int $userId شناسه یکتای کاربر در سامانه
     * @param string $nickname نام نمایشی کاربر داخل کلاس
     * @param string|null $apiKey کلید API اختصاصی اکانت مربوطه
     * @param int $access سطح دسترسی (پیش‌فرض: عادی)
     * @param int $ttl مدت زمان اعتبار لینک به ثانیه
     * @param int $concurrent سقف ورود همزمان با این لینک
     * @param string $language زبان محیط کاربری
     * @return string
     * @throws Exception
     */
    public function createLoginUrl(
        $roomId,
        $userId,
        string $nickname,
        ?string $apiKey = null,
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
        ], $apiKey);
    }

    /**
     * تولید لینک مستقیم برای دانش‌آموز با دسترسی عادی
     */
    public function createStudentLoginUrl(
        $roomId,
        $studentId,
        string $nickname,
        ?string $apiKey = null,
        int $ttl = 3600,
        int $concurrent = 1,
        string $language = 'fa'
    ): string {
        return $this->createLoginUrl(
            $roomId,
            $studentId,
            $nickname,
            $apiKey,
            self::ACCESS_NORMAL,
            $ttl,
            $concurrent,
            $language
        );
    }

    /**
     * ۳. دریافت مشخصات و وضعیت یک اتاق
     */
    public function getRoom($roomIdentifier, ?string $apiKey = null): array
    {
        $params = is_numeric($roomIdentifier)
            ? ['room_id' => (int) $roomIdentifier]
            : ['name' => (string) $roomIdentifier];

        return $this->call('getRoom', $params, $apiKey) ?? [];
    }

    /**
     * بررسی فعال بودن یک اتاق
     */
    public function isRoomActive($roomIdentifier, ?string $apiKey = null): bool
    {
        $room = $this->getRoom($roomIdentifier, $apiKey);
        return isset($room['status']) && (int) $room['status'] === self::STATUS_ENABLED;
    }

    /**
     * ۴. دریافت لیست و وضعیت سرویس‌های فعال/غیرفعال اسکای‌روم
     */
    public function getServices(?string $apiKey = null): array
    {
        return $this->call('getServices', [], $apiKey) ?? [];
    }
//
//    /**
//     * حذف دسترسی و اخراج کاربران از یک اتاق
//     *
//     * @param int|string $roomId شناسه اتاق
//     * @param array|int|string $users آرایه‌ای از شناسه‌های کاربری یا یک شناسه تنها
//     * @param string|null $apiKey کلید اختصاصی اکانت مربوطه
//     * @return int تعداد کاربرانی که با موفقیت حذف شدند
//     * @throws Exception
//     */
//    public function removeRoomUsers($roomId, $users, ?string $apiKey = null): int
//    {
//        $userIds = is_array($users) ? array_values($users) : [$users];
//
//        // تبدیل مقادیر به عددی یا رشته‌ای تمیز
//        $normalizedUsers = array_map(function ($userId) {
//            return is_numeric($userId) ? (int) $userId : $userId;
//        }, $userIds);
//
//        $result = $this->call('removeRoomUsers', [
//            'room_id' => (int) $roomId,
//            'users'   => $normalizedUsers,
//        ], $apiKey);
//
//        dd($result);
//        return (int) ($result ?? 0);
//    }
}
