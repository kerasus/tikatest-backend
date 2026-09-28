<?php

namespace App\Models;

use App\Enums\StudySessionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $lesson_id
 * @property int $term_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $ended_at
 * @property string|null $description
 * @property StudySessionSource $source
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \App\Models\User $student
 * @property-read \App\Models\AcademicTerm $term
 * @method static Builder<static>|StudySession newModelQuery()
 * @method static Builder<static>|StudySession newQuery()
 * @method static Builder<static>|StudySession query()
 * @method static Builder<static>|StudySession sumDurationMinutes()
 * @method static Builder<static>|StudySession whereCreatedAt($value)
 * @method static Builder<static>|StudySession whereDescription($value)
 * @method static Builder<static>|StudySession whereEndedAt($value)
 * @method static Builder<static>|StudySession whereId($value)
 * @method static Builder<static>|StudySession whereLessonId($value)
 * @method static Builder<static>|StudySession whereMetadata($value)
 * @method static Builder<static>|StudySession whereSource($value)
 * @method static Builder<static>|StudySession whereStartedAt($value)
 * @method static Builder<static>|StudySession whereStudentId($value)
 * @method static Builder<static>|StudySession whereTermId($value)
 * @method static Builder<static>|StudySession whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class StudySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'lesson_id',
        'term_id',
        'started_at',
        'ended_at',
        'description',
        'source',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'source' => StudySessionSource::class,
        'metadata' => 'array',
        'term_id' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function scopeSumDurationMinutes(Builder $query): int
    {
        return (int) $query
            ->whereNotNull('ended_at')
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, started_at, ended_at)), 0) as total_minutes')
            ->value('total_minutes');
    }
}
