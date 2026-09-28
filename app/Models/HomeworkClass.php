<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $homework_id
 * @property int $class_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Homework|null $homework
 * @property-read \App\Models\SchoolClass|null $schoolClass
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass whereClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass whereHomeworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkClass whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HomeworkClass extends Model
{
    protected $fillable = [
        'homework_id',
        'class_id',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
