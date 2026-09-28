<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $online_exam_session_id
 * @property int $exam_id
 * @property int $student_id
 * @property int|null $online_exam_booklet_id
 * @property int|null $lesson_id
 * @property string|null $lesson_title
 * @property string $scope
 * @property numeric $raw_score
 * @property numeric $max_score
 * @property numeric|null $scaled_score
 * @property numeric|null $percent
 * @property int $question_count
 * @property int $answered_count
 * @property int $correct_count
 * @property int $wrong_count
 * @property int $unanswered_count
 * @property numeric|null $z_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Exam $exam
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \App\Models\OnlineExamBooklet|null $onlineExamBooklet
 * @property-read \App\Models\OnlineExamSession $onlineExamSession
 * @property-read \App\Models\User $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereAnsweredCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereCorrectCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereLessonTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereMaxScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereOnlineExamBookletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereOnlineExamSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereQuestionCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereRawScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereScaledScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereUnansweredCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereWrongCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResult whereZScore($value)
 * @mixin \Eloquent
 */
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
