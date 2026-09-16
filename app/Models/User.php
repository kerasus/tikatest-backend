<?php

namespace App\Models;

use App\Enums\UserRoleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'username',
        'mobile',
        'password',
        'mobile_verification_code',
        'national_id',
        'birth_date',
        'address',
        'description',
        'picture',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mobile_verification_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'birth_date' => 'date',
        'password' => 'hashed',
    ];

    protected $appends = ['roles_list', 'permissions_list'];

    protected function picture(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (! $value) {
                    return null;
                }

                // اگر لینک مستقیم خارجی بود دست نزن
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }

                // تولید خودکار آدرس استاندارد بر اساس دیسک پیش‌فرض (public disk)
                return Storage::disk('public')->url($value);

                // یا اگر صرفاً پیشوند نسبی مثل /storage/ می‌خواهی:
                // return asset('storage/' . ltrim($value, '/'));
            }
        );
    }

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
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function termEnrollments(): HasMany
    {
        return $this->hasMany(TermEnrollment::class, 'user_id');
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function guardianRecords(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'user_id');
    }

    public function inPersonExamResults(): HasMany
    {
        return $this->hasMany(InPersonExamResult::class, 'user_id');
    }

    public function examsCreated(): HasMany
    {
        return $this->hasMany(Exam::class, 'created_by');
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'student_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(MessageOwner::class, 'user_id');
    }

    public function homeworkCreated(): HasMany
    {
        return $this->hasMany(Homework::class, 'created_by');
    }

    public function disciplinaryRecords(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'student_id');
    }

    public function disciplinaryRecorded(): HasMany
    {
        return $this->hasMany(DisciplinaryRecord::class, 'recorded_by');
    }

    public function homeworkGraded(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'graded_by');
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_user')
            ->withPivot(['id', 'personnel_code', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function scopeNonStudent(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', function (Builder $q) {
            $q->where('name', UserRoleType::Student->value);
        });
    }
}
