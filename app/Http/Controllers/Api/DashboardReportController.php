<?php

namespace App\Http\Controllers\Api;

use App\Models\Exam;
use App\Models\User;
use App\Traits\Filter;
use App\Models\Homework;
use App\Traits\CommonCRUD;
use App\Models\SchoolUser;
use App\Enums\UserRoleType;
use App\Models\SchoolClass;
use App\Models\StudySession;
use Illuminate\Http\Request;
use App\Models\CalendarEvent;
use App\Models\SchoolFeature;
use App\Services\ExamService;
use App\Models\TermEnrollment;
use App\Services\SkyroomService;
use App\Models\OnlineExamSession;
use Illuminate\Http\JsonResponse;
use App\Models\InPersonExamResult;
use Illuminate\Support\Facades\DB;
use App\Models\SkyroomRoomSchedule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\Commands\RunHealthChecksCommand;

class DashboardReportController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $schoolDashboardRoles = implode('|', [
            UserRoleType::Admin->value,
            UserRoleType::Manager->value,
            UserRoleType::Staff->value,
            UserRoleType::Teacher->value,
        ]);

        $studentDashboardRoles = implode('|', [
            UserRoleType::Admin->value,
            UserRoleType::Manager->value,
            UserRoleType::Staff->value,
            UserRoleType::Student->value,
            UserRoleType::Guardian->value,
        ]);

        $this->middleware("role:{$schoolDashboardRoles}")->only('schoolDashboard');
        $this->middleware("role:{$studentDashboardRoles}")->only('studentDashboard');
        $this->middleware('role:'.UserRoleType::Admin->value)->only([
            'adminDashboard',
            'checkHealth',
        ]);
    }

    public function adminDashboard(): JsonResponse
    {
        $studentsCount = TermEnrollment::query()
            ->active()
            ->whereTermIsActive()
            ->distinct()
            ->count('user_id');

        return $this->jsonResponseOk([
            'students_count' => $studentsCount,
            'classes_count' => SchoolClass::query()->count(),
            'online_exams_count' => Exam::query()
                ->where('delivery_mode', 'online')
                ->count(),
            'homeworks_count' => Homework::query()->count()
        ]);
    }

    public function schoolDashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $user = $request->user();

        if (! $user->hasRole(UserRoleType::Admin->value)) {
            $hasSchoolAccess = SchoolUser::query()
                ->where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->exists();

            abort_unless($hasSchoolAccess, 403, 'You are not a member of this school.');
        }

        $studentsCount = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserRoleType::Student->value))
            ->whereHas('termEnrollments', fn ($query) => $query->where('school_id', $schoolId))
            ->count();

        $classesCount = SchoolClass::query()
            ->whereHas('academicLevel.academicField', fn ($query) => $query->where('school_id', $schoolId))
            ->count();

        $onlineExamsCount = Exam::query()
            ->inSchool($schoolId)
            ->where('delivery_mode', 'online')
            ->count();

        $homeworksCount = Homework::query()
            ->whereHas('term', fn ($query) => $query->where('school_id', $schoolId))
            ->count();

        return $this->jsonResponseOk([
            'students_count' => $studentsCount,
            'classes_count' => $classesCount,
            'online_exams_count' => $onlineExamsCount,
            'homeworks_count' => $homeworksCount,
        ]);
    }

    public function studentDashboard(
        Request $request,
        ExamService $examService,
        SkyroomService $skyroomService
    ): JsonResponse {
        $student = $request->user();
        $studentId = $student->id;
        $enrollments = $request->user()->termEnrollments()
            ->active()
            ->get(['class_id', 'school_id']);

        $schoolIds = $enrollments->pluck('school_id')->filter()->unique()->values();
        $classIds = $enrollments->pluck('class_id')->filter()->unique()->values();

        // -------------------------------------------------------------
        // ۱. بررسی فیچر اسکای‌روم و پیدا کردن کلاس‌های آنلاین فعال/نزدیک
        // -------------------------------------------------------------
        $hasSkyroomFeature = false;
        $activeOnlineClasses = collect();

        if ($schoolIds->isNotEmpty()) {
            $hasSkyroomFeature = SchoolFeature::query()
                ->whereIn('school_id', $schoolIds)
                ->where('feature_key', 'skyroom')
                ->where('is_enabled', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();
        }

        if ($hasSkyroomFeature && $classIds->isNotEmpty()) {
            $now = now()->timezone('Asia/Tehran');
            $todayDate = $now->toDateString();
            $currentTime = $now->format('H:i:s');
            $nearFutureTime = $now->copy()->addMinutes(60)->format('H:i:s');
            $currentDayOfWeek = ($now->dayOfWeek + 1) % 7;

            $activeOnlineClasses = SkyroomRoomSchedule::query()
                ->where('is_active', true)
                ->whereHas('room', function ($roomQuery) use ($classIds) {
                    $roomQuery->whereIn('class_id', $classIds)
                        ->where('status', true);
                })
                ->where(function ($scheduleQuery) use ($todayDate, $currentDayOfWeek) {
                    $scheduleQuery->whereDate('held_date', $todayDate)
                        ->orWhere(function ($recurringQuery) use ($currentDayOfWeek) {
                            $recurringQuery->whereNull('held_date')
                                ->where('day_of_week', $currentDayOfWeek);
                        });
                })
                ->where(function ($timeQuery) use ($currentTime, $nearFutureTime) {
                    $timeQuery
                        // ۱) در حال برگزاری
                        ->where(function ($ongoingQuery) use ($currentTime) {
                            $ongoingQuery
                                ->where(function ($normal) use ($currentTime) {
                                    $normal->whereColumn('start_time', '<=', 'end_time')
                                        ->where('start_time', '<=', $currentTime)
                                        ->where('end_time', '>=', $currentTime);
                                })
                                ->orWhere(function ($crossMidnight) use ($currentTime) {
                                    $crossMidnight->whereColumn('start_time', '>', 'end_time')
                                        ->where(function ($q) use ($currentTime) {
                                            $q->where('start_time', '<=', $currentTime)
                                                ->orWhere('end_time', '>=', $currentTime);
                                        });
                                });
                        })
                        // ۲) به‌زودی شروع می‌شود (پنجره ۶۰ دقیقه آینده)
                        ->orWhere(function ($upcomingQuery) use ($currentTime, $nearFutureTime) {
                            $upcomingQuery->where('start_time', '>', $currentTime)
                                ->where('start_time', '<=', $nearFutureTime);
                        });
                })
                ->with(['room.class', 'room.skyroomAccount'])
                ->orderBy('start_time')
                ->get()
                ->map(function (SkyroomRoomSchedule $schedule) use ($currentTime, $skyroomService, $student) {
                    $room = $schedule->room;
                    $apiKey = $room->skyroomAccount?->api_key;
                    $isLiveNow = ($schedule->start_time <= $currentTime && $schedule->end_time >= $currentTime);

                    $joinUrl = null;

                    if ($room && $student && $room->skyroom_id && $apiKey) {
                        $nickname = $student->first_name.' '.$student->last_name.'('.$student->id.')';

                        //                        // ۱. ابتدا اخراج کاربر از اتاق (با مدیریت خطا تا در صورت نبودن کاربر در کلاس، لینک لغو نشود)
                        //                        try {
                        //                            $skyroomService->usingApiKey($apiKey)->removeRoomUsers(
                        //                                $room->skyroom_id,
                        //                                $student->id
                        //                            );
                        //                        } catch (\Throwable $e) {
                        //                            // لاگ اخطار جهت خطایابی؛ روند تولید لینک ورود متوقف نمی‌شود
                        //                            \Log::warning('Skyroom kick before join failed', [
                        //                                'room_id'    => $room->skyroom_id,
                        //                                'student_id' => $student->id,
                        //                                'error'      => $e->getMessage(),
                        //                            ]);
                        //                        }

                        // ۲. تولید لینک جدید و معتبر برای ورود
                        $joinUrl = $skyroomService->usingApiKey($apiKey)
                            ->createStudentLoginUrl(
                                $room->skyroom_id,
                                $student->id,
                                $nickname
                            );
                    }

                    return [
                        'schedule_id' => $schedule->id,
                        'room_id' => $room->id,
                        'class_id' => $room->class_id,
                        'class_name' => $room->class?->name ?? $room->title,
                        'title' => $schedule->title ?? $room->title,
                        'start_time' => substr($schedule->start_time, 0, 5),
                        'end_time' => substr($schedule->end_time, 0, 5),
                        'is_live_now' => $isLiveNow,
                        'status' => $isLiveNow ? 'live' : 'upcoming',
                        'join_url' => $joinUrl,
                    ];
                });
        }

        // -------------------------------------------------------------
        // ۲. بخش‌های قبلی (آزمون‌ها، تکالیف، نمرات، تقویم و ساعات مطالعه)
        // -------------------------------------------------------------
        $upcomingExams = $examService->listStudentRelatedExams(
            studentUserId: $studentId,
            from: now()->toDateTimeString(),
            to: null,
            onlyOnline: true,
            onlyPendingOrInProgress: true
        )
            ->get()
            ->take(5)
            ->values();

        $pendingHomeworks = Homework::query()
            ->where(function ($homeworkQuery) use ($studentId) {
                $homeworkQuery->forStudent($studentId)
                    ->orWhere(function ($globalHomeworkQuery) {
                        $globalHomeworkQuery->doesntHave('classes')
                            ->doesntHave('academicLevels');
                    });
            })
            ->whereDoesntHave('submissions', function ($submissionQuery) use ($studentId) {
                $submissionQuery->where('student_id', $studentId)
                    ->whereNotNull('submitted_at');
            })
            ->where(function ($dueDateQuery) {
                $dueDateQuery->whereNull('due_date')
                    ->orWhereDate('due_date', '>=', today());
            })
            ->with(['lesson', 'classes', 'academicLevels'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(5)
            ->get();

        $inPersonGrades = InPersonExamResult::where('user_id', $studentId)
            ->whereHas('inPersonExamDetail', function ($detailQuery) {
                $detailQuery->where(function ($visibilityQuery) {
                    $visibilityQuery->whereNull('results_visible_at')
                        ->orWhere('results_visible_at', '<=', now());
                });
            })
            ->with('inPersonExamDetail.exam.lesson')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (InPersonExamResult $result) {
                $exam = $result->inPersonExamDetail?->exam;

                return [
                    'id' => 'in_person_'.$result->id,
                    'type' => 'in_person',
                    'exam_id' => $exam?->id,
                    'exam_name' => $exam?->name,
                    'lesson_name' => $exam?->lesson?->name,
                    'score' => $result->raw_score,
                    'scaled_score' => $result->scaled_score,
                    't_score' => $result->t_score,
                    'percent' => null,
                    'graded_at' => $result->created_at,
                ];
            });

        $onlineGrades = OnlineExamSession::where('student_id', $studentId)
            ->where('status', 'graded')
            ->whereNotNull('t_score')
            ->with('exam.lesson')
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (OnlineExamSession $session) {
                return [
                    'id' => 'online_'.$session->id,
                    'type' => 'online',
                    'exam_id' => $session->exam?->id,
                    'exam_name' => $session->exam?->name,
                    'lesson_name' => $session->exam?->lesson?->name,
                    'score' => $session->t_score,
                    'scaled_score' => null,
                    't_score' => $session->t_score,
                    'percent' => $session->percent,
                    'graded_at' => $session->submitted_at ?? $session->updated_at,
                ];
            });

        $recentGrades = $inPersonGrades
            ->concat($onlineGrades)
            ->sortByDesc('graded_at')
            ->take(5)
            ->values();

        $totalStudyMinutes = StudySession::where('student_id', $studentId)
            ->whereBetween('started_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sumDurationMinutes();

        $upcomingEvents = CalendarEvent::query()
            ->where('status', 'active')
            ->whereBetween('starts_at', [now(), now()->copy()->addDays(14)->endOfDay()])
            ->whereHas('calendar', function ($calendarQuery) use ($studentId, $schoolIds) {
                $calendarQuery->where('is_active', true)
                    ->where(function ($accessQuery) use ($studentId, $schoolIds) {
                        $accessQuery->where('user_id', $studentId)
                            ->orWhere(function ($schoolCalendarQuery) use ($schoolIds) {
                                $schoolCalendarQuery->whereNull('user_id')
                                    ->whereIn('school_id', $schoolIds);
                            })
                            ->orWhere(function ($globalCalendarQuery) {
                                $globalCalendarQuery->whereNull('user_id')
                                    ->whereNull('school_id');
                            })
                            ->orWhereIn('id', function ($calendarUserQuery) use ($studentId) {
                                $calendarUserQuery->select('calendar_id')
                                    ->from('calendar_user')
                                    ->where('user_id', $studentId)
                                    ->where('is_visible', true);
                            });
                    });
            })
            ->with('calendar')
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        return $this->jsonResponseOk([
            'has_skyroom_feature' => $hasSkyroomFeature,
            'active_online_classes' => $activeOnlineClasses,
            'upcoming_exams' => $upcomingExams,
            'pending_homeworks' => $pendingHomeworks,
            'recent_grades' => $recentGrades,
            'upcoming_events' => $upcomingEvents,
            'total_study_minutes_this_month' => $totalStudyMinutes,
            'total_study_hours_this_month' => round($totalStudyMinutes / 60, 2),
        ]);
    }

    public function checkHealth(ResultStore $resultStore)
    {

        // اجرای چک‌ها در لحظه (خیلی سریع)
        artisan::call(RunHealthChecksCommand::class);

        // دریافت آخرین نتایج به صورت Array/JSON
        $checkResults = $resultStore->latestResults();

        return response()->json($checkResults);
    }
}
