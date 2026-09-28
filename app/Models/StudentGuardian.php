<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $student_profile_id
 * @property string $relationship_type
 * @property string|null $job
 * @property bool $is_primary_contact
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\StudentProfile|null $studentProfile
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereIsPrimaryContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereJob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereRelationshipType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereStudentProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentGuardian whereUserId($value)
 * @mixin \Eloquent
 */
class StudentGuardian extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_profile_id',
        'relationship_type',
        'job',
        'is_primary_contact',
    ];

    protected $casts = [
        'is_primary_contact' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
