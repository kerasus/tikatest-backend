<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $lesson_id
 * @property int|null $study_session_id
 * @property string $type
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property int|null $duration_seconds
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \App\Models\User $student
 * @property-read \App\Models\StudySession|null $studySession
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereStudySessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearningActivity whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
