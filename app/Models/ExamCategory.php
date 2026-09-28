<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int|null $school_id
 * @property string $title
 * @property int|null $term_number
 * @property int $sort_order
 * @property bool $is_system
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamCategoryTermLimit> $termLimits
 * @property-read int|null $term_limits_count
 * @method static Builder<static>|ExamCategory forSchoolOrGlobal($schoolId = null)
 * @method static Builder<static>|ExamCategory newModelQuery()
 * @method static Builder<static>|ExamCategory newQuery()
 * @method static Builder<static>|ExamCategory query()
 * @method static Builder<static>|ExamCategory whereCreatedAt($value)
 * @method static Builder<static>|ExamCategory whereId($value)
 * @method static Builder<static>|ExamCategory whereIsSystem($value)
 * @method static Builder<static>|ExamCategory whereSchoolId($value)
 * @method static Builder<static>|ExamCategory whereSortOrder($value)
 * @method static Builder<static>|ExamCategory whereTermNumber($value)
 * @method static Builder<static>|ExamCategory whereTitle($value)
 * @method static Builder<static>|ExamCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ExamCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'title',
        'term_number',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'term_number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'exam_category_id');
    }

    public function termLimits(): HasMany
    {
        return $this->hasMany(ExamCategoryTermLimit::class, 'exam_category_id');
    }

    public function scopeForSchoolOrGlobal(Builder $query, $schoolId = null): Builder
    {
        return $query->where(function (Builder $q) use ($schoolId) {
            $q->whereNull('school_id');

            if (!empty($schoolId)) {
                $q->orWhere('school_id', $schoolId);
            }
        });
    }
}
