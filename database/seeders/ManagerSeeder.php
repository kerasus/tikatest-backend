<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    public function run(): void
    {
        $managers = [
            [
                'firstname' => 'مدیر',
                'lastname' => 'علوی',
                'username' => 'alavi',
                'password' => Hash::make('password'),
                'email' => 'alavi@example.com',
                'mobile' => '09351234575',
                'employee_code' => 'MGR-001',
                'schools' => ['SCH-001'],
            ],
            [
                'firstname' => 'رئیس',
                'lastname' => 'حسینی',
                'username' => 'hosseini',
                'password' => Hash::make('password'),
                'email' => 'hosseini@example.com',
                'mobile' => '09351234576',
                'employee_code' => 'MGR-002',
                'schools' => ['SCH-002'],
            ],
        ];

        foreach ($managers as $managerData) {
            $schools = $managerData['schools'];
            unset($managerData['schools']);

            $manager = User::firstOrCreate(
                ['username' => $managerData['username']],
                $managerData
            );

            $manager->syncRoles([UserRoleType::Manager->value]);

            if (!empty($schools)) {
                $schoolModels = \App\Models\School::whereIn('code', $schools)->get();
                foreach ($schoolModels as $school) {
                    $manager->schools()->attach($school->id, ['role' => UserRoleType::Manager->value]);
                }
            }
        }
    }
}
