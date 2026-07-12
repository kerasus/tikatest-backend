<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizBooklet extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'title',
        'from_question',
        'to_question',
    ];

    protected $casts = [
        'from_question' => 'integer',
        'to_question' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
