<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InPersonExamResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'in_person_exam_id',
        'user_id',
        'raw_score',
        'scaled_score',
        'recorded_by',
        'z_score',
    ];

    protected $casts = [
        'raw_score' => 'decimal:2',
        'scaled_score' => 'decimal:2',
        'z_score' => 'decimal:4',
    ];

    protected $appends = ['exam_id', 'lesson_id', 'class_ids', 'grade_type', 'exam_date', 'is_descriptive', 'is_report_card'];

    public function inPersonExamDetail(): BelongsTo
    {
        return $this->belongsTo(InPersonExamDetail::class, 'in_person_exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getExamAttribute(): ?Exam
    {
        return $this->inPersonExamDetail?->exam;
    }

    public function getExamIdAttribute(): ?int
    {
        return $this->inPersonExamDetail?->exam?->id;
    }

    public function getLessonIdAttribute(): ?int
    {
        return $this->inPersonExamDetail?->exam?->lesson_id;
    }

    public function getClassIdsAttribute(): array
    {
        return $this->inPersonExamDetail?->exam?->classes?->pluck('id')->toArray() ?? [];
    }

    public function getGradeTypeAttribute(): ?string
    {
        return $this->inPersonExamDetail?->exam?->category?->title;
    }

    public function getExamDateAttribute(): ?string
    {
        return $this->inPersonExamDetail?->held_at?->toDateString();
    }

    public function getIsDescriptiveAttribute(): ?bool
    {
        return $this->inPersonExamDetail?->is_descriptive;
    }

    public function getIsReportCardAttribute(): bool
    {
        return in_array($this->grade_type, ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2']);
    }
}
