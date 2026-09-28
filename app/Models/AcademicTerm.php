<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $type
 * @property string|null $academic_year
 * @property string|null $season
 * @property int|null $period
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_active
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AcademicTerm> $children
 * @property-read int|null $children_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TermEnrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read AcademicTerm|null $parentTerm
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamCategoryTermLimit> $termLimits
 * @property-read int|null $term_limits_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereAcademicYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicTerm whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AcademicTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'academic_year',
        'season',
        'period',
        'starts_at',
        'ends_at',
        'is_active',
        'parent_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'period' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parentTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AcademicTerm::class, 'parent_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'term_id');
    }

    public function termLimits(): HasMany
    {
        return $this->hasMany(ExamCategoryTermLimit::class, 'term_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TermEnrollment::class, 'term_id');
    }

    public function isSchoolYear(): bool
    {
        return $this->type === 'school_year';
    }

    public function isSeasonal(): bool
    {
        return $this->type === 'seasonal';
    }

    public function isSubTerm(): bool
    {
        return $this->type === 'sub_term';
    }
}
