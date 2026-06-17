<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'correct_answers',
        'timer',
        'start_time',
        'end_time',
        'explanation',
        'is_visible',
        'quiz_type',
        'question_url',
        'answer_explanation',
        'false_negative_grading',
        'questions_text',
        'answers_text',
        'picture_id',
        'show_answer_date',
        'no_score_questions',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'false_negative_grading' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'show_answer_date' => 'datetime',
    ];

    public function quizClassAssignments(): HasMany
    {
        return $this->hasMany(QuizClassAssignment::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
