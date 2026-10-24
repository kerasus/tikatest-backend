<?php

return [
    'default_channel' => env('NOTIFICATION_DEFAULT_CHANNEL', 'bale'),

    'channels' => [
        'bale'   => \App\Services\Notification\Channels\BaleChannel::class,
        // 'email'  => \App\Services\Notification\Channels\EmailChannel::class,  // آینده!
        // 'sms'    => \App\Services\Notification\Channels\SmsChannel::class,    // آینده!
    ],
];
