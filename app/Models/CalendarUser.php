<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \App\Models\Calendar|null $calendar
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarUser withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarUser withoutTrashed()
 * @mixin \Eloquent
 */
class CalendarUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'calendar_id',
        'user_id',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
