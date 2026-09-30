<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $lesson_id
 * @property numeric|null $min_passing_score
 * @property numeric|null $max_score
 * @property string $delivery_mode
 * @property int $exam_category_id
 * @property int $term_id
 * @property int|null $occurrence
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AcademicLevel> $academicLevels
 * @property-read int|null $academic_levels_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamAnswerKey> $answerKeys
 * @property-read int|null $answer_keys_count
 * @property-read \App\Models\ExamCategory $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SchoolClass> $classes
 * @property-read int|null $classes_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\InPersonExamDetail|null $inPersonExamDetail
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InPersonExamResult> $inPersonExamResults
 * @property-read int|null $in_person_exam_results_count
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \App\Models\OnlineExamDetail|null $onlineExamDetail
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamSessionResponse> $onlineExamSessionResponses
 * @property-read int|null $online_exam_session_responses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamSessionResult> $onlineExamSessionResults
 * @property-read int|null $online_exam_session_results_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamSession> $onlineExamSessions
 * @property-read int|null $online_exam_sessions_count
 * @property-read \App\Models\AcademicTerm $term
 * @method static Builder<static>|Exam inSchool($schoolId)
 * @method static Builder<static>|Exam newModelQuery()
 * @method static Builder<static>|Exam newQuery()
 * @method static Builder<static>|Exam query()
 * @method static Builder<static>|Exam whereCreatedAt($value)
 * @method static Builder<static>|Exam whereCreatedBy($value)
 * @method static Builder<static>|Exam whereDeliveryMode($value)
 * @method static Builder<static>|Exam whereDescription($value)
 * @method static Builder<static>|Exam whereExamCategoryId($value)
 * @method static Builder<static>|Exam whereId($value)
 * @method static Builder<static>|Exam whereLessonId($value)
 * @method static Builder<static>|Exam whereMaxScore($value)
 * @method static Builder<static>|Exam whereMinPassingScore($value)
 * @method static Builder<static>|Exam whereName($value)
 * @method static Builder<static>|Exam whereOccurrence($value)
 * @method static Builder<static>|Exam whereTermId($value)
 * @method static Builder<static>|Exam whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'lesson_id',
        'min_passing_score',
        'max_score',
        'delivery_mode',
        'exam_category_id',
        'term_id',
        'occurrence',
        'created_by',
    ];

    protected $casts = [
        'min_passing_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'delivery_mode' => 'string',
        'occurrence' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inPersonExamDetail(): HasOne
    {
        return $this->hasOne(InPersonExamDetail::class);
    }

    public function onlineExamDetail(): HasOne
    {
        return $this->hasOne(OnlineExamDetail::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_classes', 'exam_id', 'class_id');
    }

    public function academicLevels(): BelongsToMany
    {
        return $this->belongsToMany(AcademicLevel::class, 'exam_academic_levels');
    }

    public function inPersonExamResults(): HasManyThrough
    {
        return $this->hasManyThrough(
            InPersonExamResult::class,
            InPersonExamDetail::class,
            'exam_id',
            'in_person_exam_id',
            'id',
            'id'
        );
    }

    public function grades()
    {
        return $this->inPersonExamResult();
    }

    public function onlineExamSessions(): HasMany
    {
        return $this->hasMany(OnlineExamSession::class);
    }

    public function onlineExamSessionResponses(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResponse::class);
    }

    public function onlineExamSessionResults(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResult::class);
    }

    public function isOnline(): bool
    {
        return $this->delivery_mode === 'online';
    }

    public function isInPerson(): bool
    {
        return $this->delivery_mode === 'in_person';
    }

    public function scopeInSchool(Builder $query, $schoolId): Builder
    {
        return $query->where(function (Builder $subQuery) use ($schoolId) {
            // ۱. از طریق کلاس‌های متصل به آزمون (که رایج‌ترین حالته)
            $subQuery->whereHas('classes.academicLevel.academicField', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            // ۲. یا از طریق پایه‌های متصل مستقیم به آزمون
            ->orWhereHas('academicLevels.academicField', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            // ۳. یا حتی از طریق ترم تحصیلی فعال آزمون
            ->orWhereHas('term', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            });
        });
    }
}
