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
 * @property string $name
 * @property int|null $academic_level_id
 * @property int $order
 * @property numeric $coefficient
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicLevel|null $academicLevel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SchoolClass> $classes
 * @property-read int|null $classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Homework> $homework
 * @property-read int|null $homework_count
 * @method static Builder<static>|Lesson forClassWithFallback(?mixed $classIds)
 * @method static Builder<static>|Lesson newModelQuery()
 * @method static Builder<static>|Lesson newQuery()
 * @method static Builder<static>|Lesson onlyTrashed()
 * @method static Builder<static>|Lesson query()
 * @method static Builder<static>|Lesson whereAcademicLevelId($value)
 * @method static Builder<static>|Lesson whereCoefficient($value)
 * @method static Builder<static>|Lesson whereCreatedAt($value)
 * @method static Builder<static>|Lesson whereDeletedAt($value)
 * @method static Builder<static>|Lesson whereId($value)
 * @method static Builder<static>|Lesson whereName($value)
 * @method static Builder<static>|Lesson whereOrder($value)
 * @method static Builder<static>|Lesson whereUpdatedAt($value)
 * @method static Builder<static>|Lesson withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Lesson withoutTrashed()
 * @mixin \Eloquent
 */
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

    public function scopeForClassWithFallback(Builder $query, mixed $classIds): Builder
    {
        if (empty($classIds)) {
            return $query;
        }

        // نرمال‌سازی ورودی به آرایه‌ای از شناسه‌های معتبر عددی
        if (is_string($classIds)) {
            $classIds = explode(',', $classIds);
        }

        $classIds = array_filter((array) $classIds);

        if (empty($classIds)) {
            return $query;
        }

        // لود همه کلاس‌های مورد نظر
        $classes = SchoolClass::whereIn('id', $classIds)->get();

        if ($classes->isEmpty()) {
            return $query;
        }

        // پیدا کردن کلاس‌هایی که در جدول پیوت درس ثبت شده دارند
        $classesWithDirectLessons = \DB::table('class_lesson')
            ->whereIn('class_id', $classes->pluck('id'))
            ->pluck('class_id')
            ->unique()
            ->toArray();

        // تفکیک کلاس‌ها به دو گروه
        $directClassIds = [];
        $fallbackLevelIds = [];

        foreach ($classes as $class) {
            if (in_array($class->id, $classesWithDirectLessons)) {
                $directClassIds[] = $class->id;
            } else {
                // کلاسی که درس منتسب نداره -> مقطع تحصیلی‌اش باید fallback بشه
                $fallbackLevelIds[] = $class->academic_level_id;
            }
        }

        $fallbackLevelIds = array_unique(array_filter($fallbackLevelIds));

        // اعمال شرط روی کوئری
        return $query->where(function (Builder $q) use ($directClassIds, $fallbackLevelIds) {
            // ۱. درس‌های منتسب مستقیم به کلاس‌هایی که درس اختصاصی دارند
            if (! empty($directClassIds)) {
                $q->whereHas('classes', function (Builder $classQ) use ($directClassIds) {
                    $classQ->whereIn('classes.id', $directClassIds);
                });
            }

            // ۲. درس‌های مقطع کلاس‌هایی که درس اختصاصی ندارند
            if (! empty($fallbackLevelIds)) {
                if (! empty($directClassIds)) {
                    $q->orWhereIn('academic_level_id', $fallbackLevelIds);
                } else {
                    $q->whereIn('academic_level_id', $fallbackLevelIds);
                }
            }
        })->distinct();
    }
}
