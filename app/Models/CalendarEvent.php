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
