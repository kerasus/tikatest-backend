<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_attempt_id',
        'quiz_question_id',
        'quiz_question_option_id',
        'user_id',
        'quiz_id',
        'question_number',
        'submitted_option',
        'answer_text',
        'is_correct',
        'marks_obtained',
        'answered_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'marks_obtained' => 'decimal:2',
        'answered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionOption::class, 'quiz_question_option_id');
    }
}