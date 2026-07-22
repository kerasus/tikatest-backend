<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            [
                'firstname' => 'علی',
                'lastname' => 'اسماعیلی',
                'username' => 'ali',
                'password' => Hash::make('password'),
                'mobile' => '09351234567',
                'student_phone' => '09351234567',
                'melli_code' => '0014256789',
                'student_code' => 'STU-001',
                'birth_date' => '2005-03-15',
                'student_email' => 'ali@example.com',
                'address' => 'تهران، خیابان انقلاب',
                'father_name' => 'محمد',
                'father_phone' => '09351234568',
                'father_email' => 'father@example.com',
                'mother_name' => 'فاطمه',
                'mother_phone' => '09351234569',
                'mother_email' => 'mother@example.com',
                'schools' => ['SCH-001'],
            ],
            [
                'firstname' => 'مریم',
                'lastname' => 'محمدی',
                'username' => 'maryam',
                'password' => Hash::make('password'),
                'mobile' => '09351234570',
                'student_phone' => '09351234570',
                'melli_code' => '0014256790',
                'student_code' => 'STU-002',
                'birth_date' => '2006-05-20',
                'student_email' => 'maryam@example.com',
                'address' => 'تهران، خیابان ولیعصر',
                'father_name' => 'رضا',
                'father_phone' => '09351234571',
                'father_email' => 'father.m@example.com',
                'mother_name' => 'زهرا',
                'mother_phone' => '09351234572',
                'mother_email' => 'mother.m@example.com',
                'schools' => ['SCH-001', 'SCH-002'],
            ],
        ];

        foreach ($students as $studentData) {
            $schools = $studentData['schools'];
            unset($studentData['schools']);

            $student = User::firstOrCreate(
                ['username' => $studentData['username']],
                $studentData
            );

            $student->syncRoles([UserRoleType::Student->value]);

            if (!empty($schools)) {
                $schoolModels = \App\Models\School::whereIn('code', $schools)->get();
                foreach ($schoolModels as $school) {
                    $student->schools()->attach($school->id, ['role' => UserRoleType::Student->value]);
                }
            }
        }
    }
}
