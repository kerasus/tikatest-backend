<?php

namespace Tests\Feature;

use App\Models\OnlineExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineExamSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('exams.view', 'web');
    }

    private function createStudent(): User
    {
        $student = User::factory()->create();
        $student->assignRole(Role::findOrCreate('student', 'web'));

        return $student;
    }

    private function createExam(array $timing = []): int
    {
        $schoolId = DB::table('schools')->insertGetId(['slug' => 'test-school', 'name' => 'مدرسه']);
        $termId = DB::table('academic_terms')->insertGetId(['school_id' => $schoolId, 'name' => 'ترم آزمون']);
        $categoryId = DB::table('exam_categories')->insertGetId(['title' => 'آزمون آنلاین']);
        $examId = DB::table('exams')->insertGetId([
            'name' => 'امتحان نمونه',
            'exam_category_id' => $categoryId,
            'term_id' => $termId,
            'delivery_mode' => 'online',
        ]);

        DB::table('online_exam_details')->insert(array_merge([
            'exam_id' => $examId,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'time_limit_minutes' => 30,
            'content' => json_encode(['type' => 'text', 'body' => 'متن آزمون']),
            'solution' => json_encode(['type' => 'text', 'body' => 'پاسخ محرمانه']),
        ], $timing));

        DB::table('online_exam_answer_keys')->insert([
            'exam_id' => $examId,
            'question_number' => 1,
            'correct_option' => '2',
        ]);

        return $examId;
    }

    public function test_student_cannot_open_exam_or_session_before_start(): void
    {
        $student = $this->createStudent();
        $examId = $this->createExam(['starts_at' => now()->addHour()]);
        $session = OnlineExamSession::create([
            'exam_id' => $examId,
            'student_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($student)->postJson("/api/online-exam-sessions/$examId/start")->assertStatus(409);
        $this->getJson("/api/student-portal/online-exams/$examId")->assertStatus(409);
        $this->getJson("/api/online-exam-sessions/$session->id")->assertStatus(409);
    }

    public function test_student_only_sees_own_session_and_cannot_answer_after_deadline(): void
    {
        $owner = $this->createStudent();
        $stranger = $this->createStudent();
        $examId = $this->createExam();

        $started = $this->actingAs($owner)->postJson("/api/online-exam-sessions/$examId/start")
            ->assertOk()
            ->assertDontSee('پاسخ محرمانه')
            ->assertDontSee('correct_option')
            ->json('session.id');

        $this->getJson("/api/online-exam-sessions/$started")
            ->assertOk()
            ->assertDontSee('پاسخ محرمانه')
            ->assertDontSee('correct_option');

        $this->actingAs($stranger)->getJson("/api/online-exam-sessions/$started")->assertForbidden();
        $this->postJson("/api/online-exam-sessions/$started/answer", ['question_number' => 1, 'submitted_option' => '2'])->assertForbidden();
        $this->postJson("/api/online-exam-sessions/$started/submit")->assertForbidden();

        DB::table('online_exam_details')->where('exam_id', $examId)->update(['ends_at' => now()->subMinute()]);
        $this->actingAs($owner)->postJson("/api/online-exam-sessions/$started/answer", [
            'question_number' => 1,
            'submitted_option' => '2',
        ])->assertStatus(409);
        $this->postJson("/api/online-exam-sessions/$started/submit")->assertStatus(409);

        $this->assertDatabaseMissing('online_exam_session_responses', ['online_exam_session_id' => $started]);
    }

    public function test_result_is_hidden_until_exam_ends_and_then_available_only_to_owner(): void
    {
        $owner = $this->createStudent();
        $other = $this->createStudent();
        $examId = $this->createExam();
        $session = OnlineExamSession::create([
            'exam_id' => $examId,
            'student_id' => $owner->id,
            'status' => 'graded',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinute(),
        ]);

        $this->actingAs($owner)->getJson("/api/online-exam-sessions/$examId/my-result")->assertStatus(409);
        $this->getJson("/api/online-exam-sessions/$session->id")->assertStatus(409);

        DB::table('online_exam_details')->where('exam_id', $examId)->update(['ends_at' => now()->subMinute()]);

        $this->getJson("/api/online-exam-sessions/$examId/my-result")
            ->assertOk()
            ->assertJsonPath('answer_keys.0.correct_option', '2');
        $this->actingAs($other)->getJson("/api/online-exam-sessions/$session->id")->assertForbidden();
    }

    public function test_expired_personal_duration_prevents_answer_and_start(): void
    {
        $student = $this->createStudent();
        $examId = $this->createExam();
        $session = OnlineExamSession::create([
            'exam_id' => $examId,
            'student_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(31),
            'duration_limit_seconds' => 1800,
        ]);

        $this->actingAs($student)->getJson("/api/online-exam-sessions/$session->id")->assertStatus(410);
        $this->postJson("/api/online-exam-sessions/$session->id/answer", [
            'question_number' => 1,
            'submitted_option' => '2',
        ])->assertStatus(409);
        $this->postJson("/api/online-exam-sessions/$examId/start")->assertStatus(410);

        $this->assertSame('expired', $session->fresh()->status);
    }

    public function test_only_current_class_members_can_start_or_view_assigned_exam(): void
    {
        $student = $this->createStudent();
        $examId = $this->createExam();
        $schoolId = DB::table('academic_terms')->value('school_id');
        $fieldId = DB::table('academic_fields')->insertGetId(['school_id' => $schoolId, 'name' => 'ریاضی']);
        $levelId = DB::table('academic_levels')->insertGetId(['field_id' => $fieldId, 'name' => 'دهم']);
        $classId = DB::table('classes')->insertGetId(['academic_level_id' => $levelId, 'name' => 'الف']);
        DB::table('exam_classes')->insert(['exam_id' => $examId, 'class_id' => $classId]);

        $this->actingAs($student)->postJson("/api/online-exam-sessions/$examId/start")->assertForbidden();
        $this->getJson("/api/student-portal/online-exams/$examId")->assertForbidden();

        DB::table('term_enrollments')->insert([
            'user_id' => $student->id,
            'school_id' => $schoolId,
            'term_id' => DB::table('exams')->where('id', $examId)->value('term_id'),
            'class_id' => $classId,
        ]);

        $this->postJson("/api/online-exam-sessions/$examId/start")->assertOk();
    }

    public function test_answer_release_time_is_respected_after_exam_ends(): void
    {
        $student = $this->createStudent();
        $examId = $this->createExam([
            'ends_at' => now()->subMinute(),
            'answers_visible_at' => now()->addHour(),
        ]);
        OnlineExamSession::create([
            'exam_id' => $examId,
            'student_id' => $student->id,
            'status' => 'graded',
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($student)->getJson("/api/online-exam-sessions/$examId/my-result")
            ->assertOk()
            ->assertDontSee('پاسخ محرمانه')
            ->assertDontSee('correct_option');

        DB::table('online_exam_details')->where('exam_id', $examId)
            ->update(['answers_visible_at' => now()->subMinute()]);

        $this->getJson("/api/online-exam-sessions/$examId/my-result")
            ->assertOk()
            ->assertJsonPath('answer_keys.0.correct_option', '2');
    }

    public function test_my_sessions_route_is_not_shadowed_by_resource_show(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)->getJson('/api/online-exam-sessions/my-sessions')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_student_can_answer_and_submit_while_exam_is_open_without_seeing_solutions(): void
    {
        $student = $this->createStudent();
        $examId = $this->createExam();
        $sessionId = $this->actingAs($student)->postJson("/api/online-exam-sessions/$examId/start")
            ->assertOk()
            ->json('session.id');

        $this->postJson("/api/online-exam-sessions/$sessionId/answer", [
            'question_number' => 1,
            'submitted_option' => '2',
        ])->assertOk();

        $this->assertDatabaseHas('online_exam_session_responses', [
            'online_exam_session_id' => $sessionId,
            'submitted_option' => '2',
        ]);

        $this->postJson("/api/online-exam-sessions/$sessionId/submit")
            ->assertOk()
            ->assertDontSee('correct_option')
            ->assertDontSee('پاسخ محرمانه')
            ->assertDontSee('marks_obtained');

        $this->assertSame('graded', OnlineExamSession::findOrFail($sessionId)->status);
    }

    public function test_management_permission_never_exempts_student_from_session_ownership(): void
    {
        $student = $this->createStudent();
        $student->givePermissionTo('exams.view');
        $owner = $this->createStudent();
        $examId = $this->createExam();
        $session = OnlineExamSession::create([
            'exam_id' => $examId,
            'student_id' => $owner->id,
            'status' => 'graded',
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)->getJson("/api/online-exam-sessions/$session->id")->assertForbidden();
        $this->getJson('/api/online-exam-sessions')->assertForbidden();
        $this->getJson("/api/online-exam-sessions/$examId/sessions/grouped-by-class")->assertForbidden();
        $this->getJson("/api/online-exam-sessions/$session->id/result")->assertForbidden();
    }
}
