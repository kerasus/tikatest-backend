<?php

namespace App\Services\Notification;

class NotificationMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionText = null,
        public readonly array $data = [],       // دیتای اضافه مخصوص هر کانال
    ) {}

    public static function make(string $title, string $body): self
    {
        return new self($title, $body);
    }

    public function withAction(string $url, string $text): self
    {
        return new self($this->title, $this->body, $url, $text, $this->data);
    }

    public function withData(array $data): self
    {
        return new self($this->title, $this->body, $this->actionUrl, $this->actionText, $data);
    }
}
