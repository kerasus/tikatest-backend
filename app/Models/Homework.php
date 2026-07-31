<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Homework extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'lesson_id',
        'class_id',
        'title',
        'description',
        'attachment',
        'attachment_2',
        'due_date',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
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

    public function owners(): HasMany
    {
        return $this->hasMany(HomeworkOwner::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }
}
