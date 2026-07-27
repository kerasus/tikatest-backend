<?php

namespace Database\Seeders;

use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $levels = AcademicLevel::all();

        foreach ($levels as $level) {
            $school = $level->school;

            if ($school) {
                SchoolClass::firstOrCreate(
                    [
                        'level_id' => $level->id,
                        'name' => 'کلاس ' . $level->name,
                    ],
                    [
                        'level_id' => $level->id,
                        'name' => 'کلاس ' . $level->name,
                    ]
                );
            }
        }
    }
}
