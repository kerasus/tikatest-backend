<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
