<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'level_id',
        'name',
    ];

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'level_id');
    }

    public function userClassRegistrations(): HasMany
    {
        return $this->hasMany(UserClass::class);
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_classes');
    }

    public function homework(): HasMany
    {
        return $this->hasMany(Homework::class);
    }
}
