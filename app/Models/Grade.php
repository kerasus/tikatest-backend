<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'exam_session_id',
        'lesson_id',
        'student_id',
        'class_id',
        'raw_grade',
        'calculated_grade',
        'min_grade',
        'grade_type',
        'grade_name_for_other_type',
        'is_report_card',
        'is_descriptive',
        'descriptive_value',
        'is_visible',
        'z_score',
        'exam_date',
        'explanation',
    ];

    protected $casts = [
        'is_report_card' => 'boolean',
        'is_descriptive' => 'boolean',
        'is_visible' => 'boolean',
        'raw_grade' => 'decimal:2',
        'calculated_grade' => 'decimal:2',
        'min_grade' => 'decimal:2',
        'z_score' => 'decimal:4',
        'descriptive_value' => 'integer',
        'exam_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function getDescriptiveLabelAttribute(): ?string
    {
        if (!$this->is_descriptive) {
            return null;
        }

        return match($this->descriptive_value) {
            1 => 'خیلی خوب',
            2 => 'خوب',
            3 => 'قابل قبول',
            4 => 'نیاز به آموزش و تلاش بیشتر',
            default => null,
        };
    }
}