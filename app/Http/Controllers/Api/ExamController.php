<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\InPersonExamDetail;
use App\Models\InPersonExamResult;
use App\Models\OnlineExamDetail;
use App\Models\OnlineExamBooklet;
use App\Models\OnlineExamAnswerKey;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;

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
            'eagerLoads' => ['category', 'lesson', 'createdBy', 'inPersonExamDetail', 'onlineExamDetail', 'answerKeys', 'classes', 'academicLevels'],
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
            'category',
             'lesson',
             'createdBy',
             'inPersonExamDetail',
             'onlineExamDetail',
             'answerKeys',
             'classes',
             'academicLevels',
             'inPersonExamResults.student',
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
                foreach ($validated['booklets'] as $booklet) {
                    OnlineExamBooklet::create([
                        'online_exam_id' => $onlineDetail->id,
                        'lesson_id' => $booklet['lesson_id'] ?? null,
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
                foreach ($validated['booklets'] as $booklet) {
                    OnlineExamBooklet::create([
                        'online_exam_id' => $exam->onlineExamDetail->id,
                        'lesson_id' => $booklet['lesson_id'] ?? null,
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
            'content' => 'nullable|string',
            'solution' => 'nullable|string',
            'content_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp',
            'solution_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp',
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
