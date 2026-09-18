<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkyroomRoom extends Model
{
    use SoftDeletes;

    protected $table = 'skyroom_rooms';

    protected $fillable = [
        'class_id',
        'skyroom_id',
        'name',
        'title',
        'description',
        'max_users',
        'guest_login',
        'op_login_first',
        'status',
    ];

    protected $casts = [
        'guest_login'    => 'boolean',
        'op_login_first' => 'boolean',
        'status'         => 'boolean',
        'max_users'      => 'integer',
        'skyroom_id'     => 'integer',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SkyroomRoomSchedule::class, 'skyroom_room_id');
    }
}
