<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $homework_id
 * @property array<array-key, mixed>|null $content
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Homework|null $homework
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereHomeworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HomeworkAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }
}
