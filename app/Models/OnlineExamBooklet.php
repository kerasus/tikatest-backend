<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $online_exam_id
 * @property int|null $lesson_id
 * @property string $title
 * @property int $from_question
 * @property int $to_question
 * @property array<array-key, mixed>|null $booklet_scores
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \App\Models\OnlineExamDetail $onlineExamDetail
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereBookletScores($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereFromQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereOnlineExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereToQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamBooklet whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OnlineExamBooklet extends Model
{
    use HasFactory;

    protected $fillable = [
        'online_exam_id',
        'lesson_id',
        'title',
        'from_question',
        'to_question',
        'booklet_scores',
    ];

    protected $casts = [
        'from_question' => 'integer',
        'to_question' => 'integer',
        'booklet_scores' => 'array',
    ];

    public function onlineExamDetail(): BelongsTo
    {
        return $this->belongsTo(OnlineExamDetail::class, 'online_exam_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
