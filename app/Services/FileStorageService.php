<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    protected string $publicStorageRoot;

    public function __construct()
    {
        $this->publicStorageRoot = config('filesystems.disks.public.root', storage_path('app/public'));
    }

    /**
     * ذخیره ساده فایل آپلودی
     */
    public function storeUploadedFile(
        UploadedFile $file,
        string $directory = 'uploads',
        ?string $prefix = null
    ): array {
        $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
        $fileName = $prefix . uniqid() . '.' . $file->getClientOriginalExtension();
        $relativePath = trim($directory, '/') . '/' . $fileName;

        $path = $file->storeAs($directory, $fileName, 'public');

        return [
            'file_name'     => $fileName,
            'relative_path' => $relativePath,
            'url'           => $this->getUrl($relativePath),
        ];
    }

    /**
     * تولید آدرس عمومی بر اساس کانفیگ هاست
     */
    public function getUrl(string $relativePath): string
    {
        $baseUrl = config('filesystems.disks.public.url', url('storage'));
        return rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * نرمال‌سازی مسیر و حذف ایمن فایل از تمام کاندیداهای مسیر در لوکال و سرور cPanel
     */
    public function deletePublicFile(?string $pathOrUrl): bool
    {
        if (blank($pathOrUrl)) {
            return false;
        }

        // ۱. استخراج path از URL کامل (در صورتی که با http شروع شده باشد)
        $cleanPath = $pathOrUrl;
        if (str_starts_with($cleanPath, 'http://') || str_starts_with($cleanPath, 'https://')) {
            $cleanPath = parse_url($cleanPath, PHP_URL_PATH) ?? $cleanPath;
        }

        // ۲. حذف هرگونه پیشوند api یا storage از ابتدای مسیر
        $cleanPath = preg_replace('#^/?(api/)?(storage/)?#', '', (string) $cleanPath);
        $cleanPath = ltrim($cleanPath, '/');

        if (empty($cleanPath)) {
            return false;
        }

        $deleted = false;

        // سناریو ۱: حذف از طریق درایور رسمی دیسک public لاراول
        if (Storage::disk('public')->exists($cleanPath)) {
            $deleted = Storage::disk('public')->delete($cleanPath);
        }

        // سناریو ۲: حذف مستقیم از مسیر فیزیکی FILESYSTEM_PUBLIC_ROOT
        if (! $deleted && $this->publicStorageRoot) {
            $directFilePath = rtrim($this->publicStorageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cleanPath;
            if (file_exists($directFilePath) && is_file($directFilePath)) {
                $deleted = @unlink($directFilePath);
            }
        }

        // سناریو ۳: پشتیبان برای مسیر فیزیکی دقیق هاست اشتراکی cPanel و لوکال
        if (! $deleted) {
            $candidates = [
                '/home/h429372/public_html/api/storage/' . $cleanPath,
                public_path('storage/' . $cleanPath),
                public_path($cleanPath),
                storage_path('app/public/' . $cleanPath),
            ];

            foreach ($candidates as $candidate) {
                if (file_exists($candidate) && is_file($candidate)) {
                    $deleted = @unlink($candidate);
                    if ($deleted) {
                        break;
                    }
                }
            }
        }

        Log::info('FileStorageService: delete operation', [
            'raw_input' => $pathOrUrl,
            'normalized' => $cleanPath,
            'success'   => $deleted,
        ]);

        return $deleted;
    }
}
