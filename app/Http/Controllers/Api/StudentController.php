<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\DisciplinaryRecord;
use App\Models\Exam;
use App\Models\Homework;
use App\Models\InPersonExamResult;
use App\Models\OnlineExamSession;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\StudySession;
use App\Models\User;
use App\Services\ExamService;
use App\Services\TermEnrollmentService;
use App\Services\TermService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $staffRoles = implode('|', [
            UserRoleType::Admin->value,
            UserRoleType::Manager->value,
            UserRoleType::Staff->value,
        ]);

        // متدهایی که فقط و فقط کادر مدرسه و مدیران اجازه دسترسی به آن‌ها را دارند
        $this->middleware("role:{$staffRoles}")->only([
            'index',
            'store',
            'destroy',
            'studyHoursGeneralReport',
            'studyHoursStudentReport',
        ]);

        // سایر متدها (show, update, dashboard و گزارش‌های شخصی) برای هر ۵ نقش مجاز هستند
        $allRoles = implode('|', [
            UserRoleType::Admin->value,
            UserRoleType::Manager->value,
            UserRoleType::Staff->value,
            UserRoleType::Student->value,
            UserRoleType::Guardian->value,
        ]);

        $this->middleware("role:{$allRoles}")->except([
            'index',
            'store',
            'destroy',
            'studyHoursGeneralReport',
            'studyHoursStudentReport',
        ]);
    }

    /**
     * بررسی دسترسی کادر مدرسه، خود دانش‌آموز یا ولی مربوطه
     */
    private function validateStudentAccess(User $currentUser, User|int $student): User
    {
        $studentModel = $student instanceof User
            ? $student
            : User::where('id', $student)
                ->whereHas('roles', fn ($q) => $q->where('name', UserRoleType::Student->value))
                ->firstOrFail();

        // ۱. ادمین، مدیر یا کادر به همه دسترسی دارند
        if ($this->isStaffUser($currentUser)) {
            return $studentModel;
        }

        // ۲. دانش‌آموز فقط به رکورد خودش دسترسی دارد
        if ($currentUser->hasRole(UserRoleType::Student->value)) {
            if ($currentUser->id === $studentModel->id) {
                return $studentModel;
            }
        }

        // ۳. اولیاء فقط به فرزندان متصل به خود دسترسی دارند
        if ($currentUser->hasRole(UserRoleType::Guardian->value)) {
            $isChild = $studentModel->studentProfile()
                ->whereHas('guardians', fn ($q) => $q->where('guardian_records.user_id', $currentUser->id))
                ->exists();

            if ($isChild) {
                return $studentModel;
            }
        }

        abort(403, 'دسترسی لازم برای مشاهده یا ویرایش این اطلاعات را ندارید.');
    }

    private function isStaffUser(User $user): bool
    {
        return $user->hasAnyRole([
            UserRoleType::Admin->value,
            UserRoleType::Manager->value,
            UserRoleType::Staff->value,
        ]);
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
            'filterKeysIn' => [
                'id',
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
                    'relationName' => 'termEnrollments.schoolClass',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'termEnrollments.schoolClass.academicLevel',
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
            'eagerLoads' => [
                'termEnrollments.schoolClass.academicLevel.academicField.school',
                'studentProfile',
                'guardianRecords.user',
                'roles',
                'permissions',
            ],
        ];

        $modelQuery = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'student'));
        $perPage = $request->has('length') ? $request->get('length') : 10;

        $this->buildFilterQuery(
            $request,
            $modelQuery,
            User::class,
            $this->getConfigArray($config)
        );

        if (
            $request->filled('class_id') ||
            $request->filled('academic_level_id') ||
            $request->filled('field_id') ||
            $request->filled('school_id')
        ) {
            $modelQuery->whereHas('termEnrollments', function ($registrationQuery) use ($request) {
                if ($request->filled('class_id')) {
                    $registrationQuery->where(
                        'term_enrollments.class_id',
                        $request->integer('class_id')
                    );
                }

                $registrationQuery->whereHas('schoolClass', function ($classQuery) use ($request) {
                    if ($request->filled('academic_level_id')) {
                        $classQuery->where(
                            'classes.academic_level_id',
                            $request->integer('academic_level_id')
                        );
                    }

                    if (
                        $request->filled('field_id') ||
                        $request->filled('school_id')
                    ) {
                        $classQuery->whereHas('academicLevel', function ($levelQuery) use ($request) {
                            if ($request->filled('field_id')) {
                                $levelQuery->where(
                                    'academic_levels.field_id',
                                    $request->integer('field_id')
                                );
                            }

                            if ($request->filled('school_id')) {
                                $levelQuery->whereHas(
                                    'academicField',
                                    fn ($fieldQuery) => $fieldQuery->where(
                                        'academic_fields.school_id',
                                        $request->integer('school_id')
                                    )
                                );
                            }
                        });
                    }
                });
            });
        }

        return $this->jsonResponseOk($modelQuery->paginate($perPage));
    }

    public function store(
        Request $request,
        TermService $termService,
        TermEnrollmentService $termEnrollmentService
    ): JsonResponse {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6', // یا پسورد پیش‌فرض دلخواهت
            'mobile' => 'nullable|string|max:20|unique:users,mobile',
            'national_id' => 'nullable|string|max:20',
            'student_code' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'picture' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
            'class_ids' => 'required|array|distinct',
            'class_ids.*' => 'required|integer|exists:classes,id',
        ]);

        $user = DB::transaction(function () use ($request, $termService, $termEnrollmentService) {
            $data = $request->only([
                'first_name', 'last_name', 'username', 'password', 'mobile',
                'national_id', 'birth_date', 'email', 'address', 'description',
            ]);

            if ($request->hasFile('picture')) {
                $data['picture'] = $request->file('picture')->store('student-pictures', 'public');
            }

            $user = User::create($data);
            $user->assignRole(UserRoleType::Student->value);

            StudentProfile::create([
                'user_id' => $user->id,
                'code' => $request->input('student_code'),
            ]);

            $classes = SchoolClass::with('academicLevel.academicField.school')
                ->whereIn('id', $request->class_ids)
                ->get();

            $activeTermsCache = [];

            foreach ($classes as $schoolClass) {
                $schoolId = $schoolClass->academicLevel?->academicField?->school?->id;

                if (! $schoolId) {
                    throw ValidationException::withMessages([
                        'class_ids' => 'مدرسه کلاس انتخاب‌شده مشخص نیست.',
                    ]);
                }

                if (! array_key_exists($schoolId, $activeTermsCache)) {
                    $activeTerm = $termService->getActiveTermWithParents($schoolId);
                    $activeTermsCache[$schoolId] = $activeTerm?->id;
                }

                $termId = $activeTermsCache[$schoolId];

                if (! $termId) {
                    throw ValidationException::withMessages([
                        'class_ids' => 'برای مدرسه کلاس انتخاب‌شده ترم فعال وجود ندارد.',
                    ]);
                }

                $termEnrollmentService->enrollStudent(
                    $schoolClass->id,
                    $user->id,
                    $termId
                );
            }

            return $user;
        });

        return $this->jsonResponseOk($user->load('studentProfile', 'termEnrollments.schoolClass'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $student = $this->validateStudentAccess($request->user(), (int) $id);

        $student->load([
            'termEnrollments.schoolClass.academicLevel.academicField.school',
            'studentProfile.guardians.user',
            'roles',
            'permissions',
        ]);

        return $this->jsonResponseOk($student);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $user = $request->user();
        $student = $this->validateStudentAccess($user, $student);

        $isStaff = $this->isStaffUser($user);

        if ($isStaff) {
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
                'picture' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:2048',
            ]);

            $data = $request->only([
                'first_name', 'last_name', 'username', 'password', 'mobile',
                'national_id', 'birth_date', 'email', 'address', 'description',
            ]);
        } else {
            // دانش‌آموز یا ولی فقط حق تغییر عکس دارند
            $request->validate([
                'picture' => 'required|image|mimes:jpeg,jpg,png,gif|max:2048',
            ]);

            $data = [];
        }

        if ($request->hasFile('picture')) {
            if ($student->picture && Storage::disk('public')->exists($student->picture)) {
                Storage::disk('public')->delete($student->picture);
            }

            $data['picture'] = $request->file('picture')->store('student-pictures', 'public');
        }

        if (! empty($data)) {
            $student->fill($data);
            $student->save();
        }

        return $this->jsonResponseOk($student->load('studentProfile', 'guardianRecords.user', 'termEnrollments.schoolClass'));
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

    public function dashboard(Request $request, ExamService $examService): JsonResponse
    {
        $studentId = $request->user()->id;
        $enrollments = $request->user()->termEnrollments()
            ->active()
            ->get(['class_id', 'school_id']);
        $schoolIds = $enrollments->pluck('school_id')->filter()->unique()->values();

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
            'upcoming_exams' => $upcomingExams,
            'pending_homeworks' => $pendingHomeworks,
            'recent_grades' => $recentGrades,
            'upcoming_events' => $upcomingEvents,
            'total_study_minutes_this_month' => $totalStudyMinutes,
            'total_study_hours_this_month' => round($totalStudyMinutes / 60, 2),
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
            $q->whereHas('student.termEnrollments', function ($subQ) use ($request) {
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
