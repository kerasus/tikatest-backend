<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $class_id
 * @property int|null $skyroom_id شناسه اتاق در اسکای‌روم
 * @property string $name نام لاتین و یکتای اتاق در اسکای‌روم
 * @property string $title عنوان نمایشی اتاق/کلاس
 * @property string|null $description
 * @property int $max_users سقف تعداد کاربر آنلاین
 * @property bool $guest_login امکان ورود میهمان
 * @property bool $op_login_first الزام ورود اپراتور قبل از سایرین
 * @property bool $status 0: غیرفعال, 1: فعال
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SkyroomRoomSchedule> $schedules
 * @property-read int|null $schedules_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereGuestLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereMaxUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereOpLoginFirst($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereSkyroomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkyroomRoom withoutTrashed()
 * @mixin \Eloquent
 */
class SkyroomRoom extends Model
{
    use SoftDeletes;

    protected $table = 'skyroom_rooms';

    protected $fillable = [
        'class_id',
        'skyroom_account_id',
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
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function skyroomAccount(): BelongsTo
    {
        return $this->belongsTo(SchoolSkyroomAccount::class, 'skyroom_account_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SkyroomRoomSchedule::class, 'skyroom_room_id');
    }
}
