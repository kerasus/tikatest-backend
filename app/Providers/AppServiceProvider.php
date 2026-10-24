<?php

namespace App\Providers;

use App\Checks\OpcacheCheck;
use App\Checks\DiskSpaceCheck;
use App\Checks\DatabaseInfoCheck;
use Spatie\Health\Facades\Health;
use Illuminate\Support\Collection;
use App\Checks\SslCertificateCheck;
use App\Checks\DatabaseLatencyCheck;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use App\Services\Notification\NotificationManager;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $sslDomain = env('DOMAIN', parse_url(config('app.url'), PHP_URL_HOST) ?? 'sanjad.ir');

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

            // ۳. چک خاموش بودن Debug Mode در پروداکشن (امنیت)
            DebugModeCheck::new(),

            // ۴. چک کش بودن Config و Routeها برای سرعت
            OptimizedAppCheck::new(),

            OpcacheCheck::new()->name('Opcache')->label('PHP OPcache'),

            DiskSpaceCheck::new(),

            DatabaseInfoCheck::new(),

            DatabaseLatencyCheck::new(),

            SslCertificateCheck::new(),
        ]);
    }
}
