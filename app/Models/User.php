<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'username',
        'employee_code',
        'mobile',
        'password',
        'mobile_verification_code',
        'national_id',
        'student_code',
        'birth_date',
        'student_email',
        'student_phone',
        'address',
        'additional_info',
        'xp',
        'picture',
        'school_id',
        'father_name',
        'father_phone',
        'father_email',
        'father_job',
        'father_national_id',
        'father_password',
        'mother_name',
        'mother_last_name',
        'mother_phone',
        'mother_email',
        'mother_job',
        'mother_national_id',
        'mother_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mobile_verification_code',
        'father_password',
        'mother_password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'birth_date' => 'date',
        'password' => 'hashed',
    ];

    protected $appends = ['roles_list', 'permissions_list'];

    public function getRolesListAttribute(): array
    {
        return $this->getRoleNames()->toArray();
    }

    public function getPermissionsListAttribute(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->role($role);
    }

    public function userClassRegistrations(): HasMany
    {
        return $this->hasMany(UserClass::class, 'user_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'student_id');
    }

    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'student_id');
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'student_id');
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'student_id');
    }

    public function homeworkOwners(): HasMany
    {
        return $this->hasMany(HomeworkOwner::class, 'user_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function examSessionsCreated(): HasMany
    {
        return $this->hasMany(ExamSession::class, 'created_by');
    }

    public function homeworkCreated(): HasMany
    {
        return $this->hasMany(Homework::class, 'created_by');
    }

    public function disciplinaryRecorded(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'recorded_by');
    }

    public function homeworkGraded(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'graded_by');
    }

}
