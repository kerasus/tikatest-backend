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
                'first_name' => 'مدیر',
                'last_name' => 'علوی',
                'username' => 'alavi',
                'password' => Hash::make('password'),
                'email' => 'alavi@example.com',
                'mobile' => '09351234575',
            ],
            [
                'first_name' => 'رئیس',
                'last_name' => 'حسینی',
                'username' => 'hosseini',
                'password' => Hash::make('password'),
                'email' => 'hosseini@example.com',
                'mobile' => '09351234576',
            ],
        ];

        foreach ($managers as $managerData) {
            $manager = User::firstOrCreate(
                ['username' => $managerData['username']],
                $managerData
            );

            $manager->syncRoles([UserRoleType::Manager->value]);
        }
    }
}
