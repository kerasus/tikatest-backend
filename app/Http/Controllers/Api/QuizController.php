<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizClassAssignment;
use App\Services\QuizScoringService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuizController extends Controller
{
    use Filter, CommonCRUD;

    private QuizScoringService $scoringService;

    public function __construct(QuizScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
        $this->middleware('auth:sanctum');
        $this->middleware('permission:quizzes.view')->only(['index', 'show']);
        $this->middleware('permission:quizzes.create')->only(['store']);
        $this->middleware('permission:quizzes.update')->only(['update']);
        $this->middleware('permission:quizzes.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'quiz_type',
            ],
            'filterKeysExact' => [
                'is_visible',
                'quiz_type',
            ],
            'filterDate' => [
                'start_time',
                'end_time',
                'created_at',
            ],
            'eagerLoads' => ['quizClassAssignments'],
        ];

        return $this->commonIndex($request, Quiz::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'required|string|max:255',
            'time_limit' => 'nullable|integer',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'description' => 'nullable|string',
            'is_visible' => 'boolean',
            'quiz_type' => 'nullable|string|max:50',
            'contentType' => 'nullable|in:text,image,pdf',
            'contentValue' => 'nullable',
            'solutionType' => 'nullable|in:text,image,pdf',
            'solutionValue' => 'nullable',
            'show_answer_date' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        $data = $request->only([
            'school_id',
            'name',
            'time_limit',
            'start_time',
            'end_time',
            'description',
            'is_visible',
            'quiz_type',
            'show_answer_date',
            'no_score_questions',
        ]);

        // Handle content
        if ($request->filled('contentType')) {
            $data['content'] = $this->buildContentPayload($request->contentType, $request->contentValue);
        }

        // Handle solution
        if ($request->filled('solutionType')) {
            $data['solution'] = $this->buildContentPayload($request->solutionType, $request->solutionValue);
        }

        $quiz = Quiz::create($data);

        return $this->jsonResponseOk($quiz->load(['quizClassAssignments.schoolClass', 'answerKeys', 'sessions']));
    }

    public function show(int $id): JsonResponse
    {
        $quiz = Quiz::with([
            'quizClassAssignments.schoolClass',
            'quizClassAssignments.academicLevel',
            'answerKeys',
            'sessions'
        ])->findOrFail($id);

        return $this->jsonResponseOk($quiz);
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'sometimes|required|string|max:255',
            'time_limit' => 'nullable|integer',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'description' => 'nullable|string',
            'is_visible' => 'boolean',
            'quiz_type' => 'nullable|string|max:50',
            'contentType' => 'nullable|in:text,image,pdf',
            'contentValue' => 'nullable',
            'solutionType' => 'nullable|in:text,image,pdf',
            'solutionValue' => 'nullable',
            'show_answer_date' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        $data = $request->only([
            'school_id',
            'name',
            'time_limit',
            'start_time',
            'end_time',
            'description',
            'is_visible',
            'quiz_type',
            'show_answer_date',
            'no_score_questions',
        ]);

        // Handle content if provided
        if ($request->has('contentType')) {
            $data['content'] = $this->buildContentPayload($request->contentType, $request->contentValue);
        }

        // Handle solution if provided
        if ($request->has('solutionType')) {
            $data['solution'] = $this->buildContentPayload($request->solutionType, $request->solutionValue);
        }

        $quiz->update($data);

        // Recalculate all session percentages after quiz update
        $this->scoringService->recalculateAllSessions($quiz);

        return $this->show($quiz->id);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        return $this->commonDestroy($quiz);
    }

    public function resultsWithRank(Request $request, $quizId): JsonResponse
    {
        $quiz = Quiz::with(['sessions', 'quizClassAssignments.schoolClass'])->findOrFail($quizId);

        $ranked = $this->scoringService->getRankings($quiz);

        return $this->jsonResponseOk([
            'quiz' => $quiz,
            'results' => $ranked,
        ]);
    }

    public function assignParticipants(Request $request, $quizId): JsonResponse
    {
        $request->validate([
            'class_ids' => 'required|array',
            'class_ids.*' => 'exists:classes,id',
        ]);

        $quiz = Quiz::findOrFail($quizId);
        $assigned = [];
        foreach ($request->class_ids as $classId) {
            $exists = QuizClassAssignment::where('quiz_id', $quizId)
                ->where('class_id', $classId)
                ->exists();
            if (!$exists) {
                $assignment = QuizClassAssignment::create([
                    'quiz_id' => $quizId,
                    'class_id' => $classId,
                ]);
                $assigned[] = $assignment;
            }
        }

        return $this->jsonResponseOk($assigned);
    }

    public function availableForStudent(Request $request): JsonResponse
    {
        $student = $request->user()->load('studentClassRegistrations');
        $classIds = $student->studentClassRegistrations->pluck('class_id')->filter()->values();

        $query = Quiz::query()
            ->where('is_visible', true)
            ->with(['quizClassAssignments.schoolClass'])
            ->where(function ($q) use ($classIds) {
                $q->whereDoesntHave('quizClassAssignments')
                    ->orWhereHas('quizClassAssignments', function ($assignmentQuery) use ($classIds) {
                        $assignmentQuery->whereIn('class_id', $classIds);
                    });
            })
            ->orderByDesc('start_time')
            ->orderByDesc('created_at');

        return $this->jsonResponseOk($query->paginate($request->get('length', 20)));
    }

    private function buildContentPayload(string $type, $value): array
    {
        if ($type === 'text') {
            return [
                'type' => 'text',
                'body' => $value,
            ];
        }

        // For image or pdf - store file if uploaded
        if ($type === 'image' || $type === 'pdf') {
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $path = $value->store('quiz-content', 'public');
                return [
                    'type' => $type,
                    'path' => $path,
                ];
            }
            // If path already provided
            return [
                'type' => $type,
                'path' => $value,
            ];
        }

        return [
            'type' => $type,
            'body' => $value,
        ];
    }
}