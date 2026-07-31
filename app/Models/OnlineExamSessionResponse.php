<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamSessionResponse extends Model
{
    use HasFactory;

    protected $table = 'online_exam_session_responses';

    protected $fillable = [
        'online_exam_session_id',
        'exam_id',
        'user_id',
        'question_number',
        'submitted_option',
        'answer_text',
        'is_correct',
        'marks_obtained',
        'answered_at',
    ];

    protected $casts = [
        'question_number' => 'integer',
        'is_correct' => 'boolean',
        'marks_obtained' => 'decimal:2',
        'answered_at' => 'datetime',
    ];

    public function onlineExamSession(): BelongsTo
    {
        return $this->belongsTo(OnlineExamSession::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
