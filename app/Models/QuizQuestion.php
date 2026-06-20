<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_number',
        'question_text',
        'question_type',
        'points',
        'has_negative_marking',
        'negative_marks',
        'question_image_url',
        'explanation',
    ];

    protected $casts = [
        'has_negative_marking' => 'boolean',
        'points' => 'decimal:2',
        'negative_marks' => 'decimal:2',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizAttemptResponse::class);
    }
}
