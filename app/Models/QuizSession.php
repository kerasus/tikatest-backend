<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'quiz_id',
        'student_id',
        'lesson_id',
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
        'percent',
        'answer_status',
        'is_locked',
    ];

    protected $casts = [
        'session_started_at' => 'datetime',
        'session_ended_at' => 'datetime',
        'submitted_at' => 'datetime',
            'submission_data' => 'array',
            'booklet_scores' => 'array',
            'is_locked' => 'boolean',
        'percent' => 'decimal:2',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizSessionResponse::class, 'quiz_session_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'in_progress' &&
               ($this->session_ended_at === null || $this->session_ended_at->isFuture());
    }

    public function isExpired(): bool
    {
        if ($this->status === 'in_progress' && $this->session_ended_at !== null) {
            return $this->session_ended_at->isPast();
        }
        return $this->status === 'expired';
    }

    public function getRemainingTimeInSeconds(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        if ($this->session_ended_at === null) {
            return 0;
        }

        $remaining = now()->diffInSeconds($this->session_ended_at, false);

        return max(0, $remaining);
    }

    public function getTimeUsedInSeconds(): int
    {
        if ($this->session_started_at === null) {
            return 0;
        }

        $endPoint = $this->submitted_at ?? now();
        return (int) $this->session_started_at->diffInSeconds($endPoint);
    }
}
