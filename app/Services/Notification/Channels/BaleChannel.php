<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use RuntimeException;
use App\Services\Bale\BaleService;
use App\Services\Notification\NotificationMessage;
use App\Services\Notification\Contracts\NotificationChannel;

class BaleChannel implements NotificationChannel
{
    public function __construct(
        private readonly BaleService $baleService,
    ) {
    }

    public function key(): string
    {
        return 'bale';
    }

    public function isAvailableFor(User $user): bool
    {
        return filled($user->bale_chat_id);
    }

    public function send(User $user, NotificationMessage $message): array
    {
        if (! $this->isAvailableFor($user)) {
            throw new RuntimeException(
                'شناسه گفت‌وگوی بله برای این کاربر ثبت نشده است.'
            );
        }

        $response = $this->baleService->sendMessage(
            chatId: (string) $user->bale_chat_id,
            text: $this->formatText($message),
            options: [
                'parse_mode' => 'HTML',
            ],
        );

        return [
            'channel' => $this->key(),
            'ok' => true,
            'message_id' => data_get($response, 'result.message_id'),
            'response' => $response,
        ];
    }

    protected function formatText(NotificationMessage $message): string
    {
        $title = $this->escapeHtml($message->title);
        $body = $this->escapeHtml($message->body);

        $text = "<b>{$title}</b>\n\n{$body}";

        if (filled($message->actionUrl)) {
            $actionUrl = $this->escapeHtml($message->actionUrl);
            $actionText = $this->escapeHtml(
                $message->actionText ?? 'مشاهده'
            );

            $text .= "\n\n<a href=\"{$actionUrl}\">{$actionText}</a>";
        }

        return $text;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
