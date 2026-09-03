<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
