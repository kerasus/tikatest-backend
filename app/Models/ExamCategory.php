<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'title',
        'term_number',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'term_number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'exam_category_id');
    }
}
