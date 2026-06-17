<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'lesson_id',
        'class_id',
        'gregorian_date',
        'persian_date',
        'grade_type',
        'grade_name_for_other_type',
        'is_descriptive',
        'is_report_card',
        'min_grade',
        'created_by',
    ];

    protected $casts = [
        'is_descriptive' => 'boolean',
        'is_report_card' => 'boolean',
        'min_grade' => 'decimal:2',
        'gregorian_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
