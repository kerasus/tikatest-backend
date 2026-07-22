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
                'firstname' => 'استاد',
                'lastname' => 'احمدی',
                'username' => 'ahmadi',
                'password' => Hash::make('password'),
                'email' => 'ahmadi@example.com',
                'mobile' => '09351234573',
                'employee_code' => 'TCH-001',
                'schools' => ['SCH-001'],
            ],
            [
                'firstname' => 'معلم',
                'lastname' => 'کریمی',
                'username' => 'karimi',
                'password' => Hash::make('password'),
                'email' => 'karimi@example.com',
                'mobile' => '09351234574',
                'employee_code' => 'TCH-002',
                'schools' => ['SCH-001', 'SCH-002'],
            ],
        ];

        foreach ($teachers as $teacherData) {
            $schools = $teacherData['schools'];
            unset($teacherData['schools']);

            $teacher = User::firstOrCreate(
                ['username' => $teacherData['username']],
                $teacherData
            );

            $teacher->syncRoles([UserRoleType::Teacher->value]);

            if (!empty($schools)) {
                $schoolModels = \App\Models\School::whereIn('code', $schools)->get();
                foreach ($schoolModels as $school) {
                    $teacher->schools()->attach($school->id, ['role' => UserRoleType::Teacher->value]);
                }
            }
        }
    }
}
