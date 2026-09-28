<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exam_category_id
 * @property int $term_id
 * @property int|null $max_occurrences
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ExamCategory $examCategory
 * @property-read \App\Models\AcademicTerm $term
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit whereExamCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit whereMaxOccurrences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit whereTermId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamCategoryTermLimit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ExamCategoryTermLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_category_id',
        'term_id',
        'max_occurrences',
    ];

    protected $casts = [
        'max_occurrences' => 'integer',
    ];

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /**
     * آیا برگزاری غیرممکن است؟ (max_occurrences === 0)
     */
    public function isProhibited(): bool
    {
        return $this->max_occurrences === 0;
    }

    /**
     * آیا تعداد برگزاری نامحدود است؟
     */
    public function isUnlimited(): bool
    {
        return is_null($this->max_occurrences);
    }
}
