<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Notification\NotificationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendBaleToStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_students_by_class_and_name_before_sending(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $schoolId = DB::table('schools')->insertGetId(['slug' => 'school', 'name' => 'مدرسه']);
        $fieldId = DB::table('academic_fields')->insertGetId(['school_id' => $schoolId, 'name' => 'ریاضی']);
        $levelId = DB::table('academic_levels')->insertGetId(['field_id' => $fieldId, 'name' => 'دهم']);
        $classId = DB::table('classes')->insertGetId(['academic_level_id' => $levelId, 'name' => 'الف']);
        $otherClassId = DB::table('classes')->insertGetId(['academic_level_id' => $levelId, 'name' => 'ب']);
        $termId = DB::table('academic_terms')->insertGetId(['school_id' => $schoolId, 'name' => 'ترم']);

        foreach ([
            ['name' => 'علی', 'class' => $classId, 'connected' => true],
            ['name' => 'رضا', 'class' => $classId, 'connected' => true],
            ['name' => 'علی', 'class' => $otherClassId, 'connected' => true],
            ['name' => 'علی', 'class' => $classId, 'connected' => false],
        ] as $index => $item) {
            $student = User::factory()->create([
                'first_name' => $item['name'],
                'bale_chat_id' => $item['connected'] ? (string) ($index + 100) : null,
            ]);
            $student->assignRole(Role::findOrCreate('student', 'web'));
            DB::table('term_enrollments')->insert([
                'user_id' => $student->id,
                'school_id' => $schoolId,
                'term_id' => $termId,
                'class_id' => $item['class'],
            ]);
        }

        $notifications = $this->mock(NotificationManager::class);
        $notifications->shouldReceive('sendVia')
            ->once()
            ->withArgs(fn ($channel, $student) => $channel === 'bale' && $student->first_name === 'علی' && $student->bale_chat_id === '100')
            ->andReturn(['ok' => true]);

        $this->actingAs($admin)
            ->postJson('/api/messages/send-to-students', [
                'body' => 'متن پیام',
                'school_id' => $schoolId,
                'field_id' => $fieldId,
                'academic_level_id' => $levelId,
                'class_id' => $classId,
                'first_name' => 'علی',
            ])
            ->assertOk()
            ->assertJsonPath('sent', 1);
    }

    public function test_rejects_broadcast_without_filters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        $this->actingAs($admin)
            ->postJson('/api/messages/send-to-students', ['body' => 'متن پیام'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filters');
    }

    public function test_student_cannot_send_bulk_messages(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::findOrCreate('student', 'web'));

        $this->actingAs($student)
            ->postJson('/api/messages/send-to-students', ['body' => 'متن پیام', 'first_name' => 'علی'])
            ->assertForbidden();
    }
}
