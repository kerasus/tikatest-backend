<?php

namespace App\Services;

use App\Models\SchoolFeature;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SchoolFtpFileService
{
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

        // کش یک‌ساعته به ازای هر مدرسه و کلاس
        $cacheKey = "ftp_files_school_{$schoolId}_class_{$classId}";

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
     *
     * @param int $schoolId
     * @param array<int> $classIds
     * @return array
     */
    public function getClassesFilesTree(int $schoolId, array $classIds): array
    {
        // آرایه خالی یا بدون آیدی معتبر نیاز به درخواست به سرور نداره
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

        // سورت می‌کنیم تا کش‌کی برای ترتیب‌های مختلف یکسان باشه (مثلا [1, 2] و [2, 1])
        sort($validClassIds);
        $classesHash = md5(implode(',', $validClassIds));
        $cacheKey = "ftp_files_tree_school_{$schoolId}_{$classesHash}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($config, $schoolId, $validClassIds) {
            try {
                // چون آرایه ممکنه طولانی باشه، متد POST با Body مناسب‌تره
                // (یا اگر اندپوینت مقصدت GET با کوئری پارامتر قبول می‌کنه، می‌تونی get بزنی)
                $response = Http::withHeaders([
                    'X-Service-Api-Key' => $config['outbound_api_key'],
                    'Accept'            => 'application/json',
                ])
                    ->timeout(15) // تایم‌اوت رو برای حجم درختی یکم دست‌ودلبازتر گذاشتیم
                    ->post(rtrim($config['base_url'], '/') . '/classes/files-tree', [
                        'class_ids' => $validClassIds,
                    ]);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'tree'    => $response->json('data') ?? [],
                    ];
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
        });
    }

    /**
     * برای وقتی که ادمین فایل جدیدی آپلود کرده و کش باید تازه شود
     */
    public function forgetClassFilesCache(int $schoolId, int $classId): void
    {
        Cache::forget("ftp_files_school_{$schoolId}_class_{$classId}");
    }

    /**
     * پاک‌سازی کش درخت فایل‌های چند کلاس
     */
    public function forgetClassesFilesTreeCache(int $schoolId, array $classIds): void
    {
        $validClassIds = array_values(array_unique(array_filter($classIds, fn($id) => is_numeric($id) && $id > 0)));
        sort($validClassIds);
        $classesHash = md5(implode(',', $validClassIds));
        Cache::forget("ftp_files_tree_school_{$schoolId}_{$classesHash}");
    }
}
