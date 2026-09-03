<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'academic_year',
        'season',
        'period',
        'starts_at',
        'ends_at',
        'is_active',
        'parent_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'period' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parentTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AcademicTerm::class, 'parent_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'term_id');
    }

    public function termLimits(): HasMany
    {
        return $this->hasMany(ExamCategoryTermLimit::class, 'term_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TermEnrollment::class, 'term_id');
    }

    public function isSchoolYear(): bool
    {
        return $this->type === 'school_year';
    }

    public function isSeasonal(): bool
    {
        return $this->type === 'seasonal';
    }

    public function isSubTerm(): bool
    {
        return $this->type === 'sub_term';
    }
}
