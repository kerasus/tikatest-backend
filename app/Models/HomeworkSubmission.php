<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $homework_id
 * @property int $student_id
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $student_seen_at
 * @property \Illuminate\Support\Carbon|null $operator_seen_at
 * @property string|null $feedback
 * @property array<array-key, mixed>|null $content
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Homework|null $homework
 * @property-read \App\Models\User $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereHomeworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereOperatorSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereStudentSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkSubmission whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'student_id',
        'submitted_at',
        'student_seen_at',
        'operator_seen_at',
        'feedback',
        'content',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'student_seen_at' => 'datetime',
        'operator_seen_at' => 'datetime',
        'content' => 'array',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
