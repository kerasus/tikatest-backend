<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $in_person_exam_id
 * @property int $user_id
 * @property numeric $raw_score
 * @property numeric|null $scaled_score
 * @property int|null $recorded_by
 * @property numeric|null $t_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array $class_ids
 * @property-read \App\Models\Exam|null $exam
 * @property-read string|null $exam_date
 * @property-read int|null $exam_id
 * @property-read string|null $grade_type
 * @property-read bool|null $is_descriptive
 * @property-read bool $is_report_card
 * @property-read int|null $lesson_id
 * @property-read \App\Models\InPersonExamDetail $inPersonExamDetail
 * @property-read \App\Models\User|null $recordedBy
 * @property-read \App\Models\User $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereInPersonExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereRawScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereScaledScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereTScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamResult whereUserId($value)
 * @mixin \Eloquent
 */
class InPersonExamResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'in_person_exam_id',
        'user_id',
        'raw_score',
        'scaled_score',
        'recorded_by',
        't_score',
    ];

    protected $casts = [
        'raw_score' => 'decimal:2',
        'scaled_score' => 'decimal:2',
        't_score' => 'decimal:4',
    ];

    protected $appends = [
//     'exam_id',
//     'lesson_id',
//     'class_ids',
//     'grade_type',
//     'exam_date',
//     'is_descriptive',
//     'is_report_card'
    ];

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
