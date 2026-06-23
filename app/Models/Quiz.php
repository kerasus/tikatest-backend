<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'time_limit',
        'starts_at',
        'ends_at',
        'start_time',
        'end_time',
        'description',
        'is_visible',
        'quiz_type',
        'content',
        'solution',
        'show_answer_date',
        'no_score_questions',
    ];

    protected $appends = [
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'content' => 'array',
        'solution' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'show_answer_date' => 'datetime',
    ];

    public function getStartTimeAttribute(): ?Carbon
    {
        return $this->starts_at;
    }

    public function setStartTimeAttribute($value): void
    {
        $this->attributes['starts_at'] = $value;
    }

    public function getEndTimeAttribute(): ?Carbon
    {
        return $this->ends_at;
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['ends_at'] = $value;
    }

    public function quizClassAssignments(): HasMany
    {
        return $this->hasMany(QuizClassAssignment::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function answerKeys(): HasMany
    {
        return $this->hasMany(QuizAnswerKey::class);
    }
}
