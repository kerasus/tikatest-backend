<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $lesson_id
 * @property int $term_id
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AcademicLevel> $academicLevels
 * @property-read int|null $academic_levels_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HomeworkAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SchoolClass> $classes
 * @property-read int|null $classes_count
 * @property-read \App\Models\User $createdBy
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HomeworkSubmission> $submissions
 * @property-read int|null $submissions_count
 * @property-read \App\Models\AcademicTerm $term
 * @method static Builder<static>|Homework forStudent(\App\Models\User|int $student, bool $activeEnrollmentsOnly = true)
 * @method static Builder<static>|Homework inSchool($schoolId)
 * @method static Builder<static>|Homework newModelQuery()
 * @method static Builder<static>|Homework newQuery()
 * @method static Builder<static>|Homework onlyTrashed()
 * @method static Builder<static>|Homework query()
 * @method static Builder<static>|Homework whereCreatedAt($value)
 * @method static Builder<static>|Homework whereCreatedBy($value)
 * @method static Builder<static>|Homework whereDeletedAt($value)
 * @method static Builder<static>|Homework whereDescription($value)
 * @method static Builder<static>|Homework whereDueDate($value)
 * @method static Builder<static>|Homework whereId($value)
 * @method static Builder<static>|Homework whereLessonId($value)
 * @method static Builder<static>|Homework whereTermId($value)
 * @method static Builder<static>|Homework whereTitle($value)
 * @method static Builder<static>|Homework whereUpdatedAt($value)
 * @method static Builder<static>|Homework withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Homework withoutTrashed()
 * @mixin \Eloquent
 */
class Homework extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'lesson_id',
        'term_id',
        'due_date',
        'created_by',
        'sort_order',
        'content',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HomeworkAttachment::class);
    }

    public function academicLevels(): BelongsToMany
    {
        return $this->belongsToMany(AcademicLevel::class, 'homework_academic_levels');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'homework_classes', 'homework_id', 'class_id');
    }

    public function scopeInSchool(Builder $query, $schoolId): Builder
    {
        return $query->whereHas('academicLevels.academicField', function (Builder $q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        });
    }

    public function scopeForStudent(Builder $query, User|int $student, bool $activeEnrollmentsOnly = true): Builder
    {
        $studentId = $student instanceof User ? $student->id : $student;

        return $query->where(function (Builder $q) use ($studentId, $activeEnrollmentsOnly) {
            // ۱. تکالیفی که مستقیماً برای کلاس‌های دانش‌آموز تعریف شده‌اند
            $q->whereHas('classes.termEnrollments', function (Builder $subQ) use ($studentId, $activeEnrollmentsOnly) {
                $subQ->where('user_id', $studentId);

                if ($activeEnrollmentsOnly) {
                    $subQ->where(function ($dateQ) {
                        $dateQ->whereNull('left_at')
                            ->orWhere('left_at', '>', now());
                    });
                }
            })
                // ۲. یا تکالیفی که برای کل مقطع تحصیلی (AcademicLevel) کلاسی که دانش‌آموز در آن است تعریف شده‌اند
                ->orWhereHas('academicLevels.classes.termEnrollments', function (Builder $subQ) use ($studentId, $activeEnrollmentsOnly) {
                    $subQ->where('user_id', $studentId);

                    if ($activeEnrollmentsOnly) {
                        $subQ->where(function ($dateQ) {
                            $dateQ->whereNull('left_at')
                                ->orWhere('left_at', '>', now());
                        });
                    }
                });
        });
    }
}
