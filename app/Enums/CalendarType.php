<?php

namespace App\Enums;

enum CalendarType: string
{
    case School = 'school';
    case Personal = 'personal';
    case National = 'national';
    case Religious = 'religious';

    public function label(): string
    {
        return match ($this) {
            self::School => 'تقویم آموزشگاه',
            self::Personal => 'تقویم شخصی',
            self::National => 'تقویم ملی',
            self::Religious => 'تقویم مذهبی',
        };
    }
}
