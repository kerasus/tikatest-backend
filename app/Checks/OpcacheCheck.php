<?php
namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class OpcacheCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        if (!function_exists('opcache_get_status')) {
            return $result->failed('تابع OPcache فعال نیست یا پشتیبانی نمی‌شود.');
        }

        $status = opcache_get_status(false);

        if ($status === false || empty($status['opcache_enabled'])) {
            return $result->warning('OPcache غیرفعال (Disabled) است.');
        }

        $memory = $status['memory_usage'] ?? [];
        $stats = $status['opcache_statistics'] ?? [];

        $usedMb = isset($memory['used_memory']) ? round($memory['used_memory'] / 1024 / 1024, 2) : 0;
        $hitRate = isset($stats['opcache_hit_rate']) ? round($stats['opcache_hit_rate'], 1) : 0;
        $cachedScripts = $stats['num_cached_scripts'] ?? 0;

        // اگر Hit Rate زیر ۷۰٪ بود هشدار بده
        if ($hitRate < 70) {
            $result->warning("نرخ کشینگ پایینه: {$hitRate}%");
        } else {
            $result->ok();
        }

        return $result
            ->shortSummary("Hit Rate: {$hitRate}% | حافظه: {$usedMb}MB")
            ->meta([
                'enabled' => true,
                'hit_rate' => $hitRate,
                'memory_used_mb' => $usedMb,
                'cached_scripts' => $cachedScripts,
            ]);
    }
}
