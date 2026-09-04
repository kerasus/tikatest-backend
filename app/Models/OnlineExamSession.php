<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'status',
        'started_at',
        'submitted_at',
        'duration_limit_seconds',
        'time_used_seconds',
        'score',
        'percent',
        'ip_address',
        'user_agent',
        'attempt_number',
        'is_locked',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'duration_limit_seconds' => 'integer',
        'time_used_seconds' => 'integer',
        'score' => 'decimal:2',
        'percent' => 'decimal:2',
        'attempt_number' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResponse::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResult::class);
    }
}
