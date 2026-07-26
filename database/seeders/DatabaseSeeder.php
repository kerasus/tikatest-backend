<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SchoolSeeder::class,
            AcademicFieldSeeder::class,
            AcademicLevelSeeder::class,
            LessonSeeder::class,
            ClassSeeder::class,
            ManagerSeeder::class,
            TeacherSeeder::class,
            StudentSeeder::class,
        ]);
    }
}
