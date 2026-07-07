<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentClassRegistration;
use App\Models\StudySession;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:students.view')->only(['index', 'show']);
        $this->middleware('permission:students.create')->only(['store']);
        $this->middleware('permission:students.update')->only(['update']);
        $this->middleware('permission:students.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'lastname',
                'full_name',
                'username',
                'student_phone',
                'melli_code',
                'student_code',
            ],
            'filterOnMultipleColumnKeys' => [
                [
                    'requestKey' => 'full_name_search',
                    'columns' => ['name', 'lastname', 'full_name'],
                ],
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'class_name',
                    'relationName' => 'studentClassRegistrations.schoolClass',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'field_name',
                    'relationName' => 'studentClassRegistrations.schoolClass.academicField',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'studentClassRegistrations',
                    'relationNames' => ['studentClassRegistrations'],
                ],
            ],
            'eagerLoads' => [
                'studentClassRegistrations.schoolClass.academicField',
                'studentClassRegistrations.schoolClass.academicLevel',
                'roles',
                'permissions',
            ],
        ];

        $modelQuery = User::query()->where('user_type', 'student')->orWhere('user_type', null);
        $perPage = $request->has('length') ? $request->get('length') : 10;

        $this->buildFilterQuery($request, $modelQuery, User::class, $this->getConfigArray($config));

        return $this->jsonResponseOk($modelQuery->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'student_phone' => 'nullable|string|max:20',
            'melli_code' => 'nullable|string|max:20',
            'student_code' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'student_email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_email' => 'nullable|email|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_email' => 'nullable|email|max:255',
            'school_id' => 'nullable|exists:schools,id',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $data = $request->all();
        $data['user_type'] = 'student';

        $user = User::create($data);

        if ($request->filled('class_id')) {
            StudentClassRegistration::create([
                'student_id' => $user->id,
                'class_id' => $request->class_id,
                'school_id' => $request->school_id,
            ]);
        }

        return $this->jsonResponseOk($user->load('studentClassRegistrations.schoolClass'));
    }

    public function show(int $id): JsonResponse
    {
        $student = User::where('id', $id)
            ->where(function ($query) {
                $query->where('user_type', 'student')->orWhereNull('user_type');
            })
            ->with([
                'studentClassRegistrations.schoolClass.academicField',
                'studentClassRegistrations.schoolClass.academicLevel',
                'roles',
                'permissions',
            ])
            ->findOrFail($id);

        return $this->jsonResponseOk($student);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,' . $student->id,
            'password' => 'nullable|string|min:6',
            'student_phone' => 'nullable|string|max:20',
            'melli_code' => 'nullable|string|max:20',
            'student_code' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'student_email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_email' => 'nullable|email|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_email' => 'nullable|email|max:255',
            'school_id' => 'nullable|exists:schools,id',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $student->fill($request->all());
        $student->save();

        if ($request->filled('class_id')) {
            StudentClassRegistration::updateOrCreate(
                ['student_id' => $student->id],
                ['class_id' => $request->class_id, 'school_id' => $request->school_id]
            );
        }

        return $this->jsonResponseOk($student->load('studentClassRegistrations.schoolClass'));
    }

    public function destroy(User $student): JsonResponse
    {
        if ($student->user_type !== 'student') {
            return $this->jsonResponseServerError([
                'errors' => ['student' => ['این کاربر دانش آموز نیست.']]
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
            $query->where('started_at', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->date_to . ' 23:59:59');
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
            $start = \Carbon\Carbon::parse($request->started_at);
            $end = \Carbon\Carbon::parse($request->ended_at);
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
            $start = \Carbon\Carbon::parse($request->started_at);
            $end = \Carbon\Carbon::parse($request->ended_at);
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
            'grade_type' => 'nullable|string',
        ]);

        $query = \App\Models\Grade::where('student_id', auth()->id())
            ->where('is_report_card', true)
            ->with(['lesson', 'schoolClass']);

        if ($request->filled('grade_type')) {
            $query->where('grade_type', $request->grade_type);
        }

        $grades = $query->orderBy('created_at', 'desc')->get();

        return $this->jsonResponseOk($grades);
    }

    public function myAbsences(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = \App\Models\DisciplinaryRecord::where('student_id', auth()->id())
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

        $query = \App\Models\DisciplinaryRecord::where('student_id', auth()->id())
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
            'grade_type' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $query = \App\Models\Grade::where('student_id', auth()->id())
            ->with(['lesson', 'schoolClass', 'examSession']);

        if ($request->filled('grade_type')) {
            $query->where('grade_type', $request->grade_type);
        }
        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        $grades = $query->orderBy('grade_date', 'desc')->paginate(20);

        return $this->jsonResponseOk($grades);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $studentId = auth()->id();

        $recentGrades = \App\Models\Grade::where('student_id', $studentId)
            ->with('lesson')
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

        $recentDisciplinary = \App\Models\DisciplinaryRecord::where('student_id', $studentId)
            ->with('disciplinaryCase')
            ->orderBy('incident_date', 'desc')
            ->limit(3)
            ->get();

        $pendingHomework = \App\Models\Homework::whereHas('submissions', function ($q) use ($studentId) {
            $q->where('student_id', $studentId)->where('status', '!=', 'submitted');
        })->count();

        return $this->jsonResponseOk([
            'recent_grades' => $recentGrades,
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
            $q->where('started_at', '>=', $request->date_from . ' 00:00:00');
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('started_at', '<=', $request->date_to . ' 23:59:59');
        });

        $query->when($request->filled('class_id'), function ($q) use ($request) {
            $q->whereHas('student.studentClassRegistrations', function ($subQ) use ($request) {
                $subQ->where('class_id', $request->class_id);
            });
        });

        $sessions = $query->orderBy('started_at', 'desc')->paginate(20);

        $totalMinutes = StudySession::when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('started_at', '>=', $request->date_from . ' 00:00:00');
        })->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('started_at', '<=', $request->date_to . ' 23:59:59');
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
            $q->where('started_at', '>=', $request->date_from . ' 00:00:00');
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('started_at', '<=', $request->date_to . ' 23:59:59');
        });

        $query->when($request->filled('lesson_id'), function ($q) use ($request) {
            $q->where('lesson_id', $request->lesson_id);
        });

        $sessions = $query->orderBy('started_at', 'desc')->paginate(20);

        $totalMinutes = StudySession::where('student_id', $studentId)
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('started_at', '>=', $request->date_from . ' 00:00:00');
            })->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where('started_at', '<=', $request->date_to . ' 23:59:59');
            })->sum('duration_minutes');

        return $this->jsonResponseOk([
            'sessions' => $sessions,
            'total_minutes' => $totalMinutes ?? 0,
            'total_hours' => $totalMinutes ? round($totalMinutes / 60, 2) : 0,
        ]);
    }
}
