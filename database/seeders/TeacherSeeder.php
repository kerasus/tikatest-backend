<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = [
            [
                'first_name' => 'استاد',
                'last_name' => 'احمدی',
                'username' => 'ahmadi',
                'password' => Hash::make('password'),
                'email' => 'ahmadi@example.com',
                'mobile' => '09351234573',
            ],
            [
                'first_name' => 'معلم',
                'last_name' => 'کریمی',
                'username' => 'karimi',
                'password' => Hash::make('password'),
                'email' => 'karimi@example.com',
                'mobile' => '09351234574',
            ],
        ];

        foreach ($teachers as $teacherData) {
            $teacher = User::firstOrCreate(
                ['username' => $teacherData['username']],
                $teacherData
            );

            $teacher->syncRoles([UserRoleType::Teacher->value]);
        }
    }
}
