<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'lesson_id',
        'study_session_id',
        'type',
        'occurred_at',
        'duration_seconds',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'duration_seconds' => 'integer',
        'metadata' => 'array',
        'student_id' => 'integer',
        'lesson_id' => 'integer',
        'study_session_id' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }
}
