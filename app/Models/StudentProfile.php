<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $code
 * @property int $xp
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentGuardian> $guardians
 * @property-read int|null $guardians_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereXp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile withoutTrashed()
 * @mixin \Eloquent
 */
class StudentProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'code',
        'xp',
    ];

    protected $casts = [
        'xp' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'student_profile_id');
    }
}
