<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $message_id
 * @property int $user_id
 * @property bool $is_student
 * @property bool $is_father
 * @property bool $is_mother
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Message $message
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereIsFather($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereIsMother($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereIsStudent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageOwner whereUserId($value)
 * @mixin \Eloquent
 */
class MessageOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'is_student',
        'is_father',
        'is_mother',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_student' => 'boolean',
        'is_father' => 'boolean',
        'is_mother' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
