<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_id
 * @property int $question_number
 * @property int $number_of_choices
 * @property string $correct_option
 * @property numeric $weight
 * @property bool $has_negative_mark
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Exam $exam
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereCorrectOption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereHasNegativeMark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereNumberOfChoices($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereQuestionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamAnswerKey whereWeight($value)
 * @mixin \Eloquent
 */
class OnlineExamAnswerKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'question_number',
        'number_of_choices',
        'correct_option',
        'weight',
        'has_negative_mark',
        'is_active',
    ];

    protected $casts = [
        'question_number' => 'integer',
        'number_of_choices' => 'integer',
        'weight' => 'decimal:2',
        'has_negative_mark' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
