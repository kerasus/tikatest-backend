<?php

namespace App\Models;

use App\Enums\StudySessionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
