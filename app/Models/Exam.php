<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'lesson_id',
        'min_passing_score',
        'max_score',
        'delivery_mode',
        'exam_category_id',
        'created_by',
    ];

    protected $casts = [
        'min_passing_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'delivery_mode' => 'string',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inPersonExamDetail(): HasOne
    {
        return $this->hasOne(InPersonExamDetail::class);
    }

    public function onlineExamDetail(): HasOne
    {
        return $this->hasOne(OnlineExamDetail::class);
    }

    public function answerKeys(): HasMany
    {
        return $this->hasMany(OnlineExamAnswerKey::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_classes', 'exam_id', 'class_id');
    }

    public function academicLevels(): BelongsToMany
    {
        return $this->belongsToMany(AcademicLevel::class, 'exam_academic_levels');
    }

    public function inPersonResults(): HasManyThrough
    {
        return $this->hasManyThrough(
            InPersonExamResult::class,
            InPersonExamDetail::class,
            'exam_id',
            'in_person_exam_id',
            'id',
            'id'
        );
    }

    public function grades()
    {
        return $this->inPersonResults();
    }

    public function onlineExamSessions(): HasMany
    {
        return $this->hasMany(OnlineExamSession::class);
    }

    public function onlineExamSessionResponses(): HasMany
    {
        return $this->hasMany(OnlineExamSessionResponse::class);
    }

    public function isOnline(): bool
    {
        return $this->delivery_mode === 'online';
    }

    public function isInPerson(): bool
    {
        return $this->delivery_mode === 'in_person';
    }
}
