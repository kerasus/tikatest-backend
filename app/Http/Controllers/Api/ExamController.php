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

        return DB::transaction(function () use ($validated) {
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
