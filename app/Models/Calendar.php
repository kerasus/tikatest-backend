<?php

namespace App\Models;

use App\Enums\CalendarType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property CalendarType $type
 * @property int|null $school_id
 * @property int|null $user_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CalendarEvent> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\School|null $school
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CalendarUser> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Calendar withoutTrashed()
 * @mixin \Eloquent
 */
class Calendar extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'school_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'type' => CalendarType::class,
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'calendar_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(CalendarUser::class, 'calendar_id');
    }
}
