<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $homework_id
 * @property int $academic_level_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicLevel|null $academicLevel
 * @property-read \App\Models\Homework|null $homework
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel whereAcademicLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel whereHomeworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAcademicLevel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HomeworkAcademicLevel extends Model
{
    protected $fillable = [
        'homework_id',
        'academic_level_id',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }
}
