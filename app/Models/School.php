<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $phone_number
 * @property string|null $address
 * @property string|null $website
 * @property string|null $logo
 * @property string $type
 * @property string|null $account_url
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AcademicField> $academicFields
 * @property-read int|null $academic_fields_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AcademicLevel> $academicLevels
 * @property-read int|null $academic_levels_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AcademicTerm> $academicTerms
 * @property-read int|null $academic_terms_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SchoolClass> $classes
 * @property-read int|null $classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisciplinaryCase> $disciplinaryCases
 * @property-read int|null $disciplinary_cases_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisciplinaryRecord> $disciplinaryRecords
 * @property-read int|null $disciplinary_records_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamCategory> $examCategories
 * @property-read int|null $exam_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Exam> $exams
 * @property-read int|null $exams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Homework> $homework
 * @property-read int|null $homework_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HomeworkSubmission> $homeworkSubmissions
 * @property-read int|null $homework_submissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $staffMembers
 * @property-read int|null $staff_members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $teachers
 * @property-read int|null $teachers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TermEnrollment> $termEnrollments
 * @property-read int|null $term_enrollments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereAccountUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School withoutTrashed()
 * @mixin \Eloquent
 */
class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'phone_number',
        'address',
        'website',
        'logo',
        'type',
        'account_url',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];



    protected function logo(): Attribute
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

    public function termEnrollments(): HasMany
    {
        return $this->hasMany(TermEnrollment::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function examCategories(): HasMany
    {
        return $this->hasMany(ExamCategory::class);
    }

    public function academicTerms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class);
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_user')
            ->withPivot(['role_in_school', 'personnel_code', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function staffMembers(): BelongsToMany
    {
        return $this->users()->wherePivotIn('role_in_school', ['manager', 'teacher', 'staff']);
    }

    public function teachers(): BelongsToMany
    {
        return $this->users()->wherePivot('role_in_school', 'teacher');
    }
}
