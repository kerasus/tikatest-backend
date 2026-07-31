<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentGuardian;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $schools = School::all();

        $firstNames = [
            'علی', 'مریم', 'محمد', 'فاطمه', 'رضا', 'زهرا', 'حسن', 'امیر', 'سارا', 'اکبر',
            'محسن', 'نجمه', 'حسین', 'فرزانه', 'پویا', 'مهرناز', 'سعید', 'لیلا', 'امیرحسین', 'مرضیه',
        ];

        $lastNames = [
            'احمدی', 'محمدی', 'رضایی', 'حسینی', 'عباسی', 'کریمی', 'موسوی', 'نجفی', 'قاسمی', 'جعفری',
            'سلیمانی', 'میرزایی', 'نوری', 'حیدری', 'منصوری', 'باقری', 'علوی', 'اصغری', 'کاظمی', 'فرهادی',
        ];

        $studentsPerClass = 5;
        $globalIndex = 0;

        foreach ($schools as $school) {
            $classes = SchoolClass::whereHas('academicLevel.academicField', function ($query) use ($school) {
                $query->where('school_id', $school->id);
            })->get();

            foreach ($classes as $class) {
                for ($i = 1; $i <= $studentsPerClass; $i++) {
                    $globalIndex++;
                    $firstName = $firstNames[($i - 1) % count($firstNames)];
                    $lastName = $lastNames[($i - 1) % count($lastNames)];
                    $melliCode = str_pad((string) ($globalIndex * 137 + 1000000000), 10, '0', STR_PAD_LEFT);
                    $username = $melliCode;
                    $mobile = '0935'.str_pad((string) ($globalIndex * 111111 + 1000000), 7, '0', STR_PAD_LEFT);
                    $studentCode = sprintf(
                        '%s-CLS-%d-STU-%03d',
                        strtoupper($school->code),
                        $class->id,
                        $i,
                    );
                    $birthYear = rand(2000, 2010);
                    $birthMonth = rand(1, 12);
                    $birthDay = rand(1, 28);
                    $birthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);

                    $user = User::firstOrCreate(
                        ['username' => $username],
                        [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'password' => Hash::make('password'),
                            'mobile' => $mobile,
                            'national_id' => $melliCode,
                            'email' => $username.'@example.com',
                            'birth_date' => $birthDate,
                            'address' => 'تهران',
                        ]
                    );

                    $fatherUser = User::updateOrCreate(
                        ['username' => "father-{$username}"],
                        [
                            'first_name' => $firstNames[$globalIndex % count($firstNames)],
                            'last_name' => $lastName,
                            'password' => Hash::make('password'),
                            'mobile' => '0912'.str_pad((string) (2000000 + $globalIndex), 7, '0', STR_PAD_LEFT),
                            'national_id' => (string) (2000000000 + $globalIndex),
                            'email' => "father-{$username}@example.com",
                            'address' => 'تهران',
                        ],
                    );
                    $fatherUser->syncRoles([UserRoleType::Guardian->value]);

                    $motherUser = User::updateOrCreate(
                        ['username' => "mother-{$username}"],
                        [
                            'first_name' => $firstNames[($globalIndex + 1) % count($firstNames)],
                            'last_name' => $lastName,
                            'password' => Hash::make('password'),
                            'mobile' => '0919'.str_pad((string) (3000000 + $globalIndex), 7, '0', STR_PAD_LEFT),
                            'national_id' => (string) (3000000000 + $globalIndex),
                            'email' => "mother-{$username}@example.com",
                            'address' => 'تهران',
                        ],
                    );
                    $motherUser->syncRoles([UserRoleType::Guardian->value]);

                    $user->syncRoles([UserRoleType::Student->value]);

                    $studentProfile = StudentProfile::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'code' => $studentCode,
                        ]
                    );

                    StudentGuardian::updateOrCreate(
                        [
                            'student_profile_id' => $studentProfile->id,
                            'relationship_type' => 'father',
                        ],
                        [
                            'user_id' => $fatherUser->id,
                            'job' => 'پدر',
                            'is_primary_contact' => true,
                        ],
                    );

                    StudentGuardian::updateOrCreate(
                        [
                            'student_profile_id' => $studentProfile->id,
                            'relationship_type' => 'mother',
                        ],
                        [
                            'user_id' => $motherUser->id,
                            'job' => 'مادر',
                            'is_primary_contact' => false,
                        ],
                    );

                    UserClass::firstOrCreate([
                        'user_id' => $user->id,
                        'class_id' => $class->id,
                    ]);
                }
            }
        }
    }
}
