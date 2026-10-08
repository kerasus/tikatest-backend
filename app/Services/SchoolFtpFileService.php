<?php

namespace App\Services;

use App\Models\SchoolFeature;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SchoolFtpFileService
{
    /**
     * دریافت فایل‌های دسته‌بندی‌شده یک کلاس از پنل FTP مدرسه
     */
    public function getClassFiles(int $schoolId, int $classId): array
    {
        // ۱. پیدا کردن فیچر فعال FTP برای این مدرسه
        $feature = SchoolFeature::query()
            ->where('school_id', $schoolId)
            ->where('feature_key', SchoolFeature::FTP_PANEL)
            ->where('is_enabled', true)
            ->first();

        $config = $feature?->settings;

        if (!$feature || empty($config['base_url']) || empty($config['outbound_api_key'])) {
            return [
                'success' => false,
                'message' => 'سرویس مدیریت فایل برای این مدرسه فعال یا پیکربندی نشده است.',
                'files'   => [],
            ];
        }

        // ۲. کش یک‌ساعته به ازای هر مدرسه و کلاس
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
     * برای وقتی که ادمین فایل جدیدی آپلود کرده و کش باید تازه شود
     */
    public function forgetClassFilesCache(int $schoolId, int $classId): void
    {
        Cache::forget("ftp_files_school_{$schoolId}_class_{$classId}");
    }
}
