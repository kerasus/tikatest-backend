<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string|null $personnel_code
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $left_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\School|null $school
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereJoinedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereLeftAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser wherePersonnelCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolUser whereUserId($value)
 * @mixin \Eloquent
 */
class SchoolUser extends Model
{
    use HasFactory;

    protected $table = 'school_user';

    protected $fillable = [
        'school_id',
        'user_id',
        'personnel_code',
        'is_active',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
