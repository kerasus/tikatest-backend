<?php

namespace App\Services;

use App\Models\SchoolFeature;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SchoolFtpFileService
{
    /**
     * پیشوند عمومی کلیدهای کش این سرویس
     */
    protected const CACHE_PREFIX = 'ftp_files_service';

    /**
     * استخراج و اعتبارسنجی تنظیمات سرویس FTP مدرسه
     */
    protected function getFtpConfig(int $schoolId): ?array
    {
        $feature = SchoolFeature::query()
            ->where('school_id', $schoolId)
            ->where('feature_key', SchoolFeature::FTP_PANEL)
            ->where('is_enabled', true)
            ->first();

        $config = $feature?->settings;

        if (!$feature || empty($config['base_url']) || empty($config['outbound_api_key'])) {
            return null;
        }

        return $config;
    }

    /**
     * دریافت کلید نسخه کش برای یک مدرسه (جهت باطل‌سازی آنی تمام کش‌ها)
     */
    protected function getSchoolCacheVersion(int $schoolId): int
    {
        return (int) Cache::get(self::CACHE_PREFIX . "_version_school_{$schoolId}", 1);
    }

    /**
     * دریافت فایل‌های دسته‌بندی‌شده یک کلاس از پنل FTP مدرسه
     */
    public function getClassFiles(int $schoolId, int $classId): array
    {
        $config = $this->getFtpConfig($schoolId);

        if (!$config) {
            return [
                'success' => false,
                'message' => 'سرویس مدیریت فایل برای این مدرسه فعال یا پیکربندی نشده است.',
                'files'   => [],
            ];
        }

        $v = $this->getSchoolCacheVersion($schoolId);
        $cacheKey = "ftp_files_v{$v}_school_{$schoolId}_class_{$classId}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($config, $classId, $schoolId) {
            try {
                $response = Http::withHeaders([
                    'X-Service-Api-Key' => $config['outbound_api_key'],
                    'Accept'            => 'application/json',
                ])
                    ->timeout(10)
                    ->get(rtrim($config['base_url'], '/') . "/classes/{$classId}/files");

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'files'   => $response->json('data') ?? [],
                    ];
                }

                Log::warning('پاسخ غیرموفق از پنل FTP مدرسه', [
                    'school_id' => $schoolId,
                    'class_id'  => $classId,
                    'status'    => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::error('خطا در ارتباط با پنل FTP مدرسه: ' . $e->getMessage(), [
                    'school_id' => $schoolId,
                    'class_id'  => $classId,
                ]);
            }

            return [
                'success' => false,
                'message' => 'امکان دریافت فایل‌ها در حال حاضر وجود ندارد.',
                'files'   => [],
            ];
        });
    }

    /**
     * دریافت درخت فایل‌های مجموعه‌ای از کلاس‌ها (Batch / Tree)
     */
    public function getClassesFilesTree(int $schoolId, array $classIds): array
    {
        $validClassIds = array_values(array_unique(array_filter($classIds, fn($id) => is_numeric($id) && $id > 0)));

        if (empty($validClassIds)) {
            return [
                'success' => true,
                'tree'    => [],
            ];
        }

        $config = $this->getFtpConfig($schoolId);

        if (!$config) {
            return [
                'success' => false,
                'message' => 'سرویس مدیریت فایل برای این مدرسه فعال یا پیکربندی نشده است.',
                'tree'    => [],
            ];
        }

        sort($validClassIds);
        $classesHash = md5(implode(',', $validClassIds));
        $v = $this->getSchoolCacheVersion($schoolId);
        $cacheKey = "ftp_files_tree_v{$v}_school_{$schoolId}_{$classesHash}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $address = rtrim($config['base_url'], '/') . '/sanjad/classes/files-tree';

            $response = Http::withHeaders([
                'X-Service-Api-Key' => $config['outbound_api_key'],
                'Accept'            => 'application/json',
            ])
                ->timeout(15)
                ->post($address, [
                    'class_ids' => $validClassIds,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $tree = $body['data'] ?? $body ?? [];

                $result = [
                    'success' => true,
                    'tree'    => $tree,
                ];

                Cache::put($cacheKey, $result, now()->addHours(2));

                return $result;
            }

            Log::warning('پاسخ ناموفق در دریافت درخت فایل‌های کلاس‌ها از پنل FTP', [
                'school_id' => $schoolId,
                'class_ids' => $validClassIds,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('خطا در ارتباط با سرور FTP جهت دریافت درخت فایل‌ها: ' . $e->getMessage(), [
                'school_id' => $schoolId,
                'class_ids' => $validClassIds,
            ]);
        }

        return [
            'success' => false,
            'message' => 'امکان دریافت ساختار فایل‌ها در حال حاضر وجود ندارد.',
            'tree'    => [],
        ];
    }

    /**
     * پاک‌سازی کش یک کلاس مشخص
     */
    public function forgetClassFilesCache(int $schoolId, int $classId): void
    {
        $v = $this->getSchoolCacheVersion($schoolId);
        Cache::forget("ftp_files_v{$v}_school_{$schoolId}_class_{$classId}");
    }

    /**
     * پاک‌سازی کش درخت فایل‌های چند کلاس
     */
    public function forgetClassesFilesTreeCache(int $schoolId, array $classIds): bool
    {
        $validClassIds = array_values(array_unique(array_filter($classIds, fn($id) => is_numeric($id) && $id > 0)));
        sort($validClassIds);
        $classesHash = md5(implode(',', $validClassIds));
        $v = $this->getSchoolCacheVersion($schoolId);
        $cacheKey = "ftp_files_tree_v{$v}_school_{$schoolId}_{$classesHash}";

        return Cache::forget($cacheKey);
    }

    /**
     * 🔥 پاک‌سازی کامل تمام کش‌های این سرویس برای یک مدرسه (یا با ورودی null برای کل مدارس)
     * با افزایش شماره ورژن، تمام کلیدهای کش قبلی بدون سربار به عنوان منقضی در نظر گرفته می‌شوند.
     */
    public function flushAllServiceCache(?int $schoolId = null): bool
    {
        if ($schoolId !== null) {
            $versionKey = self::CACHE_PREFIX . "_version_school_{$schoolId}";
            $currentVersion = (int) Cache::get($versionKey, 1);
            Cache::forever($versionKey, $currentVersion + 1);

            return true;
        }

        // اگر بخواهیم با متد Redis اگر ردیس فعال باشد یا به ازای لیست مدارس فعال پاک کنیم:
        try {
            // اگر از Redis استفاده می‌کنید:
            if (config('cache.default') === 'redis') {
                $redis = Cache::getStore()->getRedis();
                $prefix = config('database.redis.options.prefix', '');
                $keys = $redis->keys($prefix . 'ftp_files_*');
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        // حذف پیشوند لاراول از کلید قبل از del در ردیس
                        $cleanedKey = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $key);
                        Cache::forget($cleanedKey);
                    }
                }
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('خطا در پاکسازی کلیدهای ردیس کش FTP: ' . $e->getMessage());
        }

        // برای درایورهای File یا دیتابیس در صورت نیاز، کش تمام مدارس را صفر می‌کنیم
        $schoolIds = SchoolFeature::where('feature_key', SchoolFeature::FTP_PANEL)->pluck('school_id');
        foreach ($schoolIds as $sId) {
            $versionKey = self::CACHE_PREFIX . "_version_school_{$sId}";
            $currentVersion = (int) Cache::get($versionKey, 1);
            Cache::forever($versionKey, $currentVersion + 1);
        }

        return true;
    }
}
