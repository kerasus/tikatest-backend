<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\InPersonExamDetail;
use App\Models\OnlineExamDetail;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'show']);
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
            'eagerLoads' => ['category', 'lesson', 'createdBy', 'inPersonDetail', 'onlineDetail', 'answerKeys', 'classes', 'academicLevels'],
        ];

        return $this->commonIndex($request, Exam::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateExam($request);

        return DB::transaction(function () use ($request, $validated) {
            $exam = Exam::create($validated);

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
            'category', 'lesson', 'createdBy',
            'inPersonDetail', 'onlineDetail', 'answerKeys',
            'classes', 'academicLevels', 'inPersonResults',
        ])->findOrFail($id);

        return $this->jsonResponseOk($exam);
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        $validated = $this->validateExam($request, true);

        return DB::transaction(function () use ($exam, $validated, $request) {
            $exam->update($validated);

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

            OnlineExamDetail::create([
                'exam_id' => $exam->id,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
                'visible_at' => $validated['visible_at'] ?? null,
                'answers_visible_at' => $validated['answers_visible_at'] ?? null,
                'content' => $validated['content'] ?? null,
                'solution' => $validated['solution'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if ($request->filled('class_ids')) {
                $exam->classes()->sync($request->class_ids, false);
            }

            if ($request->filled('academic_level_ids')) {
                $exam->academicLevels()->sync($request->academic_level_ids, false);
            }

            return $this->show($request, $exam->id);
        });
    }

    public function storeWithInPersonDetailAndResults(Request $request): JsonResponse
    {
        $validated = $this->validateInPersonExam($request);

        var_dump('hi1');
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

            var_dump('hi2');
            $exam = Exam::create($examData);

            var_dump('hi3');
            $detail = InPersonExamDetail::create([
                'exam_id' => $exam->id,
                'held_at' => $validated['held_at'],
                'is_descriptive' => $validated['is_descriptive'] ?? false,
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
            'created_by' => 'nullable|exists:users,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'visible_at' => 'nullable|date',
            'answers_visible_at' => 'nullable|date',
            'content' => 'nullable|array',
            'solution' => 'nullable|array',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
        ];

        return $request->validate($rules);
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
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'results' => 'required|array|min:1',
            'results.*.user_id' => 'required|exists:users,id',
            'results.*.raw_score' => 'nullable|numeric|min:0',
            'results.*.scaled_score' => 'nullable|numeric|min:0',
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
                    'created_by' => $request->user()->id,
                ]
            );
        } elseif ($exam->isOnline()) {
            OnlineExamDetail::updateOrCreate(
                ['exam_id' => $exam->id],
                [
                    'starts_at' => $request->input('starts_at'),
                    'ends_at' => $request->input('ends_at'),
                    'time_limit_minutes' => $request->input('time_limit_minutes'),
                    'visible_at' => $request->input('visible_at'),
                    'answers_visible_at' => $request->input('answers_visible_at'),
                    'content' => $request->input('content'),
                    'solution' => $request->input('solution'),
                    'created_by' => $request->user()->id,
                ]
            );
        }
    }
}
