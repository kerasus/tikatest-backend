<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEventTarget extends Model
{
    use HasFactory, SoftDeletes;

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
