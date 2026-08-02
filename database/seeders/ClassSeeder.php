<?php

namespace Database\Seeders;

use App\Models\AcademicLevel;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $levels = AcademicLevel::with('academicField.school')->get();

        $classSuffixes = ['الف', 'ب', 'ج'];

        foreach ($levels as $level) {
            $school = $level->academicField->school ?? null;

            if ($school) {
                foreach ($classSuffixes as $suffix) {
                    SchoolClass::firstOrCreate(
                        [
                            'academic_level_id' => $level->id,
                            'name' => 'کلاس ' . $suffix,
                        ]
                    );
                }
            }
        }
    }
}
