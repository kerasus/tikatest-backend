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
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'classes.view',
            'classes.create',
            'classes.update',
            'classes.delete',
            'lessons.view',
            'lessons.create',
            'lessons.update',
            'lessons.delete',
            'exams.view',
            'exams.create',
            'exams.update',
            'exams.delete',
            'grades.view',
            'grades.create',
            'grades.update',
            'grades.delete',
            'quizzes.view',
            'quizzes.create',
            'quizzes.update',
            'quizzes.delete',
            'quizzes.manage',
            'quiz_attempts.view',
            'quiz_attempts.create',
            'quiz_attempts.update',
            'quiz_attempts.delete',
            'quiz_questions.view',
            'quiz_questions.create',
            'quiz_questions.update',
            'quiz_questions.delete',
            'quiz_question_options.view',
            'quiz_question_options.create',
            'quiz_question_options.update',
            'quiz_question_options.delete',
            'homework.view',
            'homework.create',
            'homework.update',
            'homework.delete',
            'disciplinary_cases.view',
            'disciplinary_cases.create',
            'disciplinary_cases.update',
            'disciplinary_cases.delete',
            'disciplinary_records.view',
            'disciplinary_records.create',
            'disciplinary_records.update',
            'disciplinary_records.delete',
            'messages.view',
            'messages.create',
            'messages.update',
            'messages.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate(UserRoleType::Admin->value, 'web');
        $manager = Role::findOrCreate(UserRoleType::Manager->value, 'web');
        $user = Role::findOrCreate(UserRoleType::User->value, 'web');
        $teacher = Role::findOrCreate('teacher', 'web');
        $student = Role::findOrCreate('student', 'web');

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
            'students.view',
            'students.create',
            'students.update',
            'students.delete',
            'classes.view',
            'classes.create',
            'classes.update',
            'classes.delete',
            'lessons.view',
            'lessons.create',
            'lessons.update',
            'lessons.delete',
            'exams.view',
            'exams.create',
            'exams.update',
            'exams.delete',
            'grades.view',
            'grades.create',
            'grades.update',
            'grades.delete',
            'quizzes.view',
            'quizzes.create',
            'quizzes.update',
            'quizzes.delete',
            'quizzes.manage',
            'quiz_attempts.view',
            'quiz_attempts.create',
            'quiz_attempts.update',
            'quiz_attempts.delete',
            'quiz_questions.view',
            'quiz_questions.create',
            'quiz_questions.update',
            'quiz_questions.delete',
            'homework.view',
            'homework.create',
            'homework.update',
            'homework.delete',
            'disciplinary_cases.view',
            'disciplinary_cases.create',
            'disciplinary_cases.update',
            'disciplinary_cases.delete',
            'disciplinary_records.view',
            'disciplinary_records.create',
            'disciplinary_records.update',
            'disciplinary_records.delete',
            'messages.view',
            'messages.create',
            'messages.update',
            'messages.delete',
        ]);

        $teacher->syncPermissions([
            'students.view',
            'classes.view',
            'lessons.view',
            'exams.view',
            'exams.create',
            'grades.view',
            'grades.create',
            'grades.update',
            'grades.delete',
            'quizzes.view',
            'quizzes.create',
            'quizzes.update',
            'quizzes.delete',
            'quizzes.manage',
            'quiz_attempts.view',
            'quiz_attempts.create',
            'quiz_attempts.update',
            'quiz_attempts.delete',
            'quiz_questions.view',
            'quiz_questions.create',
            'quiz_questions.update',
            'quiz_questions.delete',
            'homework.view',
            'homework.create',
            'homework.update',
            'homework.delete',
            'disciplinary_cases.view',
            'disciplinary_cases.create',
            'disciplinary_cases.update',
            'disciplinary_cases.delete',
            'disciplinary_records.view',
            'disciplinary_records.create',
            'disciplinary_records.update',
            'disciplinary_records.delete',
            'messages.view',
            'messages.create',
            'messages.update',
            'messages.delete',
        ]);

        $student->syncPermissions([
            'students.view',
            'grades.view',
            'quizzes.view',
            'quiz_attempts.view',
            'homework.view',
            'disciplinary_cases.view',
            'disciplinary_records.view',
            'messages.view',
            'messages.create',
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
