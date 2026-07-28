<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    public function run(): void
    {
        $managers = [
            [
                'first_name' => 'مدیر',
                'last_name' => 'علوی',
                'username' => 'alavi',
                'password' => Hash::make('password'),
                'email' => 'alavi@example.com',
                'mobile' => '09351234575',
                'employee_code' => 'MGR-001',
                'school_code' => 'SCH-001',
            ],
            [
                'first_name' => 'رئیس',
                'last_name' => 'حسینی',
                'username' => 'hosseini',
                'password' => Hash::make('password'),
                'email' => 'hosseini@example.com',
                'mobile' => '09351234576',
                'employee_code' => 'MGR-002',
                'school_code' => 'SCH-002',
            ],
        ];

        foreach ($managers as $managerData) {
            $schoolCode = $managerData['school_code'];
            unset($managerData['school_code']);

            $school = School::where('code', $schoolCode)->first();
            if ($school) {
                $managerData['school_id'] = $school->id;
            }

            $manager = User::firstOrCreate(
                ['username' => $managerData['username']],
                $managerData
            );

            $manager->syncRoles([UserRoleType::Manager->value]);
        }
    }
}
