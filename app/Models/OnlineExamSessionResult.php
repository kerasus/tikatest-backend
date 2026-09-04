<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamSessionResult extends Model
{
    use HasFactory;

    protected $table = 'online_exam_session_results';

    protected $fillable = [
        'online_exam_session_id',
        'exam_id',
        'student_id',
        'online_exam_booklet_id',
        'lesson_id',
        'lesson_title',
        'scope',
        'raw_score',
        'max_score',
        'scaled_score',
        'percent',
        'question_count',
        'answered_count',
        'correct_count',
        'wrong_count',
        'unanswered_count',
        'z_score',
    ];

    protected $casts = [
        'scope' => 'string',
        'raw_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'scaled_score' => 'decimal:2',
        'percent' => 'decimal:2',
        'question_count' => 'integer',
        'answered_count' => 'integer',
        'correct_count' => 'integer',
        'wrong_count' => 'integer',
        'unanswered_count' => 'integer',
        'z_score' => 'decimal:4',
    ];

    public function onlineExamSession(): BelongsTo
    {
        return $this->belongsTo(OnlineExamSession::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function onlineExamBooklet(): BelongsTo
    {
        return $this->belongsTo(OnlineExamBooklet::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
