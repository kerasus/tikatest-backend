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
                'name' => 'متوسطه دوره دوم',
            ],
            [
                'name' => 'ریاضی',
            ],
            [
                'name' => 'ادبیات فارسی',
            ],
            [
                'name' => 'زبان انگلیسی',
            ],
            [
                'name' => 'علوم اجتماعی',
            ],
            [
                'name' => 'معلومات',
            ],
            [
                'name' => 'هنر',
            ],
            [
                'name' => 'ورزش',
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
