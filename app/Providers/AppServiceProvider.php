<?php

namespace App\Providers;

use App\Checks\OpcacheCheck;
use Spatie\Health\Facades\Health;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Collection::macro('std', function ($mode = 1) {
            $collection = $this;
            $count = $collection->count();

            if ($count === 0 || $count === 1) {
                return 0;
            }

            $mean = $collection->avg();

            if ($mode === 1) {
                $variance = $collection->map(fn ($v) => pow($v - $mean, 2))->avg();
            } else {
                $variance = $collection->map(fn ($v) => pow($v - $mean, 2))->sum() / ($count - 1);
            }

            return sqrt($variance);
        });

        Health::checks([
            // ۱. چک فضای دیسک (هشدار اگر بالای ۸۰٪ پر شد)
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(80)
                ->failWhenUsedSpaceIsAbovePercentage(90),

            // ۲. اتصال سریع به دیتابیس
            DatabaseCheck::new(),

            // ۳. چک خاموش بودن Debug Mode در پروداکشن (امنیت)
            DebugModeCheck::new(),

            // ۴. چک کش بودن Config و Routeها برای سرعت
            OptimizedAppCheck::new(),

            OpcacheCheck::new()->name('Opcache')->label('PHP OPcache'),
        ]);
    }
}
