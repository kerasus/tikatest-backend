<?php

namespace Database\Seeders;

use App\Models\AcademicLevel;
use App\Models\Lesson;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $levels = AcademicLevel::all();
        $genericLessons = ['درس اول', 'درس دوم', 'درس سوم', 'درس چهارم', 'درس پنجم'];

        foreach ($levels as $level) {
            foreach ($genericLessons as $index => $lessonName) {
                Lesson::firstOrCreate(
                    [
                        'level_id' => $level->id,
                        'name' => $lessonName,
                    ],
                    [
                        'level_id' => $level->id,
                        'name' => $lessonName,
                        'order' => $index + 1,
                    ]
                );
            }
        }
    }
}
