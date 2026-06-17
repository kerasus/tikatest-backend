<?php

namespace Database\Seeders;

use App\Enums\UserRoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage-roles',
            'tags.view',
            'tags.create',
            'tags.update',
            'tags.delete',
            'places.view',
            'places.create',
            'places.update',
            'places.delete',
            'places.import',
            'places.manage-tags',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate(UserRoleType::Admin->value, 'web');
        $manager = Role::findOrCreate(UserRoleType::Manager->value, 'web');
        $user = Role::findOrCreate(UserRoleType::User->value, 'web');

        $admin->syncPermissions($permissions);

        $manager->syncPermissions([
            'users.view',
            'tags.view',
            'tags.create',
            'tags.update',
            'tags.delete',
            'places.view',
            'places.create',
            'places.update',
            'places.delete',
            'places.import',
            'places.manage-tags',
        ]);

        $user->syncPermissions([
            'tags.view',
            'places.view',
            'places.manage-tags',
        ]);

        $adminUser = User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'firstname' => 'Admin',
                'lastname' => 'User',
                'mobile' => '09000000000',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
            ]
        );

        $adminUser->syncRoles([UserRoleType::Admin->value]);
    }
}
