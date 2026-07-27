<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\UserClassRegistration;
use App\Models\User;
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
            $classes = SchoolClass::where('school_id', $school->id)->get();

            foreach ($classes as $class) {
                for ($i = 1; $i <= $studentsPerClass; $i++) {
                    $globalIndex++;
                    $firstName = $firstNames[($i - 1) % count($firstNames)];
                    $lastName = $lastNames[($i - 1) % count($lastNames)];
                    $username = strtolower($school->code) . '_' . strtolower(str_replace(' ', '_', $class->name)) . '_student_' . $i;
                    $mobile = '0935' . str_pad((string) ($globalIndex * 111111 + 1000000), 7, '0', STR_PAD_LEFT);
                    $melliCode = str_pad((string) ($globalIndex * 137 + 1000000000), 10, '0', STR_PAD_LEFT);
                    $studentCode = strtoupper($school->code) . '-' . strtoupper(str_replace(' ', '_', $class->name)) . '-STU-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                    $birthYear = rand(2000, 2010);
                    $birthMonth = rand(1, 12);
                    $birthDay = rand(1, 28);
                    $birthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);

                    $user = User::firstOrCreate(
                        ['username' => $username],
                        [
                            'firstname' => $firstName,
                            'lastname' => $lastName,
                            'password' => Hash::make('password'),
                            'mobile' => $mobile,
                            'student_phone' => $mobile,
                            'melli_code' => $melliCode,
                            'student_code' => $studentCode,
                            'birth_date' => $birthDate,
                            'student_email' => $username . '@example.com',
                            'address' => 'تهران',
                            'father_name' => 'پدر',
                            'father_phone' => $mobile,
                            'father_email' => 'father.' . $username . '@example.com',
                            'mother_name' => 'مادر',
                            'mother_phone' => $mobile,
                            'mother_email' => 'mother.' . $username . '@example.com',
                        ]
                    );

                    $user->syncRoles([UserRoleType::Student->value]);

                    UserClassRegistration::firstOrCreate([
                        'user_id' => $user->id,
                        'class_id' => $class->id,
                    ]);
                }
            }
        }
    }
}
