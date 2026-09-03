<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
