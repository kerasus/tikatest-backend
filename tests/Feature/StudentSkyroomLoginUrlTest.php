<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SkyroomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class StudentSkyroomLoginUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_active_class_member_can_generate_a_student_login_url(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $schoolId = DB::table('schools')->insertGetId(['slug' => 'skyroom-test', 'name' => 'مدرسه']);
        $fieldId = DB::table('academic_fields')->insertGetId(['school_id' => $schoolId, 'name' => 'رشته']);
        $levelId = DB::table('academic_levels')->insertGetId(['field_id' => $fieldId, 'name' => 'پایه']);
        $classId = DB::table('classes')->insertGetId(['academic_level_id' => $levelId, 'name' => 'کلاس']);
        $termId = DB::table('academic_terms')->insertGetId(['school_id' => $schoolId, 'name' => 'ترم']);
        $accountId = DB::table('school_skyroom_accounts')->insertGetId([
            'school_id' => $schoolId,
            'title' => 'اکانت',
            'username' => 'account',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
        $roomId = DB::table('skyroom_rooms')->insertGetId([
            'class_id' => $classId,
            'skyroom_account_id' => $accountId,
            'skyroom_id' => 12345,
            'name' => 'test-room',
            'title' => 'اتاق',
            'status' => true,
        ]);
        DB::table('term_enrollments')->insert([
            'user_id' => $student->id,
            'school_id' => $schoolId,
            'class_id' => $classId,
            'term_id' => $termId,
            'enrolled_at' => now()->subDay(),
        ]);

        $skyroom = Mockery::mock(SkyroomService::class)->makePartial();
        $skyroom->shouldReceive('createStudentLoginUrl')
            ->once()
            ->with(12345, $student->id, $student->first_name.' '.$student->last_name.'('.$student->id.')')
            ->andReturn('https://www.skyroom.online/room/test-link');
        $this->app->instance(SkyroomService::class, $skyroom);

        $this->actingAs($otherStudent)
            ->postJson("/api/skyroom-rooms/{$roomId}/student-login-url")
            ->assertForbidden();

        $this->actingAs($student)
            ->postJson("/api/skyroom-rooms/{$roomId}/student-login-url")
            ->assertOk()
            ->assertJsonPath('join_url', 'https://www.skyroom.online/room/test-link');

        DB::table('term_enrollments')->where('user_id', $student->id)->update(['left_at' => now()->subMinute()]);
        $this->postJson("/api/skyroom-rooms/{$roomId}/student-login-url")
            ->assertForbidden();
    }
}
