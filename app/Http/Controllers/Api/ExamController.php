<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\InPersonExamDetail;
use App\Models\InPersonExamResult;
use App\Models\OnlineExamDetail;
use App\Models\SchoolClass;
use App\Models\AcademicTerm;
use App\Models\ExamCategoryTermLimit;
use App\Models\User;
use App\Models\TermEnrollment;
use App\Models\OnlineExamBooklet;
use App\Models\OnlineExamAnswerKey;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'show', 'examStudents']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name', 'delivery_mode'],
            'filterKeysExact' => ['lesson_id', 'exam_category_id'],
            'filterDate' => [
                'created_at',
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'category_title',
                    'relationName' => 'category',
                    'relationColumn' => 'title',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'lesson_name',
                    'relationName' => 'lesson',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'classes',
                ],
                [
                    'requestKey' => 'academic_level_ids',
                    'relationName' => 'academicLevels',
                ],
            ],
            'eagerLoads' => [
                'category',
                'lesson',
//                'createdBy',
                'inPersonExamDetail',
                'onlineExamDetail',
//                'answerKeys',
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
                'onlineExamSessions' => function ($sessionQuery) use ($studentId) {
                    $sessionQuery->where('student_id', $studentId)
                        ->orderByDesc('attempt_number');
                },
            ])
            ->orderByDesc('created_at');

        $exams = $query->paginate($perPage);

        $exams->getCollection()->transform(function (Exam $exam) {
            $latestSession = $exam->onlineExamSessions->first();

            if ($exam->onlineExamDetail) {
                $exam->onlineExamDetail->makeHidden(['content', 'solution']);
            }

            $exam->setAttribute('latest_session', $latestSession);
            $exam->setAttribute('session_status', $latestSession?->status ?? 'not_started');

            return $exam;
        });

        return $this->jsonResponseOk($exams);
    }

    public function myExams(Request $request): JsonResponse
    {
        $studentId = auth()->id();

        $config = [
            'filterKeys' => [
                'name',
                'description',
            ],

            'filterDate' => [
                'created_at',
            ],

            'filterKeysExact' => [
                'lesson_id',
                'exam_category_id',
                'delivery_mode',
            ],

            'filterRelationKeys' => [
                [
                    'requestKey' => 'lesson_name',
                    'relationName' => 'lesson',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'category_name',
                    'relationName' => 'category',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'academic_level_id',
                    'relationName' => 'academicLevels',
                    'relationColumn' => 'academic_levels.id',
                    'exact' => true,
                ],
                [
                    'requestKey' => 'class_id',
                    'relationName' => 'classes',
                    'relationColumn' => 'classes.id',
                    'exact' => true,
                ],
            ],

            'eagerLoads' => [
                'category',
                'lesson',
                'academicLevels',
                'classes',
            ],
        ];

        $modelQuery = Exam::query()
            /*
             * آزمون‌هایی که دانش‌آموز اجازه دیدن آن‌ها را دارد:
             *
             * 1. آزمون عمومی:
             *    به هیچ کلاس یا پایه‌ای متصل نشده باشد.
             *
             * 2. آزمون مخصوص کلاس دانش‌آموز
             *
             * 3. آزمون مخصوص پایه‌ای که دانش‌آموز در کلاس‌های آن پایه ثبت‌نام است
             */
            ->where(function ($query) use ($studentId) {
                $query
                    ->whereHas('classes.userClassRegistrations', function ($classQuery) use ($studentId) {
                        $classQuery->where('user_id', $studentId);
                    })
                    ->orWhereHas('academicLevels.classes.userClassRegistrations', function ($classQuery) use ($studentId) {
                        $classQuery->where('user_id', $studentId);
                    })
                    ->orWhere(function ($globalExamQuery) {
                        $globalExamQuery
                            ->doesntHave('classes')
                            ->doesntHave('academicLevels');
                    });
            });

        /*
         * اعمال فیلترها، جستجوها و eager loadingهای عمومی
         */
        $this->buildFilterQuery(
            $request,
            $modelQuery,
            Exam::class,
            $this->getConfigArray($config)
        );

        /*
         * فقط نتیجه‌ی همین دانش‌آموز را لود می‌کنیم.
         *
         * برای آزمون حضوری:
         * نتیجه از طریق in_person_exam_details به in_person_exam_results
         * مرتبط است؛ بنابراین رابطه Exam باید به‌درستی تعریف شده باشد.
         */
        $modelQuery
            ->with([
                'inPersonExamDetail',

                'inPersonExamResults' => function ($resultQuery) use ($studentId) {
                    $resultQuery
                        ->where('user_id', $studentId)
                        ->latest('created_at');
                },

                'onlineExamDetail',

                'onlineExamSessions' => function ($sessionQuery) use ($studentId) {
                    $sessionQuery
                        ->where('student_id', $studentId)
                        ->latest('attempt_number');
                },
            ])
            ->latest('created_at');

        $perPage = (int) $request->get('length', 10);

        $exams = $modelQuery->paginate($perPage);

        $exams->getCollection()->transform(function (Exam $exam) {
            $latestInPersonResult = $exam->inPersonExamResults->first();
            $latestOnlineSession = $exam->onlineExamSessions->first();

            $result = null;

            if ($exam->delivery_mode === 'in_person') {
                if ($latestInPersonResult) {
                    $result = [
                        'type' => 'in_person',
                        'status' => 'recorded',
                        'has_result' => true,

                        'raw_score' => $latestInPersonResult->raw_score,
                        'scaled_score' => $latestInPersonResult->scaled_score,
                        'z_score' => $latestInPersonResult->z_score,

                        'recorded_at' => $latestInPersonResult->created_at,
                    ];
                }
            } elseif ($exam->delivery_mode === 'online') {
                if ($latestOnlineSession) {
                    $hasScore = $latestOnlineSession->score !== null
                        && $latestOnlineSession->status === 'graded';

                    $result = [
                        'type' => 'online',
                        'status' => $latestOnlineSession->status,
                        'has_result' => $hasScore,

                        'score' => $hasScore
                            ? $latestOnlineSession->score
                            : null,

                        'percent' => $hasScore
                            ? $latestOnlineSession->percent
                            : null,

                        'attempt_number' => $latestOnlineSession->attempt_number,
                        'started_at' => $latestOnlineSession->started_at,
                        'submitted_at' => $latestOnlineSession->submitted_at,
                    ];
                }
            }

            /*
             * اطلاعات اضافی را به شکل یکسان برای API اضافه می‌کنیم.
             */
            $exam->setAttribute('my_result', $result);

            /*
             * اگر آزمون نتیجه نداشته باشد، مقدار score برابر null است.
             * این باعث می‌شود آزمون همچنان در لیست باقی بماند.
             */
            $exam->setAttribute(
                'score',
                $this->extractScore(
                    $latestInPersonResult,
                    $latestOnlineSession
                )
            );

            $exam->setAttribute(
                'has_result',
                $result !== null && ($result['has_result'] ?? false)
            );

            /*
             * چون نتیجه داخل my_result قرار گرفت،
             * بهتر است رابطه‌های خام در خروجی نیایند.
             */
            $exam->makeHidden([
                'inPersonExamResults',
                'onlineExamSessions',
            ]);

            return $exam;
        });

        return $this->jsonResponseOk($exams);
    }
    private function extractScore ($inPersonResult = null, $onlineSession = null): ?array
    {
        if ($inPersonResult) {
            return [
                'raw_score' => $inPersonResult->raw_score,
                'scaled_score' => $inPersonResult->scaled_score,
                'z_score' => $inPersonResult->z_score,
            ];
        }

        if ($onlineSession) {
            return [
                'score' => $onlineSession->score,
                'percent' => $onlineSession->percent,
                'status' => $onlineSession->status,
            ];
        }

        return null;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateExam($request);

        return DB::transaction(function () use ($request, $validated) {
            $exam = Exam::create($validated);

            if ($exam->term_id && ($validated['occurrence'] ?? null) === null) {
                $exam->occurrence = $this->enforceTermOccurrence(
                    $exam->id,
                    $exam->exam_category_id,
                    $exam->term_id
                );
                $exam->save();
            }

            $this->storeDetail($exam, $request);

            if ($request->filled('class_ids')) {
                $exam->classes()->sync($request->class_ids, false);
            }

            if ($request->filled('academic_level_ids')) {
                $exam->academicLevels()->sync($request->academic_level_ids, false);
            }

            return $this->show($request, $exam->id);
        });
    }

    public function show(Request $request, $id): JsonResponse
    {
        $exam = Exam::with([
            'category',
             'lesson',
             'createdBy',
             'inPersonExamDetail',
             'onlineExamDetail.booklets',
             'answerKeys',
             'classes',
             'academicLevels',
             'inPersonExamResults.student',
            'term.school',
            'term.parentTerm',
        ])->findOrFail($id);

        return $this->jsonResponseOk($exam);
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
                    $studentQuery->whereHas('userClassRegistrations', function ($registrationQuery) use ($classIds) {
                        $registrationQuery->whereIn('term_enrollments.class_id', $classIds);
                    });
                }

                if (!empty($academicLevelIds)) {
                    $studentQuery->orWhereHas('userClassRegistrations', function ($registrationQuery) use ($academicLevelIds) {
                        $registrationQuery->whereHas('schoolClass', function ($classQuery) use ($academicLevelIds) {
                            $classQuery->whereIn('classes.academic_level_id', $academicLevelIds);
                        });
                    });
                }
            })
            ->with(['userClassRegistrations.schoolClass', 'studentProfile']);

        $perPage = (int) $request->get('length', 1000);

        return $this->jsonResponseOk($query->paginate($perPage));
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        $validated = $this->validateExam($request, true);

        return DB::transaction(function () use ($exam, $validated, $request) {
            $exam->update($validated);

            if ($request->filled('term_id')) {
                $exam->term_id = $request->input('term_id');
            } elseif ($validated['term_id'] ?? null) {
                $exam->term_id = $validated['term_id'];
            }
            if (array_key_exists('occurrence', $validated) && $validated['occurrence'] !== null) {
                $exam->occurrence = $validated['occurrence'];
            } elseif ($exam->term_id) {
                $exam->occurrence = $this->enforceTermOccurrence(
                    $exam->id,
                    $exam->exam_category_id,
                    $exam->term_id
                );
            }
            $exam->save();

            $this->updateDetail($exam, $request);

            if ($request->filled('class_ids')) {
                $exam->classes()->sync($request->class_ids);
            }

            if ($request->filled('academic_level_ids')) {
                $exam->academicLevels()->sync($request->academic_level_ids);
            }

            return $this->show($request, $exam->id);
        });
    }

    public function destroy(Exam $exam): JsonResponse
    {
        return $this->commonDestroy($exam);
    }

    public function storeWithOnlineDetail(Request $request): JsonResponse
    {
        $validated = $this->validateOnlineExam($request);

        $this->validateLessonOrBookletsExclusive($validated);

        return DB::transaction(function () use ($validated, $request) {
            $examData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'lesson_id' => $validated['lesson_id'],
                'min_passing_score' => $validated['min_passing_score'] ?? null,
                'max_score' => $validated['max_score'] ?? null,
                'delivery_mode' => 'online',
                'exam_category_id' => $validated['exam_category_id'],
                'created_by' => $validated['created_by'] ?? $request->user()->id,
            ];

            $exam = Exam::create($examData);

            $occurrence = $this->enforceTermOccurrence(
                $exam->id,
                $validated['exam_category_id'],
                $validated['term_id'] ?? null
            );
            $exam->term_id = $validated['term_id'] ?? null;
            $exam->occurrence = $validated['occurrence'] ?? $occurrence;
            $exam->save();

            $content = $this->processExamContent($request, 'content');
            $solution = $this->processExamContent($request, 'solution');

            $onlineDetail = OnlineExamDetail::create([
                'exam_id' => $exam->id,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
                'visible_at' => $validated['visible_at'] ?? null,
                'answers_visible_at' => $validated['answers_visible_at'] ?? null,
                'content' => $content,
                'solution' => $solution,
                'created_by' => $request->user()->id,
            ]);

            if (!empty($validated['booklets'])) {
                $examHasLesson = !empty($validated['lesson_id']);
                foreach ($validated['booklets'] as $booklet) {
                    OnlineExamBooklet::create([
                        'online_exam_id' => $onlineDetail->id,
                        'lesson_id' => $examHasLesson ? null : ($booklet['lesson_id'] ?? null),
                        'title' => $booklet['title'],
                        'from_question' => $booklet['from_question'] ?? null,
                        'to_question' => $booklet['to_question'] ?? null,
                        'booklet_scores' => $booklet['booklet_scores'] ?? null,
                    ]);
                }
            }

            if (!empty($validated['answer_keys'])) {
                foreach ($validated['answer_keys'] as $answerKey) {
                    OnlineExamAnswerKey::create([
                        'exam_id' => $exam->id,
                        'question_number' => $answerKey['question_number'],
                        'number_of_choices' => $answerKey['number_of_choices'] ?? 4,
                        'correct_option' => $answerKey['correct_option'],
                        'weight' => $answerKey['weight'] ?? 0,
                        'has_negative_mark' => $answerKey['has_negative_mark'] ?? false,
                        'is_active' => $answerKey['is_active'] ?? true,
                    ]);
                }
            }

            if (!empty($validated['class_ids'])) {
                $exam->classes()->sync($validated['class_ids'], false);
            }

            if (!empty($validated['academic_level_ids'])) {
                $exam->academicLevels()->sync($validated['academic_level_ids'], false);
            }

            return $this->show($request, $exam->id);
        });
    }

    public function updateWithOnlineDetail(Request $request, Exam $exam): JsonResponse
    {
        $validated = $this->validateOnlineExam($request);

        $this->validateLessonOrBookletsExclusive($validated);

        return DB::transaction(function () use ($exam, $validated, $request) {
            $examData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'lesson_id' => $validated['lesson_id'],
                'min_passing_score' => $validated['min_passing_score'] ?? null,
                'max_score' => $validated['max_score'] ?? null,
                'delivery_mode' => 'online',
                'exam_category_id' => $validated['exam_category_id'],
                'created_by' => $validated['created_by'] ?? $request->user()->id,
            ];

            $exam->update($examData);

            $exam->term_id = $validated['term_id'] ?? $exam->term_id;
            if (array_key_exists('occurrence', $validated) && $validated['occurrence'] !== null) {
                $exam->occurrence = $validated['occurrence'];
            } elseif ($validated['term_id'] ?? null) {
                $exam->occurrence = $this->enforceTermOccurrence(
                    $exam->id,
                    $exam->exam_category_id,
                    $validated['term_id']
                );
            }
            $exam->save();

            $existingDetail = OnlineExamDetail::where('exam_id', $exam->id)->first();

            $updateData = [
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
                'visible_at' => $validated['visible_at'] ?? null,
                'answers_visible_at' => $validated['answers_visible_at'] ?? null,
                'created_by' => $request->user()->id,
            ];

            if ($request->has('content') || $request->hasFile('content_file')) {
                $content = $this->processExamContent($request, 'content');
                if (!$request->hasFile('content_file') && $existingDetail?->content && $content) {
                    if (isset($existingDetail->content['path']) && !isset($content['path'])) {
                        $content['path'] = $existingDetail->content['path'];
                    }
                }
                $updateData['content'] = $content;
            }

            if ($request->has('solution') || $request->hasFile('solution_file')) {
                $solution = $this->processExamContent($request, 'solution');
                if (!$request->hasFile('solution_file') && $existingDetail?->solution && $solution) {
                    if (isset($existingDetail->solution['path']) && !isset($solution['path'])) {
                        $solution['path'] = $existingDetail->solution['path'];
                    }
                }
                $updateData['solution'] = $solution;
            }

            OnlineExamDetail::updateOrCreate(
                ['exam_id' => $exam->id],
                $updateData
            );

            if (isset($validated['booklets'])) {
                $exam->onlineExamDetail?->booklets()->delete();
                $examHasLesson = !empty($validated['lesson_id']);
                foreach ($validated['booklets'] as $booklet) {
                    OnlineExamBooklet::create([
                        'online_exam_id' => $exam->onlineExamDetail->id,
                        'lesson_id' => $examHasLesson ? null : ($booklet['lesson_id'] ?? null),
                        'title' => $booklet['title'],
                        'from_question' => $booklet['from_question'] ?? null,
                        'to_question' => $booklet['to_question'] ?? null,
                        'booklet_scores' => $booklet['booklet_scores'] ?? null,
                    ]);
                }
            }

            if (isset($validated['answer_keys'])) {
                OnlineExamAnswerKey::where('exam_id', $exam->id)->delete();
                foreach ($validated['answer_keys'] as $answerKey) {
                    OnlineExamAnswerKey::create([
                        'exam_id' => $exam->id,
                        'question_number' => $answerKey['question_number'],
                        'number_of_choices' => $answerKey['number_of_choices'] ?? 4,
                        'correct_option' => $answerKey['correct_option'],
                        'weight' => $answerKey['weight'] ?? 0,
                        'has_negative_mark' => $answerKey['has_negative_mark'] ?? false,
                        'is_active' => $answerKey['is_active'] ?? true,
                    ]);
                }
            }

            if (!empty($validated['class_ids'])) {
                $exam->classes()->sync($validated['class_ids']);
            }

            if (!empty($validated['academic_level_ids'])) {
                $exam->academicLevels()->sync($validated['academic_level_ids']);
            }

            return $this->show($request, $exam->id);
        });
    }

    public function storeWithInPersonDetailAndResults(Request $request): JsonResponse
    {
        $validated = $this->validateInPersonExam($request);

        return DB::transaction(function () use ($validated, $request) {
            $examData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'lesson_id' => $validated['lesson_id'],
                'min_passing_score' => $validated['min_passing_score'] ?? null,
                'max_score' => $validated['max_score'] ?? null,
                'delivery_mode' => 'in_person',
                'exam_category_id' => $validated['exam_category_id'],
                'created_by' => $validated['created_by'] ?? $request->user()->id,
            ];

            $exam = Exam::create($examData);

            $occurrence = $this->enforceTermOccurrence(
                $exam->id,
                $validated['exam_category_id'],
                $validated['term_id'] ?? null
            );
            $exam->term_id = $validated['term_id'] ?? null;
            $exam->occurrence = $validated['occurrence'] ?? $occurrence;
            $exam->save();

            $detail = InPersonExamDetail::create([
                'exam_id' => $exam->id,
                'held_at' => $validated['held_at'],
                'is_descriptive' => $validated['is_descriptive'] ?? false,
                'results_visible_at' => $validated['results_visible_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if (!empty($validated['results'])) {
                foreach ($validated['results'] as $result) {
                    InPersonExamResult::create([
                        'in_person_exam_id' => $detail->id,
                        'user_id' => $result['user_id'],
                        'raw_score' => $result['raw_score'] ?? null,
                        'scaled_score' => $result['scaled_score'] ?? null,
                        'recorded_by' => $request->user()->id,
                        'z_score' => $result['z_score'] ?? null,
                    ]);
                }
            }

            if ($request->filled('class_ids')) {
                $exam->classes()->sync($request->class_ids, false);
            }

            if ($request->filled('academic_level_ids')) {
                $exam->academicLevels()->sync($request->academic_level_ids, false);
            }

            return $this->show($request, $exam->id);
        });
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
            'results.*.z_score' => 'nullable|numeric',
        ];

        return $request->validate($rules);
    }

    protected function storeDetail(Exam $exam, Request $request): void
    {
        if ($exam->isInPerson()) {
            InPersonExamDetail::create([
                'exam_id' => $exam->id,
                'held_at' => $request->input('held_at'),
                'is_descriptive' => $request->boolean('is_descriptive', false),
                'results_visible_at' => $request->input('results_visible_at'),
                'created_by' => $request->user()->id,
            ]);
        } elseif ($exam->isOnline()) {
            OnlineExamDetail::create([
                'exam_id' => $exam->id,
                'starts_at' => $request->input('starts_at'),
                'ends_at' => $request->input('ends_at'),
                'time_limit_minutes' => $request->input('time_limit_minutes'),
                'visible_at' => $request->input('visible_at'),
                'answers_visible_at' => $request->input('answers_visible_at'),
                'content' => $request->input('content'),
                'solution' => $request->input('solution'),
                'created_by' => $request->user()->id,
            ]);
        }
    }

    protected function updateDetail(Exam $exam, Request $request): void
    {
        if ($exam->isInPerson()) {
            InPersonExamDetail::updateOrCreate(
                ['exam_id' => $exam->id],
                [
                    'held_at' => $request->input('held_at'),
                    'is_descriptive' => $request->boolean('is_descriptive', false),
                    'results_visible_at' => $request->input('results_visible_at'),
                    'created_by' => $request->user()->id,
                ]
            );
        } elseif ($exam->isOnline()) {
            $existingDetail = OnlineExamDetail::where('exam_id', $exam->id)->first();

            $updateData = [
                'starts_at' => $request->input('starts_at'),
                'ends_at' => $request->input('ends_at'),
                'time_limit_minutes' => $request->input('time_limit_minutes'),
                'visible_at' => $request->input('visible_at'),
                'answers_visible_at' => $request->input('answers_visible_at'),
                'created_by' => $request->user()->id,
            ];

            $mustDeleteOldContentFile = false;
            $mustDeleteOldSolutionFile = false;
            $oldContentPath = null;
            $oldSolutionPath = null;
            // ================= مدیریت Content =================
            if ($request->has('content') || $request->hasFile('content_file')) {
                // استخراج مسیر فایل قدیمی به صورت امن
                $oldContent = $existingDetail?->content;
                $oldContentPath = is_array($oldContent) ? ($oldContent['path'] ?? null) : null;

                // پردازش محتوای جدید (اگر فایل جدید باشد، مسیر جدید را جایگزین می‌کند)
                $newContent = $this->processExamContent($request, 'content');
                $newContent = is_array($newContent) ? $newContent : [];

                // اگر فایل جدیدی آپلود نشده، مسیر فایل قدیمی را حفظ کن
                if (!$request->hasFile('content_file') && $oldContentPath) {
                    $newContent['path'] = $oldContentPath;
                }

                $updateData['content'] = !empty($newContent) ? $newContent : null;

                // ⚠️ حذف فایل قدیمی از استوریج فقط در صورتی که فایل جدید آپلود شده باشد
                if ($request->hasFile('content_file') && $oldContentPath) {
                    $mustDeleteOldContentFile = true;
                }
            }

            // ================= مدیریت Solution =================
            if ($request->has('solution') || $request->hasFile('solution_file')) {
                // استخراج مسیر فایل قدیمی به صورت امن
                $oldSolution = $existingDetail?->solution;
                $oldSolutionPath = is_array($oldSolution) ? ($oldSolution['path'] ?? null) : null;

                // پردازش محتوای جدید
                $newSolution = $this->processExamContent($request, 'solution');
                $newSolution = is_array($newSolution) ? $newSolution : [];

                // اگر فایل جدیدی آپلود نشده، مسیر فایل قدیمی را حفظ کن
                if (!$request->hasFile('solution_file') && $oldSolutionPath) {
                    $newSolution['path'] = $oldSolutionPath;
                }

                $updateData['solution'] = !empty($newSolution) ? $newSolution : null;

                // ⚠️ حذف فایل قدیمی از استوریج فقط در صورتی که فایل جدید آپلود شده باشد
                if ($request->hasFile('solution_file') && $oldSolutionPath) {
                    $mustDeleteOldSolutionFile = true;
                }
            }

            OnlineExamDetail::updateOrCreate(
                ['exam_id' => $exam->id],
                $updateData
            );
            if ($mustDeleteOldContentFile) {
                Storage::disk('public')->delete($oldContentPath);
            }
            if ($mustDeleteOldSolutionFile) {
                Storage::disk('public')->delete($oldSolutionPath);
            }
        }
    }

    /**
     * بررسی و اعمال محدودیت تعداد برگزاری آزمون یک دسته‌بندی در یک ترم.
     * اگر max_occurrences تعریف نشده باشد، رقم اولویت (occurrence) به‌صورت خودکار
     * برابر تعداد پیشین + ۱ محاسبه می‌شود. اگر ۰ باشد، برگزاری غیرممکن است.
     * در غیر این صورت اگر تعداد برگزاری‌ها به حداکثر برسد، خطا می‌دهد.
     */
    protected function enforceTermOccurrence (int $examId, int $categoryId, ?int $termId): ?int
    {
        if (!$termId) {
            return null;
        }

        $term = AcademicTerm::find($termId);

        // شناسایی ترم‌های مرتبط برای جستجوی محدودیت: خود ترم + ترم والد (در صورتی که زیرترم باشد)
        $limitTermIds = [$termId];
        if ($term && $term->parent_id) {
            $limitTermIds[] = $term->parent_id;
        }

        $limit = ExamCategoryTermLimit::where('exam_category_id', $categoryId)
            ->whereIn('term_id', $limitTermIds)
            ->latest('id')
            ->first();

        if (!$limit) {
            // محدودیتی تعریف نشده؛ رقم اولویت به‌صورت خودکار محاسبه می‌شود
            $count = Exam::where('exam_category_id', $categoryId)
                ->where('term_id', $termId)
                ->where('id', '!=', $examId)
                ->count();

            return $count + 1;
        }

        if ($limit->max_occurrences === 0) {
            throw ValidationException::withMessages([
                'term_id' => "برگزاری آزمون در این ترم ممنوع شده است (حداکثر ۰ بار).",
            ]);
        }

        $count = Exam::where('exam_category_id', $categoryId)
            ->where('term_id', $termId)
            ->where('id', '!=', $examId)
            ->count();

        if ($limit->isUnlimited()) {
            return $count + 1;
        }

        if ($count >= $limit->max_occurrences) {
            throw ValidationException::withMessages([
                'term_id' => sprintf(
                    'تعداد برگزاری آزمون در این ترم به حداکثر (%d) رسیده است.',
                    $limit->max_occurrences
                ),
            ]);
        }

        return $count + 1;
    }

    private function processExamContent(Request $request, string $field): ?array
    {
        $content = $request->input($field);
        $content = is_string($content) ? json_decode($content, true) : $content;

        $fileField = $field . '_file';
        if ($request->hasFile($fileField)) {
            $file = $request->file($fileField);
            $path = $this->storeExamFile($file, $field);
            $content = $content ?? [];
            $content['path'] = $path;
            if (!isset($content['type'])) {
                $content['type'] = in_array($file->getClientMimeType(), ['application/pdf']) ? 'pdf' : 'image';
            }
        }

        return $content;
    }

    private function storeExamFile(UploadedFile $file, string $prefix = ''): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = sprintf('exam_%s_%s.%s', $prefix, uniqid(), $extension);
        $directory = 'exam-files';

        return $file->storeAs($directory, $filename, 'public');
    }
}
