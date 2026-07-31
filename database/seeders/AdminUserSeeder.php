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
                'first_name' => 'Admin',
                'last_name' => 'User',
                'mobile' => '09000000000',
                'password' => Hash::make('password'),
            ]
        );

        $adminUser->syncRoles([UserRoleType::Admin->value]);
    }
}
