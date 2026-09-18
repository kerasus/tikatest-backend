<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkyroomRoomSchedule extends Model
{
    protected $table = 'skyroom_room_schedules';

    protected $fillable = [
        'skyroom_room_id',
        'title',
        'day_of_week',
        'held_date',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'held_date'   => 'date',
        'is_active'   => 'boolean',
        'day_of_week' => 'integer',
    ];

    public function skyroomRoom(): BelongsTo
    {
        return $this->belongsTo(SkyroomRoom::class, 'skyroom_room_id');
    }
}
