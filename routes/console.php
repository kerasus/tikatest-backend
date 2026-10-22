<?php

use App\Services\Bale\BaleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bale:set-webhook', function (BaleService $bale) {
    $secret = (string) config('bale.webhook_secret');

    if ($secret === '') {
        $this->error('ابتدا BALE_WEBHOOK_SECRET را تنظیم کنید.');

        return 1;
    }

    $url = route('bale.webhook', [], true)
        . '?' . http_build_query(['secret' => $secret]);

    if (str_starts_with($url, 'https://') === false) {
        $this->error('APP_URL باید آدرس HTTPS عمومی سایت باشد.');

        return 1;
    }


    \Log::info('bale:set-webhook', [
        'url' => $url
    ]);

    $bale->setWebhook($url);
    $this->info('وبهوک بله تنظیم شد.');

    return 0;
})->purpose('Register the Bale webhook with the configured secret');
