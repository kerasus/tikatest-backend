<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AcademicLevel> $academicLevels
 * @property-read int|null $academic_levels_count
 * @property-read \App\Models\School|null $school
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicField withoutTrashed()
 * @mixin \Eloquent
 */
class AcademicField extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicLevels(): HasMany
    {
        return $this->hasMany(AcademicLevel::class, 'field_id');
    }
}
