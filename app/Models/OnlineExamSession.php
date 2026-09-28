<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $exam_id
 * @property int $student_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property int|null $duration_limit_seconds
 * @property int $time_used_seconds
 * @property numeric $t_score
 * @property numeric $percent
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $attempt_number
 * @property bool $is_locked
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Exam $exam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamSessionResponse> $responses
 * @property-read int|null $responses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamSessionResult> $results
 * @property-read int|null $results_count
 * @property-read \App\Models\User $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereAttemptNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereDurationLimitSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereTScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereTimeUsedSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSession whereUserAgent($value)
 * @mixin \Eloquent
 */
class OnlineExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'status',
        'started_at',
        'submitted_at',
        'duration_limit_seconds',
        'time_used_seconds',
        't_score',
        'percent',
        'ip_address',
        'user_agent',
        'attempt_number',
        'is_locked',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'duration_limit_seconds' => 'integer',
        'time_used_seconds' => 'integer',
        't_score' => 'decimal:2',
        'percent' => 'decimal:2',
        'attempt_number' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResponse::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResult::class);
    }
}
