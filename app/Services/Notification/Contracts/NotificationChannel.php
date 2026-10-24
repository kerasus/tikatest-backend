<?php

namespace App\Services\Notification\Contracts;

use App\Models\User;
use App\Services\Notification\NotificationMessage;

interface NotificationChannel
{
    /**
     * آیا این کانال برای این کاربر قابل استفاده است؟
     * (مثلاً کاربر ایمیل ثبت نکرده یا chat_id بله ندارد)
     */
    public function isAvailableFor(User $user): bool;

    /**
     * ارسال پیام. خروجی: اطلاعات ارسال (موفقیت، شناسه پیام و...)
     *
     * @throws \App\Exceptions\NotificationSendException
     */
    public function send(User $user, NotificationMessage $message): array;

    /**
     * شناسه کانال (برای لاگ و صف)
     */
    public function key(): string;
}
