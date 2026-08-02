<?php

namespace Database\Seeders;

use App\Models\ExamCategory;
use Illuminate\Database\Seeder;

class ExamCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['title' => 'آزمون کلاسی', 'term_number' => null, 'sort_order' => 1, 'is_system' => true],
            ['title' => 'آزمون ماهانه', 'term_number' => null, 'sort_order' => 2, 'is_system' => true],
            ['title' => 'میان ترم اول', 'term_number' => 1, 'sort_order' => 3, 'is_system' => true],
            ['title' => 'مستمر اول', 'term_number' => 1, 'sort_order' => 4, 'is_system' => true],
            ['title' => 'پایان ترم اول', 'term_number' => 1, 'sort_order' => 5, 'is_system' => true],
            ['title' => 'میان ترم دوم', 'term_number' => 2, 'sort_order' => 6, 'is_system' => true],
            ['title' => 'مستمر دوم', 'term_number' => 2, 'sort_order' => 7, 'is_system' => true],
            ['title' => 'پایان ترم دوم', 'term_number' => 2, 'sort_order' => 8, 'is_system' => true],
            ['title' => 'سایر', 'term_number' => null, 'sort_order' => 9, 'is_system' => false],
        ];

        foreach ($categories as $category) {
            ExamCategory::firstOrCreate([
                'school_id' => null,
                'title' => $category['title'],
                'term_number' => $category['term_number'],
            ], [
                'sort_order' => $category['sort_order'],
                'is_system' => $category['is_system'],
            ]);
        }
    }
}
