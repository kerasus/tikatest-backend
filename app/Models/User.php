<?php

namespace App\Models;

use App\Enums\UserRoleType;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $username
 * @property string|null $mobile
 * @property string|null $email
 * @property string|null $address
 * @property string|null $national_id
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $mobile_verified_at
 * @property string|null $mobile_verification_code
 * @property string|null $description
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $picture
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisciplinaryRecord> $disciplinaryRecorded
 * @property-read int|null $disciplinary_recorded_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisciplinaryRecord> $disciplinaryRecords
 * @property-read int|null $disciplinary_records_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $examsCreated
 * @property-read int|null $exams_created_count
 * @property-read string $full_name
 * @property-read array $permissions_list
 * @property-read array $roles_list
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StudentGuardian> $guardianRecords
 * @property-read int|null $guardian_records_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Homework> $homeworkCreated
 * @property-read int|null $homework_created_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HomeworkSubmission> $homeworkGraded
 * @property-read int|null $homework_graded_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HomeworkSubmission> $homeworkSubmissions
 * @property-read int|null $homework_submissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InPersonExamResult> $inPersonExamResults
 * @property-read int|null $in_person_exam_results_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MessageOwner> $receivedMessages
 * @property-read int|null $received_messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\School> $schools
 * @property-read int|null $schools_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $sentMessages
 * @property-read int|null $sent_messages_count
 * @property-read \App\Models\StudentProfile|null $studentProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TermEnrollment> $termEnrollments
 * @property-read int|null $term_enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User nonStudent()
 * @method static Builder<static>|User permission($permissions, bool $without = false)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|User team($teams, bool $without = false)
 * @method static Builder<static>|User whereAddress($value)
 * @method static Builder<static>|User whereBirthDate($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDescription($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFirstName($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereLastName($value)
 * @method static Builder<static>|User whereMobile($value)
 * @method static Builder<static>|User whereMobileVerificationCode($value)
 * @method static Builder<static>|User whereMobileVerifiedAt($value)
 * @method static Builder<static>|User whereNationalId($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePicture($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User whereUsername($value)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static Builder<static>|User withoutTeam($teams)
 * @mixin \Eloquent
 */
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
