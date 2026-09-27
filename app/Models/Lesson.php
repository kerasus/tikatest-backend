<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'academic_level_id',
        'order',
        'coefficient',
    ];

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_lesson', 'lesson_id', 'class_id')
            ->withTimestamps();
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function homework(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    public function scopeForClassWithFallback(Builder $query, mixed $classId): Builder
    {
        if (empty($classId)) {
            return $query;
        }

        $schoolClass = SchoolClass::find($classId);

        if (! $schoolClass) {
            return $query;
        }

        // آیا کلاً درسی به این کلاس منتسب شده؟
        $hasClassLessons = \DB::table('class_lesson')
            ->where('class_id', $schoolClass->id)
            ->exists();

        if ($hasClassLessons) {
            // حالت اول: فقط درس‌های منتسب به این کلاس
            return $query->whereHas('classes', function (Builder $q) use ($schoolClass) {
                $q->where('classes.id', $schoolClass->id);
            });
        }

        // حالت دوم (Fallback): کلاس درسی ندارد؛ تمام درس‌های مقطع/پایه این کلاس را نشان بده
        return $query->where('academic_level_id', $schoolClass->academic_level_id);
    }
}
