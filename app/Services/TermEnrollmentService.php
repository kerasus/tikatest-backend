<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\SchoolClass;
use App\Models\TermEnrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TermEnrollmentService
{
    public function enrollStudent(
        int $classId,
        int $userId,
        int $termId,
        array $attributes = []
    ): TermEnrollment {
        $schoolClass = SchoolClass::with('academicLevel.academicField.school')->findOrFail($classId);
        $user = User::findOrFail($userId);
        $term = AcademicTerm::findOrFail($termId);
        $schoolId = $schoolClass->school?->id;

        if (! $schoolId || $schoolId !== $term->school_id) {
            throw ValidationException::withMessages([
                'term_id' => 'ترم باید متعلق به مدرسه کلاس باشد.',
            ]);
        }

        return TermEnrollment::create([
            'user_id' => $user->id,
            'class_id' => $schoolClass->id,
            'school_id' => $schoolId,
            'term_id' => $term->id,
            'enrolled_at' => $attributes['enrolled_at'] ?? now(),
            'left_at' => $attributes['left_at'] ?? null,
        ]);
    }

    public function removeEnrollment(int $termEnrollmentId): void
    {
        $enrollment = TermEnrollment::with('user')->findOrFail($termEnrollmentId);
        $userId = $enrollment->user_id;

        $enrollment->delete();

        if (! TermEnrollment::where('user_id', $userId)->exists()) {
            $enrollment->user?->delete();
        }
    }
}
