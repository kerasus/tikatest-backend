<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $academic_level_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicLevel|null $academicLevel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Homework> $homework
 * @property-read int|null $homework_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TermEnrollment> $termEnrollments
 * @property-read int|null $term_enrollments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass whereAcademicLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolClass withoutTrashed()
 * @mixin \Eloquent
 */
class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'academic_level_id',
        'name',
    ];

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function getSchoolAttribute(): ?School
    {
        return $this->academicLevel?->academicField?->school;
    }

    public function termEnrollments(): HasMany
    {
        return $this->hasMany(TermEnrollment::class, 'class_id', 'id');
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_classes');
    }

    public function homework(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    public function skyroomRooms(): HasMany
    {
        return $this->hasMany(SkyroomRoom::class, 'class_id');
    }
}
