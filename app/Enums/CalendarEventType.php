<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case General = 'general';
    case ClassEvent = 'class';
    case Exam = 'exam';
    case Homework = 'homework';
    case Meeting = 'meeting';
    case Holiday = 'holiday';
    case National = 'national';
    case Religious = 'religious';
    case Reminder = 'reminder';

    public function label(): string
    {
        return match ($this) {
            self::General => 'عمومی',
            self::ClassEvent => 'کلاس',
            self::Exam => 'امتحان',
            self::Homework => 'تکلیف',
            self::Meeting => 'جلسه',
            self::Holiday => 'تعطیل',
            self::National => 'ملی',
            self::Religious => 'مذهبی',
            self::Reminder => 'یادآوری',
        };
    }
}
