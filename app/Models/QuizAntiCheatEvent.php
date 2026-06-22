<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAntiCheatEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id',
        'student_id',
        'event_type',
        'event_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'event_data' => 'array',
    ];

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
