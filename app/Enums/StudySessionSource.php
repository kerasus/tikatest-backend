<?php

namespace App\Enums;

enum StudySessionSource: string
{
    case Manual = 'manual';
    case Lms = 'lms';
    case OnlineClass = 'online_class';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'دستی',
            self::Lms => 'سیستم مدیریت یادگیری',
            self::OnlineClass => 'کلاس آنلاین',
        };
    }
}
