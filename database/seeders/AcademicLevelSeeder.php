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
            'ابتدایی' => ['پایه اول', 'پایه دوم', 'پایه سوم', 'پایه چهارم', 'پایه پنجم', 'پایه ششم'],
//            'متوسطه دوره اول' => ['پایه هفتم', 'پایه هشتم', 'پایه نهم'],
//            'متوسطه دوره دوم - ریاضی فیزیک' => ['پایه دهم', 'پایه یازدهم', 'پایه دوازدهم'],
//            'متوسطه دوره دوم - علوم تجربی' => ['پایه دهم', 'پایه یازدهم', 'پایه دوازدهم'],
//            'متوسطه دوره دوم - علوم انسانی' => ['پایه دهم', 'پایه یازدهم', 'پایه دوازدهم'],
//            'متوسطه دوره دوم - علوم و معارف اسلامی' => ['پایه دهم', 'پایه یازدهم', 'پایه دوازدهم'],
//            'متوسطه دوره دوم - هنر' => ['پایه دهم', 'پایه یازدهم', 'پایه دوازدهم'],
        ];

        foreach ($schools as $school) {
            $fields = AcademicField::where('school_id', $school->id)->get();

            foreach ($fields as $field) {
                $levelNames = $levelsByField[$field->name] ?? ['پایه اول', 'پایه دوم', 'پایه سوم'];

                foreach ($levelNames as $levelName) {
                    AcademicLevel::firstOrCreate(
                        [
                            'field_id' => $field->id,
                            'name' => $levelName,
                        ],
                        [
                            'field_id' => $field->id,
                            'name' => $levelName,
                        ]
                    );
                }
            }
        }
    }
}
