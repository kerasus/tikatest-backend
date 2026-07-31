<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamAnswerKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'question_number',
        'correct_option',
        'weight',
        'has_negative_mark',
        'is_active',
    ];

    protected $casts = [
        'question_number' => 'integer',
        'weight' => 'decimal:2',
        'has_negative_mark' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
