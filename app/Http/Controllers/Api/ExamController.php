<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Exam;
use App\Traits\Filter;
use App\Traits\CommonCRUD;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use App\Services\ExamService;
use App\Models\TermEnrollment;
use App\Models\OnlineExamSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExamResource;
use Illuminate\Support\Facades\Validator;
use App\Services\OnlineExamSessionService;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExamController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct(
        protected OnlineExamSessionService $onlineExamSessionService
    )
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            if ($request->user()?->hasRole('student')) {
                abort(403, 'دانش‌آموز اجازه دسترسی به نمایش مدیریتی آزمون را ندارد.');
            }

            return $next($request);
        })->only(['show']);
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'show', 'examStudents']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    // tick
    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name', 'delivery_mode'],
            'filterKeysExact' => ['lesson_id', 'exam_category_id'],
            'filterDate' => [
                'created_at',
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'field_id',
                    'relationName' => 'academicLevels.academicField',
                ],
                [
                    'requestKey' => 'academic_level_id',
                    'relationName' => 'academicLevels',
                ],
                [
                    'requestKey' => 'class_id',
                    'relationName' => 'classes',
                ],
                [
                    'requestKey' => 'lesson_id',
                    'relationName' => 'lesson',
                ],
                [
                    'requestKey' => 'exam_category_id',
                    'relationName' => 'category',
                ],
            ],
            'scopes' => [
                'inSchool',
            ],
            'eagerLoads' => [
                'category',
                'lesson',
                'inPersonExamDetail',
                'onlineExamDetail',
                'classes',
                'academicLevels'
            ],
        ];

        return $this->commonIndex($request, Exam::class, $config);
    }

    public function studentOnlineExams(Request $request): JsonResponse
    {
        $studentId = auth()->id();
        $perPage = (int) $request->get('length', 100);

        $classIds = TermEnrollment::where('user_id', $studentId)->pluck('class_id');
        $academicLevelIds = SchoolClass::whereIn('id', $classIds)->pluck('academic_level_id');

        $query = Exam::query()
            ->where('delivery_mode', 'online')
            ->whereHas('onlineExamDetail', function ($detailQuery) {
                $detailQuery->where(function ($visibleQuery) {
                    $visibleQuery->whereNull('visible_at')
                        ->orWhere('visible_at', '<=', now());
                });
            })
            ->where(function ($accessQuery) use ($classIds, $academicLevelIds) {
                $accessQuery->where(function ($unrestrictedQuery) {
                    $unrestrictedQuery->whereDoesntHave('classes')
                        ->whereDoesntHave('academicLevels');
                })
                    ->orWhereHas('classes', function ($classQuery) use ($classIds) {
                        $classQuery->whereIn('classes.id', $classIds);
                    })
                    ->orWhereHas('academicLevels', function ($levelQuery) use ($academicLevelIds) {
                        $levelQuery->whereIn('academic_levels.id', $academicLevelIds);
                    });
            })
            ->with([
                'category',
                'lesson',
                'onlineExamDetail',
                'onlineExamSession' => fn ($q) => $q->where('student_id', $studentId),
            ])
            ->orderByDesc('created_at');

        $exams = $query->paginate($perPage);

        $exams->getCollection()->transform(function (Exam $exam) use ($studentId) {
            $session = $exam->onlineExamSession;

            if ($exam->onlineExamDetail) {
                $exam->onlineExamDetail->makeHidden(['content', 'solution']);
            }

            // 🎯 جادوی اصلاح وضعیت (Self-Healing)
            if ($session && $session->status === 'in_progress') {
                $isExpiredByDuration = false;

                if ($session->started_at && $session->duration_limit_seconds) {
                    $startTime = Carbon::parse($session->started_at);
                    $endTime = $startTime->copy()->addSeconds($session->duration_limit_seconds);
                    $isExpiredByDuration = $endTime->isPast();
                }

                $endsAt = $exam->onlineExamDetail?->ends_at;
                $isExamWindowClosed = $endsAt && Carbon::parse($endsAt)->isPast();

                // اگر زمان مجاز سشن تمام شده یا پنجره آزمون بسته شده است
                if ($isExpiredByDuration || $isExamWindowClosed) {
                    try {
                        $session = DB::transaction(function () use ($session, $studentId) {
                            return $this->onlineExamSessionService->submitAndGradeSession($session, $studentId);
                        });
                    } catch (Throwable $e) {
                        // در صورت بروز هرگونه خطای همزمانی، سشن قفل و اکسپایر شود تا در لوپ گیر نکند
                        $session->update([
                            'status' => 'expired',
                            'is_locked' => true,
                        ]);
                        $session->status = 'expired';
                        logger()->error("Failed to auto-grade expired session #{$session->id}: {$e->getMessage()}");
                    }
                }
            }

            $exam->setAttribute('latest_session', $session);
            $exam->setAttribute('session_status', $session?->status ?? 'not_started');

            return $exam;
        });

        return $this->jsonResponseOk($exams);
    }

    // tick
    public function myOnlineExams(Request $request, ExamService $examService): JsonResponse
    {
        $studentId = $request->user()->id;

        // پارامترهای اختیاری از فرانت‌اند
        $from = $request->query('from');
        $to = $request->query('to');

        // اگر پاس نداد، دیفالتش همون منطق مدنظرت باشه (مثلاً false یا true طبق دلخواهت)
        $onlyPendingOrInProgress = $request->boolean('only_pending_or_in_progress', false);

        $perPage = (int) $request->query('length', 10);

        // کوئری آماده و تمیز از سرویس
        $exams = $examService->listStudentRelatedExams(
            studentUserId: $studentId,
            from: $from,
            to: $to,
            onlyOnline: true,
            onlyPendingOrInProgress: $onlyPendingOrInProgress
        )
            ->paginate($perPage);

        return $this->jsonResponseOk($exams);
    }

    public function myGrades(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $request->merge([
            'delivery_mode' => 'in_person',
            'forStudent'    => $studentId,
        ]);

        $config = [
            'filterKeys' => ['name', 'delivery_mode'],
            'filterKeysExact' => ['lesson_id', 'exam_category_id'],
            'filterDate' => [
                'created_at',
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'field_id',
                    'relationName' => 'academicLevels.academicField',
                ],
                [
                    'requestKey' => 'academic_level_id',
                    'relationName' => 'academicLevels',
                ],
                [
                    'requestKey' => 'class_id',
                    'relationName' => 'classes',
                ],
                [
                    'requestKey' => 'lesson_id',
                    'relationName' => 'lesson',
                ],
                [
                    'requestKey' => 'exam_category_id',
                    'relationName' => 'category',
                ],
            ],
            'scopes' => [
                'inSchool',
                'forStudent',
            ],
            'eagerLoads' => [
                'category',
                'lesson',
                'inPersonExamDetail',
                'inPersonExamResult' => fn ($query) => $query->where('user_id', $studentId)
            ],
        ];

        return $this->commonIndex($request, Exam::class, $config);
    }

    // tick
    public function show(Request $request, $id): JsonResponse
    {
        $exam = Exam::with([
            'category',
            'lesson',
            'createdBy',
            'inPersonExamDetail',
            'onlineExamDetail.booklets',
            'onlineExamDetail.answerKeys',
            'classes',
            'academicLevels',
            'inPersonExamResults.student',
            'term.school',
            'term.parentTerm',
        ])->findOrFail($id);

        return $this->jsonResponseOk(new ExamResource($exam));
    }

    public function studentShow(
        Request $request,
        int $id
    ): JsonResponse {
        $student = $request->user();

        abort_unless(
            $student && $student->hasRole('student'),
            403,
            'این مسیر فقط برای دانش‌آموزان قابل استفاده است.'
        );

        /*
        * در این مرحله روابط حساس عمداً لود نمی‌شوند.
        *
        * استفاده از without باعث می‌شود اگر در مدل
        * OnlineExamDetail رابطه‌ای در $with تعریف شده باشد،
        * باز هم booklets و answerKeys به‌صورت خودکار لود نشوند.
        */
        $exam = Exam::query()
            ->whereKey($id)
            ->with([
                'category',
                'lesson',
                'createdBy',
                'inPersonExamDetail',

                'onlineExamDetail' => function ($query) {
                    $query->without([
                        'booklets',
                        'answerKeys',
                    ]);
                },

                'classes',
                'academicLevels',
                'term.school',
                'term.parentTerm',
            ])
            ->firstOrFail();

        /*
        * کنترل دسترسی دانش‌آموز به آزمون.
        *
        * این متد از OnlineExamSessionService استفاده می‌کند
        * و منطق تخصیص آزمون به کلاس را متمرکز نگه می‌دارد.
        */
        $this->onlineExamSessionService
            ->validateStudentAccess($exam, $student);

        /*
        * دریافت آخرین نشست دانش‌آموز برای این آزمون
        */
        $session = $this->onlineExamSessionService
            ->getLatestStudentSession($exam, $student);

        $hasSubmitted = $this->onlineExamSessionService
            ->hasSubmitted($session);

        $onlineDetail = $exam->onlineExamDetail;

        /*
        * مشخص‌کردن پایان آزمون.
        *
        * اگر ends_at وجود نداشته باشد، بعد از ثبت نهایی دانش‌آموز
        * می‌توان نتیجه را قابل نمایش در نظر گرفت.
        */
        $endsAt = $onlineDetail?->ends_at
            ? Carbon::parse($onlineDetail->ends_at)
            : null;

        $isExamFinished = $endsAt
            ? now()->greaterThan($endsAt)
            : $hasSubmitted;

        /*
        * اطلاعات حساس فقط وقتی نمایش داده می‌شوند که:
        *
        * ۱. دانش‌آموز پاسخ خود را ارسال کرده باشد
        * ۲. زمان کلی آزمون به پایان رسیده باشد
        */
        $canExposeSensitiveData = $hasSubmitted && $isExamFinished;

        if ($canExposeSensitiveData) {
            /*
            * اکنون لود کردن روابط حساس مجاز است.
            *
            * answerKeys فقط کلیدهای فعال را می‌گیرد؛
            * این همان الگوی موجود در OnlineExamSessionService است.
            */
            $exam->load([
                'onlineExamDetail.booklets',
                'onlineExamDetail.answerKeys' => function ($query) {
                    $query->where('is_active', true);
                },
            ]);
        } else {
            /*
            * دفاع دوم در برابر eager loading یا load شدن قبلی رابطه‌ها
            */
            if ($onlineDetail) {
                $onlineDetail->unsetRelation('booklets');
                $onlineDetail->unsetRelation('answerKeys');
            }
        }

        /*
        * فقط اطلاعات ضروری سشن را به خروجی اضافه می‌کنیم.
        *
        * کل مدل session را مستقیم برنمی‌گردانیم تا مواردی مثل
        * ip_address و user_agent بی‌دلیل در پاسخ API قرار نگیرند.
        */
        $exam->setAttribute(
            'participation_status',
            $session?->status ?? 'not_started'
        );

        $exam->setAttribute(
            'student_online_exam_session',
            $session
                ? [
                'id' => $session->id,
                'status' => $session->status,
                'attempt_number' => $session->attempt_number,
                'started_at' => $session->started_at,
                'submitted_at' => $session->submitted_at,
            ]
                : null
        );

        $exam->setAttribute(
            'sensitive_data_available',
            $canExposeSensitiveData
        );

        return $this->jsonResponseOk(
            new ExamResource($exam)
        );
    }

    public function examStudents(Request $request, $id): JsonResponse
    {
        $exam = Exam::with(['classes', 'academicLevels'])->findOrFail($id);

        $classIds = $exam->classes->pluck('id')->toArray();
        $academicLevelIds = $exam->academicLevels->pluck('id')->toArray();

        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'student'))
            ->where(function ($studentQuery) use ($classIds, $academicLevelIds) {
                if (!empty($classIds)) {
                    $studentQuery->whereHas('termEnrollments', function ($registrationQuery) use ($classIds) {
                        $registrationQuery->whereIn('term_enrollments.class_id', $classIds);
                    });
                }

                if (!empty($academicLevelIds)) {
                    $studentQuery->orWhereHas('termEnrollments', function ($registrationQuery) use ($academicLevelIds) {
                        $registrationQuery->whereHas('schoolClass', function ($classQuery) use ($academicLevelIds) {
                            $classQuery->whereIn('classes.academic_level_id', $academicLevelIds);
                        });
                    });
                }
            })
            ->with(['termEnrollments.schoolClass', 'studentProfile']);

        $perPage = (int) $request->get('length', 1000);

        return $this->jsonResponseOk($query->paginate($perPage));
    }

    // tick
    public function update(Request $request, Exam $exam, ExamService $examService): JsonResponse
    {
        $validated = $this->validateExam($request, true);

        $exam = DB::transaction(function () use ($exam, $validated, $request, $examService) {
            return $examService->updateExam($exam, $validated, $request);
        });

        return $this->show($request, $exam->id);
    }

    public function destroy(Exam $exam): JsonResponse
    {
        return $this->commonDestroy($exam);
    }

    public function storeWithOnlineDetail(Request $request, ExamService $examService): JsonResponse
    {
        $validated = $this->validateOnlineExam($request);

        $this->validateLessonOrBookletsExclusive($validated);

        $exam = DB::transaction(function () use ($validated, $request, $examService) {
            return $examService->createOnlineExam($validated, $request);
        });

        return $this->show($request, $exam->id);
    }

    // tick
    public function updateWithOnlineDetail(Request $request, Exam $exam, ExamService $examService): JsonResponse
    {
        $validated = $this->validateOnlineExam($request);

        $this->validateLessonOrBookletsExclusive($validated);

        $exam = DB::transaction(function () use ($exam, $validated, $request, $examService) {
            return $examService->updateOnlineExam($exam, $validated, $request);
        });

        return $this->show($request, $exam->id);
    }

    // tick
    public function storeWithInPersonDetailAndResults(Request $request, ExamService $examService): JsonResponse
    {
        $validated = $this->validateInPersonExam($request);

        $exam = DB::transaction(function () use ($validated, $request, $examService) {
            return $examService->createInPersonExam($validated, $request);
        });

        return $this->show($request, $exam->id);
    }

    protected function validateExam(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes' : 'required').'|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => ($isUpdate ? 'sometimes' : 'nullable').'|exists:lessons,id',
            'min_passing_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
            'delivery_mode' => ($isUpdate ? 'sometimes' : 'required').'|in:online,in_person',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'term_id' => 'sometimes|nullable|exists:academic_terms,id',
            'occurrence' => 'sometimes|nullable|integer|min:1',
            'created_by' => 'nullable|exists:users,id',
        ];

        return $request->validate($rules);
    }

    protected function validateOnlineExam(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'min_passing_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'term_id' => 'sometimes|nullable|exists:academic_terms,id',
            'occurrence' => 'sometimes|nullable|integer|min:1',
            'created_by' => 'nullable|exists:users,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'visible_at' => 'nullable|date',
            'answers_visible_at' => 'nullable|date',
            'content' => 'nullable|string',
            'solution' => 'nullable|string',
            'content_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
            'solution_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'booklets' => 'nullable|array',
            'booklets.*.lesson_id' => 'nullable|exists:lessons,id',
            'booklets.*.title' => 'required|string|max:255',
            'booklets.*.from_question' => 'nullable|integer|min:1',
            'booklets.*.to_question' => 'nullable|integer|min:1',
            'booklets.*.booklet_scores' => 'nullable|array',
            'answer_keys' => 'nullable|array',
            'answer_keys.*.question_number' => 'required|integer|min:1',
            'answer_keys.*.number_of_choices' => 'nullable|integer|min:2|max:10',
            'answer_keys.*.correct_option' => 'required|string|max:255',
            'answer_keys.*.weight' => 'nullable|numeric|min:0',
            'answer_keys.*.has_negative_mark' => 'sometimes|boolean',
            'answer_keys.*.is_active' => 'sometimes|boolean',
        ];

        $input = $request->all();

        $arrayFields = ['class_ids', 'academic_level_ids', 'booklets', 'answer_keys'];
        foreach ($arrayFields as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $decoded = json_decode($input[$field], true);
                if (is_array($decoded)) {
                    $input[$field] = $decoded;
                }
            }
        }

        return Validator::make($input, $rules)->validate();
    }

    /**
     * هم‌زمانی درس آزمون و دفترچه‌ها:
     * - اگر lesson_id برای آزمون انتخاب شده باشد، دفترچه‌ها نباید lesson_id داشته باشند.
     * - اگر lesson_id برای آزمون انتخاب نشده باشد، باید حداقل یک دفترچه با lesson_id ارائه شود.
     */
    protected function validateLessonOrBookletsExclusive(array $validated): void
    {
        $hasExamLesson = !empty($validated['lesson_id']);
        $booklets = $validated['booklets'] ?? [];

        if ($hasExamLesson && !empty($booklets)) {
            $bookletLessons = array_filter(array_column($booklets, 'lesson_id'));
            if (!empty($bookletLessons)) {
                throw ValidationException::withMessages([
                    'booklets' => 'اگر درس برای آزمون انتخاب شده است، نباید درس برای دفترچه‌ها انتخاب شود.',
                ]);
            }
        }

        if (!$hasExamLesson && empty($booklets)) {
            throw ValidationException::withMessages([
                'lesson_id' => 'باید یا درس برای آزمون یا دفترچه با درس برای آزمون انتخاب شود.',
            ]);
        }
    }

    protected function validateInPersonExam(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'min_passing_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'created_by' => 'nullable|exists:users,id',
            'held_at' => 'required|date',
            'is_descriptive' => 'sometimes|boolean',
            'results_visible_at' => 'nullable|date',
            'term_id' => 'sometimes|nullable|exists:academic_terms,id',
            'occurrence' => 'sometimes|nullable|integer|min:1',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'results' => 'required|array|min:1',
            'results.*.user_id' => 'required|exists:users,id',
            'results.*.raw_score' => 'required|numeric|min:0',
            'results.*.scaled_score' => 'required|numeric|min:0',
            'results.*.t_score' => 'nullable|numeric',
        ];

        return $request->validate($rules);
    }

}
