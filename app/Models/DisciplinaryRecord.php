<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $school_id
 * @property int $student_id
 * @property int $case_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $incident_date
 * @property int|null $recorded_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\DisciplinaryCase|null $disciplinaryCase
 * @property-read \App\Models\User|null $recordedBy
 * @property-read \App\Models\School|null $school
 * @property-read \App\Models\User $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereCaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereIncidentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereRecordedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisciplinaryRecord withoutTrashed()
 * @mixin \Eloquent
 */
class DisciplinaryRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'case_id',
        'description',
        'incident_date',
        'recorded_by',
    ];

    protected $casts = [
        'incident_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function disciplinaryCase(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
