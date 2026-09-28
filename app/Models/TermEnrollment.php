<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $class_id
 * @property int $school_id
 * @property int $term_id
 * @property \Illuminate\Support\Carbon|null $enrolled_at
 * @property \Illuminate\Support\Carbon|null $left_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\School|null $school
 * @property-read \App\Models\SchoolClass|null $schoolClass
 * @property-read \App\Models\AcademicTerm $term
 * @property-read \App\Models\User $user
 * @method static Builder<static>|TermEnrollment active()
 * @method static Builder<static>|TermEnrollment newModelQuery()
 * @method static Builder<static>|TermEnrollment newQuery()
 * @method static Builder<static>|TermEnrollment onlyTrashed()
 * @method static Builder<static>|TermEnrollment query()
 * @method static Builder<static>|TermEnrollment whereClassId($value)
 * @method static Builder<static>|TermEnrollment whereCreatedAt($value)
 * @method static Builder<static>|TermEnrollment whereDeletedAt($value)
 * @method static Builder<static>|TermEnrollment whereEnrolledAt($value)
 * @method static Builder<static>|TermEnrollment whereId($value)
 * @method static Builder<static>|TermEnrollment whereLeftAt($value)
 * @method static Builder<static>|TermEnrollment whereSchoolId($value)
 * @method static Builder<static>|TermEnrollment whereTermId($value)
 * @method static Builder<static>|TermEnrollment whereTermIsActive()
 * @method static Builder<static>|TermEnrollment whereUpdatedAt($value)
 * @method static Builder<static>|TermEnrollment whereUserId($value)
 * @method static Builder<static>|TermEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TermEnrollment withoutTrashed()
 * @mixin \Eloquent
 */
class TermEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'term_enrollments';

    protected $fillable = [
        'user_id',
        'class_id',
        'school_id',
        'term_id',
        'enrolled_at',
        'left_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function isActive(): bool
    {
        $hasStarted = is_null($this->enrolled_at) || $this->enrolled_at->isPast();
        $hasNotLeft = is_null($this->left_at) || $this->left_at->isFuture();

        return $hasStarted && $hasNotLeft;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('enrolled_at')
                ->orWhere('enrolled_at', '<=', now());
        })->where(function (Builder $q) {
            $q->whereNull('left_at')
                ->orWhere('left_at', '>', now());
        });
    }

    public function scopeWhereTermIsActive(Builder $query): Builder
    {
        return $query->whereHas('term', function (Builder $q) {
            $q->where('is_active', true)
                ->where(function (Builder $dateQuery) {
                    $dateQuery->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function (Builder $dateQuery) {
                    $dateQuery->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', now());
                });
        });
    }
}
