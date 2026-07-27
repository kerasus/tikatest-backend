<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\School;
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
                'school_code' => 'SCH-001',
            ],
            [
                'firstname' => 'معلم',
                'lastname' => 'کریمی',
                'username' => 'karimi',
                'password' => Hash::make('password'),
                'email' => 'karimi@example.com',
                'mobile' => '09351234574',
                'employee_code' => 'TCH-002',
                'school_code' => 'SCH-001',
            ],
        ];

        foreach ($teachers as $teacherData) {
            $schoolCode = $teacherData['school_code'];
            unset($teacherData['school_code']);

            $school = School::where('code', $schoolCode)->first();
            if ($school) {
                $teacherData['school_id'] = $school->id;
            }

            $teacher = User::firstOrCreate(
                ['username' => $teacherData['username']],
                $teacherData
            );

            $teacher->syncRoles([UserRoleType::Teacher->value]);
        }
    }
}
