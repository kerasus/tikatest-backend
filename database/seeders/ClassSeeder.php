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

        foreach ($levels as $level) {
            $school = $level->academicField->school ?? null;

            if ($school) {
                SchoolClass::firstOrCreate(
                    [
                        'level_id' => $level->id,
                        'name' => 'کلاس الف',
                    ],
                    [
                        'level_id' => $level->id,
                        'name' => 'کلاس ب',
                    ]
                );

                SchoolClass::firstOrCreate(
                    [
                        'level_id' => $level->id,
                        'name' => 'کلاس ب',
                    ],
                    [
                        'level_id' => $level->id,
                        'name' => 'کلاس ب',
                    ]
                );
            }
        }
    }
}
