<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\DisciplinaryRecord;
use App\Models\Homework;
use App\Models\InPersonExamResult;
use App\Models\StudentProfile;
use App\Models\StudySession;
use App\Models\User;
use App\Models\UserClass;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:students.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:students.create')->only(['store']);
        $this->middleware('admin_or_permission:students.update')->only(['update']);
        $this->middleware('admin_or_permission:students.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'first_name',
                'last_name',
                'username',
                'mobile',
                'email',
                'national_id',
            ],
            'filterKeysExact' => [],
            'filterOnMultipleColumnKeys' => [
                [
                    'requestKey' => 'full_name_search',
                    'columns' => ['first_name', 'last_name'],
                ],
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'class_name',
                    'relationName' => 'userClassRegistrations.schoolClass',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'userClassRegistrations.schoolClass.academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'student_code',
                    'relationName' => 'studentProfile',
                    'relationColumn' => 'code',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'father_name',
                    'relationName' => 'guardianRecords.user',
                    'relationColumn' => 'first_name',
                    'exact' => false,
                ],
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'class_id',
                    'relationName' => 'userClassRegistrations',
                ],
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'userClassRegistrations',
                    'relationNames' => ['userClassRegistrations'],
                ],
            ],
            'eagerLoads' => [
                'userClassRegistrations.schoolClass.academicLevel.academicField.school',
                'studentProfile',
                'guardianRecords.user',
                'roles',
                'permissions',
            ],
        ];

        $modelQuery = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'student'));
        $perPage = $request->has('length') ? $request->get('length') : 10;

        $this->buildFilterQuery($request, $modelQuery, User::class, $this->getConfigArray($config));

        if ($request->filled('field_id')) {
            $modelQuery->whereHas('userClassRegistrations.schoolClass.academicLevel.academicField', function ($query) use ($request) {
                $query->where('academic_fields.id', $request->get('field_id'));
            });
        }

        if ($request->filled('academic_level_id')) {
            $modelQuery->whereHas('userClassRegistrations.schoolClass', function ($query) use ($request) {
                $query->where('academic_level_id', $request->get('academic_level_id'));
            });
        }

        if ($request->filled('school_id')) {
            $modelQuery->whereHas('userClassRegistrations.schoolClass.academicLevel.academicField.school', function ($q) use ($request) {
                $q->where('id', $request->get('school_id'));
            });
        }

        return $this->jsonResponseOk($modelQuery->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'mobile' => 'nullable|string|max:20|unique:users,mobile',
            'national_id' => 'nullable|string|max:20',
            'student_code' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $data = $request->all();

        $user = User::create($data);
        $user->assignRole(UserRoleType::Student->value);

        if ($request->filled('student_code')) {
            StudentProfile::create([
                'user_id' => $user->id,
                'code' => $request->input('student_code'),
            ]);
        }

        if ($request->filled('class_id')) {
            UserClass::create([
                'user_id' => $user->id,
                'class_id' => $request->class_id,
            ]);
        }

        return $this->jsonResponseOk($user->load('studentProfile', 'userClassRegistrations.schoolClass'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $student = User::where('id', $id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'student'))
            ->with([
                'userClassRegistrations.schoolClass.academicLevel.academicField.school',
                'studentProfile',
                'guardianRecords.user',
                'roles',
                'permissions',
            ])
            ->findOrFail($id);

        return $this->jsonResponseOk($student);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,'.$student->id,
            'password' => 'nullable|string|min:6',
            'mobile' => 'sometimes|nullable|string|max:20|unique:users,mobile,'.$student->id,
            'national_id' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $student->fill($request->only([
            'first_name', 'last_name', 'username', 'password', 'mobile',
            'national_id', 'birth_date', 'email', 'address', 'description',
        ]));

        if ($request->filled('class_id')) {
            UserClass::updateOrCreate(
                ['user_id' => $student->id],
                ['class_id' => $request->class_id]
            );
        }

        return $this->jsonResponseOk($student->load('studentProfile', 'guardianRecords.user', 'userClassRegistrations.schoolClass'));
    }

    public function destroy(User $student): JsonResponse
    {
        if (! $student->hasRole(UserRoleType::Student->value)) {
            return $this->jsonResponseServerError([
                'errors' => ['student' => ['این کاربر دانش آموز نیست.']],
            ]);
        }

        $student->delete();

        return $this->jsonResponseOk(['message' => 'حذف دانش آموز با موفقیت انجام شد.']);
    }

    public function studySessions(Request $request): JsonResponse
    {
        $request->validate([
            'lesson_id' => 'nullable|exists:lessons,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = StudySession::where('student_id', auth()->id())
            ->with(['lesson']);

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }
        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->date_to.' 23:59:59');
        }

        $sessions = $query->orderBy('started_at', 'desc')->paginate(20);

        return $this->jsonResponseOk($sessions);
    }

    public function storeStudySession(Request $request): JsonResponse
    {
        $request->validate([
            'lesson_id' => 'nullable|exists:lessons,id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data = $request->all();
        $data['student_id'] = auth()->id();

        if ($request->filled('started_at') && $request->filled('ended_at')) {
            $start = Carbon::parse($request->started_at);
            $end = Carbon::parse($request->ended_at);
            $data['duration_minutes'] = $start->diffInMinutes($end);
        }

        $session = StudySession::create($data);

        return $this->jsonResponseOk($session->load('lesson'));
    }

    public function showStudySession(int $id): JsonResponse
    {
        $session = StudySession::where('id', $id)
            ->where('student_id', auth()->id())
            ->with(['lesson'])
            ->firstOrFail();

        return $this->jsonResponseOk($session);
    }

    public function updateStudySession(Request $request, int $id): JsonResponse
    {
        $session = StudySession::where('id', $id)
            ->where('student_id', auth()->id())
            ->firstOrFail();

        $request->validate([
            'lesson_id' => 'nullable|exists:lessons,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after:started_at',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $session->fill($request->all());

        if ($request->filled('started_at') && $request->filled('ended_at')) {
            $start = Carbon::parse($request->started_at);
            $end = Carbon::parse($request->ended_at);
            $session->duration_minutes = $start->diffInMinutes($end);
        }

        $session->save();

        return $this->jsonResponseOk($session->load('lesson'));
    }

    public function destroyStudySession(int $id): JsonResponse
    {
        $session = StudySession::where('id', $id)
            ->where('student_id', auth()->id())
            ->firstOrFail();

        $session->delete();

        return $this->jsonResponseOk(['message' => 'جلسه مطالعه با موفقیت حذف شد.']);
    }

    public function myReportCard(Request $request): JsonResponse
    {
        $request->validate([
            'category_title' => 'nullable|string',
        ]);

        $query = InPersonExamResult::where('user_id', auth()->id())
            ->whereNotNull('scaled_score')
            ->with(['inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.classes']);

        if ($request->filled('category_title')) {
            $query->whereHas('inPersonExamDetail.exam.category', function ($q) use ($request) {
                $q->where('title', $request->category_title);
            });
        }

        $results = $query->orderBy('created_at', 'desc')->get();

        return $this->jsonResponseOk($results);
    }

    public function myAbsences(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = DisciplinaryRecord::where('student_id', auth()->id())
            ->whereHas('disciplinaryCase', function ($q) {
                $q->where('name', 'like', '%غیبت%')->orWhere('name', 'like', '%absence%');
            })
            ->with(['disciplinaryCase']);

        if ($request->filled('date_from')) {
            $query->where('incident_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('incident_date', '<=', $request->date_to);
        }

        $records = $query->orderBy('incident_date', 'desc')->get();

        return $this->jsonResponseOk($records);
    }

    public function myDisciplinaryRecords(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = DisciplinaryRecord::where('student_id', auth()->id())
            ->with(['disciplinaryCase']);

        if ($request->filled('date_from')) {
            $query->where('incident_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('incident_date', '<=', $request->date_to);
        }

        $records = $query->orderBy('incident_date', 'desc')->get();

        return $this->jsonResponseOk($records);
    }

    public function myGrades(Request $request): JsonResponse
    {
        $request->validate([
            'category_title' => 'nullable|string',
        ]);

        $query = InPersonExamResult::where('user_id', auth()->id())
            ->with(['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.lesson']);

        if ($request->filled('category_title')) {
            $query->whereHas('inPersonExamDetail.exam.category', function ($q) use ($request) {
                $q->where('title', $request->category_title);
            });
        }

        $results = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->jsonResponseOk($results);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $studentId = auth()->id();

        $recentResults = InPersonExamResult::where('user_id', $studentId)
            ->with('inPersonExamDetail.exam.lesson')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentStudySessions = StudySession::where('student_id', $studentId)
            ->with('lesson')
            ->orderBy('started_at', 'desc')
            ->limit(5)
            ->get();

        $totalStudyMinutes = StudySession::where('student_id', $studentId)
            ->whereMonth('started_at', now()->month)
            ->sum('duration_minutes');

        $recentDisciplinary = DisciplinaryRecord::where('student_id', $studentId)
            ->with('disciplinaryCase')
            ->orderBy('incident_date', 'desc')
            ->limit(3)
            ->get();

        $pendingHomework = Homework::whereHas('owners', function ($q) use ($studentId) {
            $q->where('user_id', $studentId)->whereNull('submitted_at');
        })->count();

        return $this->jsonResponseOk([
            'recent_grades' => $recentResults,
            'recent_study_sessions' => $recentStudySessions,
            'total_study_minutes_this_month' => $totalStudyMinutes,
            'recent_disciplinary' => $recentDisciplinary,
            'pending_homework_count' => $pendingHomework,
        ]);
    }

    public function studyHoursGeneralReport(Request $request): JsonResponse
    {
        $query = StudySession::with(['student', 'lesson']);

        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('started_at', '>=', $request->date_from.' 00:00:00');
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('started_at', '<=', $request->date_to.' 23:59:59');
        });

        $query->when($request->filled('class_id'), function ($q) use ($request) {
            $q->whereHas('student.userClassRegistrations', function ($subQ) use ($request) {
                $subQ->where('class_id', $request->class_id);
            });
        });

        $sessions = $query->orderBy('started_at', 'desc')->paginate(20);

        $totalMinutes = StudySession::when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('started_at', '>=', $request->date_from.' 00:00:00');
        })->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('started_at', '<=', $request->date_to.' 23:59:59');
        })->sum('duration_minutes');

        return $this->jsonResponseOk([
            'sessions' => $sessions,
            'total_minutes' => $totalMinutes ?? 0,
            'total_hours' => $totalMinutes ? round($totalMinutes / 60, 2) : 0,
        ]);
    }

    public function studyHoursStudentReport(Request $request, $studentId): JsonResponse
    {
        $query = StudySession::where('student_id', $studentId)
            ->with(['lesson']);

        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('started_at', '>=', $request->date_from.' 00:00:00');
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('started_at', '<=', $request->date_to.' 23:59:59');
        });

        $query->when($request->filled('lesson_id'), function ($q) use ($request) {
            $q->where('lesson_id', $request->lesson_id);
        });

        $sessions = $query->orderBy('started_at', 'desc')->paginate(20);

        $totalMinutes = StudySession::where('student_id', $studentId)
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('started_at', '>=', $request->date_from.' 00:00:00');
            })->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where('started_at', '<=', $request->date_to.' 23:59:59');
            })->sum('duration_minutes');

        return $this->jsonResponseOk([
            'sessions' => $sessions,
            'total_minutes' => $totalMinutes ?? 0,
            'total_hours' => $totalMinutes ? round($totalMinutes / 60, 2) : 0,
        ]);
    }
}
