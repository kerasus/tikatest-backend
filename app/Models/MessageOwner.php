<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
