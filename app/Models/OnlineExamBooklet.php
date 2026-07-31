<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamBooklet extends Model
{
    use HasFactory;

    protected $fillable = [
        'online_exam_id',
        'lesson_id',
        'title',
        'from_question',
        'to_question',
        'booklet_scores',
    ];

    protected $casts = [
        'from_question' => 'integer',
        'to_question' => 'integer',
        'booklet_scores' => 'array',
    ];

    public function onlineExamDetail(): BelongsTo
    {
        return $this->belongsTo(OnlineExamDetail::class, 'online_exam_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
