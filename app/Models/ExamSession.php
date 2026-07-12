<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'lesson_id',
        'class_id',
        'exam_date',
        'grade_type',
        'grade_name_for_other_type',
        'is_descriptive',
        'is_report_card',
        'quiz_session_id',
        'min_grade',
        'created_by',
    ];

    protected $casts = [
        'is_descriptive' => 'boolean',
        'is_report_card' => 'boolean',
        'min_grade' => 'decimal:2',
        'exam_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function getGradeTypeLabelAttribute(): string
    {
        return match($this->grade_type) {
            'class_quiz' => 'آزمون کلاسی',
            'monthly_quiz' => 'آزمون ماهانه',
            'mid_term_1' => 'میان ترم اول',
            'continuous_1' => 'مستمر اول',
            'final_1' => 'پایان ترم اول',
            'mid_term_2' => 'میان ترم دوم',
            'continuous_2' => 'مستمر دوم',
            'final_2' => 'پایان ترم دوم',
            'other' => $this->grade_name_for_other_type ?: 'سایر',
            default => $this->grade_type,
        };
    }
}