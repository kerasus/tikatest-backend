<?php

namespace App\Enums;

enum GradeType: string
{
    case CLASS_QUIZ = 'class_quiz';
    case MONTHLY_QUIZ = 'monthly_quiz';
    case MID_TERM_1 = 'mid_term_1';
    case CONTINUOUS_1 = 'continuous_1';
    case FINAL_1 = 'final_1';
    case MID_TERM_2 = 'mid_term_2';
    case CONTINUOUS_2 = 'continuous_2';
    case FINAL_2 = 'final_2';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::CLASS_QUIZ => 'آزمون کلاسی',
            self::MONTHLY_QUIZ => 'آزمون ماهانه',
            self::MID_TERM_1 => 'میان ترم اول',
            self::CONTINUOUS_1 => 'مستمر اول',
            self::FINAL_1 => 'پایان ترم اول',
            self::MID_TERM_2 => 'میان ترم دوم',
            self::CONTINUOUS_2 => 'مستمر دوم',
            self::FINAL_2 => 'پایان ترم دوم',
            self::OTHER => 'سایر',
        };
    }

    public static function isReportCardType(string $gradeType): bool
    {
        return in_array($gradeType, [
            self::MID_TERM_1->value,
            self::CONTINUOUS_1->value,
            self::FINAL_1->value,
            self::MID_TERM_2->value,
            self::CONTINUOUS_2->value,
            self::FINAL_2->value,
        ]);
    }
}