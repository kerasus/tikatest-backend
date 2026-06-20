<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_question_id',
        'option_number',
        'option_text',
        'option_image_url',
        'is_correct_answer',
    ];

    protected $casts = [
        'is_correct_answer' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function studentResponses(): HasMany
    {
        return $this->hasMany(QuizAttemptResponse::class, 'quiz_question_option_id');
    }
}
