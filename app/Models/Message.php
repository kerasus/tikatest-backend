<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'sender_id',
        'subject',
        'body',
        'attachment',
        'is_sms',
        'message_type',
        'sent_at',
    ];

    protected $casts = [
        'is_sms' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function owners(): HasMany
    {
        return $this->hasMany(MessageOwner::class);
    }
}