<?php
namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\Cache;

class DiskSpaceCheck extends Check
{
    public ?string $name = 'UsedDiskSpace';
    public ?string $label = 'Used Disk Space';

    public function run(): Result
    {
        try {
            // سهمیه کل هاست به گیگابایت (پیش‌فرض ۵ گیگابایت)
            $quotaGb = (float) config('filesystems.host_quota_gb', env('HOST_DISK_QUOTA_GB', 5));
            $quotaBytes = $quotaGb * 1024 * 1024 * 1024;

            // تشخیص مسیر ریشه هاست کاربر (معمولاً پوشه والد یا خود پروژه)
            $projectPath = base_path();
            $homePath = dirname($projectPath); // یک لول بالاتر (معمولا روت اکانت هاست یا پابلیک)

            // محاسبه حجم واقعی با سیستم کش ۵ دقیقه‌ای جهت جلوگیری از لود اضافه روی هارد هاست
            $usedBytes = Cache::remember('server_disk_used_bytes', 300, function () use ($homePath, $projectPath) {
                return $this->calculateDiskUsage($homePath, $projectPath);
            });

            // تبدیل واحدها با دقت اعشار
            $usedGb = round($usedBytes / (1024 * 1024 * 1024), 2);
            $freeGb = max(0, round($quotaGb - $usedGb, 2));
            $usedPercentage = ($quotaBytes > 0) ? round(($usedBytes / $quotaBytes) * 100, 1) : 0;

            // گزارش دقیق در سامری
            $summaryText = $usedPercentage < 1 && $usedGb > 0
                ? "{$usedPercentage}% (~{$usedGb} GB)"
                : "{$usedPercentage}% مصرف شده";

            $result = Result::make()
                ->shortSummary($summaryText)
                ->meta([
                    'disk_space_used_percentage' => $usedPercentage,
                    'used_gb' => $usedGb,
                    'free_gb' => $freeGb,
                    'total_gb' => $quotaGb,
                ]);

            if ($usedPercentage >= 90) {
                return $result->failed("هشدار دیسک بحرانی: {$usedPercentage}% ({$usedGb} از {$quotaGb} گیگابایت)");
            }

            if ($usedPercentage >= 80) {
                return $result->warning("هشدار مصرف هاست: {$usedPercentage}% ({$usedGb} از {$quotaGb} گیگابایت)");
            }

            return $result->ok();

        } catch (\Throwable $e) {
            return Result::make()
                ->warning('خطا در بررسی سهمیه هاست: ' . $e->getMessage());
        }
    }

    /**
     * روش چندلایه برای به دست آوردن حجم دقیق مصرفی
     */
    protected function calculateDiskUsage(string $homePath, string $projectPath): float
    {
        // لایه ۱: امتحان دستورات سبک لینوکسی در صورت باز بودن دسترسی شل
        $duBytes = $this->tryDuCommand($homePath) ?: $this->tryDuCommand($projectPath);
        if ($duBytes > 0) {
            return $duBytes;
        }

        // لایه ۲: اسکن امن و سریع پوشه‌های اصلی پروژه (Vendor, Storage, App, etc.)
        return $this->scanDirectorySize($projectPath);
    }

    /**
     * اجرای دستور du در صورت امکان
     */
    protected function tryDuCommand(string $path): ?float
    {
        if (!function_exists('exec') && !function_exists('shell_exec')) {
            return null;
        }

        // چک کردن disable_functions
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled) && in_array('shell_exec', $disabled)) {
            return null;
        }

        try {
            $escaped = escapeshellarg($path);
            $output = @shell_exec("du -sb $escaped 2>/dev/null");
            if ($output && preg_match('/^(\d+)/', trim($output), $matches)) {
                return (float) $matches[1];
            }

            // فال‌بک برای فلگ k
            $outputK = @shell_exec("du -sk $escaped 2>/dev/null");
            if ($outputK && preg_match('/^(\d+)/', trim($outputK), $matches)) {
                return (float) $matches[1] * 1024;
            }
        } catch (\Throwable $e) {
            // نادیده گرفتن
        }

        return null;
    }

    /**
     * اسکن دستی فایل‌ها با محافظت در برابر پرمیشن‌ها و سیم‌لینک‌های لوپ
     */
    protected function scanDirectorySize(string $dir): float
    {
        $size = 0;
        if (!is_dir($dir) || !is_readable($dir)) {
            return $size;
        }

        try {
            $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, $flags),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                try {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                } catch (\Throwable $inner) {
                    continue;
                }
            }
        } catch (\Throwable $e) {
            // در صورتی که دایرکتوری غیرقابل خواندن باشد
        }

        return (float) $size;
    }
}
