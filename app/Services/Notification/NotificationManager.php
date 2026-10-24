<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Services\Notification\Contracts\NotificationChannel;
use Illuminate\Support\Manager;

class NotificationManager extends Manager
{
    /**
     * خروجی پیش‌فرض (کانال fallback)
     */
    public function getDefaultDriver(): string
    {
        return config('notifications.default_channel', 'bale');
    }

    protected function createDriver($name): NotificationChannel
    {
        $class = config("notifications.channels.{$name}");

        if (! $class || ! class_exists($class)) {
            throw new \InvalidArgumentException("کانال نوتیفیکیشن [{$name}] پشتیبانی نمی‌شود.");
        }

        return app($class);
    }

    /**
     * ارسال از طریق یک کانال مشخص
     */
    public function sendVia(string $channel, User $user, NotificationMessage $message): array
    {
        /** @var NotificationChannel $driver */
        $driver = $this->driver($channel);

        if (! $driver->isAvailableFor($user)) {
            return ['channel' => $channel, 'ok' => false, 'skipped' => true];
        }

        return $driver->send($user, $message);
    }

    /**
     * ارسال از طریق همه کانال‌های فعال برای کاربر (با تحمل خطا)
     */
    public function broadcast(User $user, NotificationMessage $message, ?array $channels = null): array
    {
        $results = [];

        foreach ($channels ?? array_keys(config('notifications.channels', [])) as $channel) {
            try {
                $results[$channel] = $this->sendVia($channel, $user, $message);
            } catch (\Throwable $e) {
                report($e);
                $results[$channel] = ['channel' => $channel, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
