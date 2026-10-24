<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotifyUnparticipatedStudentsTest extends TestCase
{
    use RefreshDatabase;

    private function createExam(string $deliveryMode = 'online'): Exam
    {
        $schoolId = DB::table('schools')->insertGetId(['slug' => 'school', 'name' => 'مدرسه']);
        $termId = DB::table('academic_terms')->insertGetId(['school_id' => $schoolId, 'name' => 'ترم']);
        $categoryId = DB::table('exam_categories')->insertGetId(['title' => 'آزمون']);

        return Exam::create([
            'name' => 'آزمون نمونه',
            'exam_category_id' => $categoryId,
            'term_id' => $termId,
            'delivery_mode' => $deliveryMode,
        ]);
    }

    public function test_authorized_user_can_notify_unparticipated_students(): void
    {
        Permission::findOrCreate('exams.update', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('exams.update');
        $exam = $this->createExam();
        $stats = ['total' => 1, 'sent' => 1, 'skipped' => 0, 'failed' => 0, 'results' => []];

        $service = $this->mock(ExamService::class);
        $service->shouldReceive('notifyUnparticipatedStudents')
            ->once()
            ->withArgs(fn (Exam $target) => $target->id === $exam->id)
            ->andReturn($stats);

        $this->actingAs($user)
            ->postJson("/api/exams/{$exam->id}/notify-unparticipated-students")
            ->assertOk()
            ->assertExactJson($stats);
    }

    public function test_student_cannot_send_exam_reminders(): void
    {
        Permission::findOrCreate('exams.update', 'web');
        $student = User::factory()->create();
        $student->assignRole(Role::findOrCreate('student', 'web'));
        $exam = $this->createExam();

        $this->actingAs($student)
            ->postJson("/api/exams/{$exam->id}/notify-unparticipated-students")
            ->assertForbidden();
    }

    public function test_in_person_exam_cannot_send_online_reminders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $exam = $this->createExam('in_person');

        $this->actingAs($admin)
            ->postJson("/api/exams/{$exam->id}/notify-unparticipated-students")
            ->assertStatus(422);
    }
}
