<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'website',
        'logo_url',
        'type',
        'account_url',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function academicFields(): HasMany
    {
        return $this->hasMany(AcademicField::class);
    }

    public function academicLevels(): HasMany
    {
        return $this->hasMany(AcademicLevel::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function studentClassRegistrations(): HasMany
    {
        return $this->hasMany(StudentClassRegistration::class);
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function quizClassAssignments(): HasMany
    {
        return $this->hasMany(QuizClassAssignment::class);
    }

    public function disciplinaryCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class);
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class);
    }

    public function homework(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function preRegistrations(): HasMany
    {
        return $this->hasMany(PreRegistration::class);
    }

}
