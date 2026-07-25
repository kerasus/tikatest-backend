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
        $schools = School::all();

        $classNames = [
            'الف',
            'ب',
            'ج',
            'د',
        ];

        foreach ($schools as $school) {
            $levels = AcademicLevel::where('school_id', $school->id)->get();

            foreach ($levels as $level) {
                foreach ($classNames as $className) {
                    SchoolClass::firstOrCreate(
                        [
                            'school_id' => $school->id,
                            'level_id' => $level->id,
                            'name' => $className,
                        ],
                        [
                            'school_id' => $school->id,
                            'level_id' => $level->id,
                            'name' => $className,
                        ]
                    );
                }
            }
        }
    }
}
