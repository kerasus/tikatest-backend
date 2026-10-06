<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $skyroom_room_id
 * @property string|null $title عنوان جلسه، مثلاً: جلسه حل تمرین یا درس اصلی
 * @property int|null $day_of_week 0: Saturday ... 6: Friday
 * @property \Illuminate\Support\Carbon|null $held_date تاریخ برگزاری در صورت موردی بودن
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SkyroomRoom|null $skyroomRoom
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereHeldDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereSkyroomRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoomSchedule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(SkyroomRoom::class, 'skyroom_room_id');
    }

    public function skyroomRoom(): BelongsTo
    {
        return $this->room();
    }
}
