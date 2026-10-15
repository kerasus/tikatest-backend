<?php
namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class OpcacheCheck extends Check
{
    public ?string $name = 'Opcache';
    public ?string $label = 'PHP OPcache';

    public function run(): Result
    {
        if (!function_exists('opcache_get_status')) {
            return Result::make()
                ->warning('اکستنشن OPcache نصب یا فعال نیست.');
        }

        $status = @opcache_get_status(false);

        if ($status === false || !isset($status['opcache_enabled']) || !$status['opcache_enabled']) {
            return Result::make()
                ->failed('OPcache در سطح PHP غیرفعال (Disabled) است.');
        }

        $opcacheStats = $status['opcache_statistics'] ?? [];
        $memoryUsage = $status['memory_usage'] ?? [];

        $hitRate = round($opcacheStats['opcache_hit_rate'] ?? 0, 1);
        $hits = $opcacheStats['hits'] ?? 0;
        $misses = $opcacheStats['misses'] ?? 0;
        $totalRequests = $hits + $misses;

        $cachedScripts = $opcacheStats['num_cached_scripts'] ?? 0;
        $usedMemoryMb = round(($memoryUsage['used_memory'] ?? 0) / 1024 / 1024, 2);
        $wastedPercentage = round($memoryUsage['current_wasted_percentage'] ?? 0, 1);

        $result = Result::make()
            ->shortSummary("Hit Rate: {$hitRate}% | حافظه: {$usedMemoryMb}MB")
            ->meta([
                'enabled' => true,
                'hit_rate' => $hitRate,
                'cached_scripts' => $cachedScripts,
                'memory_used_mb' => $usedMemoryMb,
            ]);

        // ۱. بررسی حافظه هدررفته (Wasted Memory) - نشانه ریست شدن دائمی کش
        if ($wastedPercentage > 15) {
            return $result->warning("حافظه هدررفته OPcache بالا است: {$wastedPercentage}%");
        }

        // ۲. اگر ورکر تازه راه‌اندازی شده و تعداد ریکوئست‌ها کم است (زیر ۱۰۰ درخواست)،
        // نباید به خاطر هیت‌ریت پایین هشدار صادر شود، چون کش هنوز در فاز Warm-up است.
        if ($totalRequests < 100) {
            return $result->ok();
        }

        // ۳. اگر ترافیک به اندازه کافی بود و هیت‌ریت به طرز مشکوکی زیر ۳۰٪ ماند:
        if ($hitRate < 30) {
            return $result->warning("نرخ کشینگ در این ورکر پایینه: {$hitRate}%");
        }

        return $result->ok();
    }
}
