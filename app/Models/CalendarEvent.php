<?php

namespace App\Models;

use App\Enums\CalendarEventSource;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $calendar_id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $all_day
 * @property CalendarEventType $type
 * @property CalendarEventStatus $status
 * @property string|null $location
 * @property string|null $color
 * @property CalendarEventSource $source
 * @property bool $is_recurring
 * @property string|null $recurrence_rule
 * @property \Illuminate\Support\Carbon|null $recurrence_until
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Calendar|null $calendar
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CalendarEventTarget> $targets
 * @property-read int|null $targets_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereAllDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereCalendarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereIsRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereRecurrenceRule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereRecurrenceUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent withoutTrashed()
 * @mixin \Eloquent
 */
class CalendarEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'calendar_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'type',
        'status',
        'location',
        'color',
        'source',
        'is_recurring',
        'recurrence_rule',
        'recurrence_until',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'is_recurring' => 'boolean',
        'type' => CalendarEventType::class,
        'status' => CalendarEventStatus::class,
        'source' => CalendarEventSource::class,
        'recurrence_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CalendarEventTarget::class, 'calendar_event_id');
    }
}
