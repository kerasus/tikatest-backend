<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $field_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicField|null $academicField
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SchoolClass> $classes
 * @property-read int|null $classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Lesson> $lessons
 * @property-read int|null $lessons_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel whereFieldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicLevel withoutTrashed()
 * @mixin \Eloquent
 */
class AcademicLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'field_id',
        'name',
    ];

    public function academicField(): BelongsTo
    {
        return $this->belongsTo(AcademicField::class, 'field_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
