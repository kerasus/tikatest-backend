<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
