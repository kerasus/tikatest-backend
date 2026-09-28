<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $calendar_event_id
 * @property string $target_type
 * @property int $target_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CalendarEvent|null $calendarEvent
 * @property-read Model|\Eloquent $target
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget whereCalendarEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget whereTargetType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEventTarget whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CalendarEventTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_event_id',
        'target_type',
        'target_id',
    ];

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
