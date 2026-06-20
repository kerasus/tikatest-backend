<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'status',
        'session_started_at',
        'session_ended_at',
        'submitted_at',
        'duration_seconds',
        'time_used_seconds',
        'ip_address',
        'user_agent',
        'attempt_number',
        'submission_data',
    ];

    protected $casts = [
        'session_started_at' => 'datetime',
        'session_ended_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'id', 'quiz_session_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'in_progress' &&
               ($this->session_ended_at === null || $this->session_ended_at->isFuture());
    }

    public function isExpired(): bool
    {
        if ($this->session_ended_at === null) {
            return false;
        }
        return $this->session_ended_at->isPast();
    }

    public function getRemainingTimeInSeconds(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($this->session_started_at);
        $remaining = $this->duration_seconds - $elapsed;

        return max(0, $remaining);
    }
}
