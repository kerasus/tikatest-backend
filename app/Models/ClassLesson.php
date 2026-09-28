<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $class_id
 * @property int $lesson_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Lesson|null $lesson
 * @property-read \App\Models\SchoolClass|null $schoolClass
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassLesson whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ClassLesson extends Model
{
    use HasFactory;

    protected $table = 'class_lesson';

    protected $fillable = [
        'class_id',
        'lesson_id',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
