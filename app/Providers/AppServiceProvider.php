<?php

namespace App\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

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
                $variance = $collection->map(fn($v) => pow($v - $mean, 2))->avg();
            } else {
                $variance = $collection->map(fn($v) => pow($v - $mean, 2))->sum() / ($count - 1);
            }

            return sqrt($variance);
        });
    }
}
