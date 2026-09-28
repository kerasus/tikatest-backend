<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $exam_id
 * @property \Illuminate\Support\Carbon|null $held_at
 * @property bool $is_descriptive
 * @property \Illuminate\Support\Carbon|null $results_visible_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Exam $exam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InPersonExamResult> $results
 * @property-read int|null $results_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereHeldAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereIsDescriptive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereResultsVisibleAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InPersonExamDetail whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class InPersonExamDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'held_at',
        'is_descriptive',
        'results_visible_at',
        'created_by',
    ];

    protected $casts = [
        'held_at' => 'date',
        'is_descriptive' => 'boolean',
        'results_visible_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(InPersonExamResult::class, 'in_person_exam_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
