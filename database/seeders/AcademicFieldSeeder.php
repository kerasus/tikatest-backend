<?php

namespace Database\Seeders;

use App\Models\AcademicField;
use App\Models\School;
use Illuminate\Database\Seeder;

class AcademicFieldSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        $fields = [
            [
                'name' => 'ابتدایی',
            ],
            [
                'name' => 'متوسطه دوره اول',
            ],
            [
                'name' => 'متوسطه دوره دوم - ریاضی فیزیک',
            ],
            [
                'name' => 'متوسطه دوره دوم - علوم تجربی',
            ],
            [
                'name' => 'متوسطه دوره دوم - علوم انسانی',
            ],
            [
                'name' => 'متوسطه دوره دوم - علوم و معارف اسلامی',
            ],
            [
                'name' => 'متوسطه دوره دوم - هنر',
            ],
        ];

        foreach ($schools as $school) {
            foreach ($fields as $index => $field) {
                AcademicField::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'name' => $field['name'],
                    ],
                    [
                        'school_id' => $school->id,
                        'name' => $field['name'],
                    ]
                );
            }
        }
    }
}
