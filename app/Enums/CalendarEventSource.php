<?php

namespace App\Enums;

enum CalendarEventSource: string
{
    case Manual = 'manual';
    case Official = 'official';
    case Imported = 'imported';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'دستی',
            self::Official => 'رسمی',
            self::Imported => 'وارد شده',
            self::System => 'سیستمی',
        };
    }
}
