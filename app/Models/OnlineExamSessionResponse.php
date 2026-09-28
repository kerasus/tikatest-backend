<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $online_exam_session_id
 * @property int $exam_id
 * @property int $user_id
 * @property int $question_number
 * @property string|null $submitted_option
 * @property string|null $answer_text
 * @property bool|null $is_correct
 * @property numeric|null $marks_obtained
 * @property \Illuminate\Support\Carbon|null $answered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Exam $exam
 * @property-read \App\Models\OnlineExamSession|null $onlineExamSession
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereAnswerText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereMarksObtained($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereOnlineExamSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereQuestionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereSubmittedOption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamSessionResponse whereUserId($value)
 * @mixin \Eloquent
 */
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
