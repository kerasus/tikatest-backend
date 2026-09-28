<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $school_id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisciplinaryRecord> $disciplinaryRecords
 * @property-read int|null $disciplinary_records_count
 * @property-read \App\Models\School|null $school
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryCase withoutTrashed()
 * @mixin \Eloquent
 */
class DisciplinaryCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
        'description',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'case_id');
    }
}
