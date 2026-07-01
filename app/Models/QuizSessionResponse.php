<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSessionResponse extends Model
{
    use HasFactory;

    protected $table = 'quiz_session_responses';

    protected $fillable = [
        'quiz_session_id',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }
}