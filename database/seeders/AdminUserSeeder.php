<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'firstname' => 'Admin',
                'lastname' => 'User',
                'mobile' => '09000000000',
                'password' => Hash::make('password')
            ]
        );

        $adminUser->syncRoles([UserRoleType::Admin->value]);
    }
}
