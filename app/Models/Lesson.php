<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'level_id',
        'order',
        'coefficient',
        'is_report_card',
    ];

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'level_id');
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function homework(): HasMany
    {
        return $this->hasMany(Homework::class);
    }
}
