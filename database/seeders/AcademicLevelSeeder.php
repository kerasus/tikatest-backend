<?php

namespace Database\Seeders;

use App\Models\AcademicField;
use App\Models\AcademicLevel;
use App\Models\School;
use Illuminate\Database\Seeder;

class AcademicLevelSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        $levelsByField = [
            'علوم تجربی' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'ریاضی' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'ادبیات فارسی' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'زبان انگلیسی' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'علوم اجتماعی' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'معلومات' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'هنر' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
            'ورزش' => ['هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'],
        ];

        foreach ($schools as $school) {
            $fields = AcademicField::where('school_id', $school->id)->get();

            foreach ($fields as $field) {
                $levelNames = $levelsByField[$field->name] ?? ['پایه اول', 'پایه دوم', 'پایه سوم'];

                foreach ($levelNames as $levelName) {
                    AcademicLevel::firstOrCreate(
                        [
                            'school_id' => $school->id,
                            'field_id' => $field->id,
                            'name' => $levelName,
                        ],
                        [
                            'school_id' => $school->id,
                            'field_id' => $field->id,
                            'name' => $levelName,
                        ]
                    );
                }
            }
        }
    }
}
