<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizClassAssignment;
use App\Services\QuizScoringService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class QuizController extends Controller
{
    use Filter, CommonCRUD;

    private QuizScoringService $scoringService;

    public function __construct(QuizScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:quizzes.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:quizzes.create')->only(['store']);
        $this->middleware('admin_or_permission:quizzes.update')->only(['update']);
        $this->middleware('admin_or_permission:quizzes.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'quiz_type',
            ],
            'filterKeysExact' => [
                'visible_at',
                'quiz_type',
            ],
            'filterDate' => [
                'start_time',
                'end_time',
                'created_at',
            ],
            'eagerLoads' => ['quizClassAssignments', 'booklets'],
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
            'quiz_type' => 'nullable|string|max:50',
            'question_type' => 'nullable|in:text,image',
            'questions_text' => 'nullable|string',
            'questions_images' => 'nullable|array',
            'questions_images.*' => 'nullable|file|mimetypes:image/*',
            'solution_type' => 'nullable|in:text,image',
            'solution_text' => 'nullable|string',
            'solution_image' => 'nullable|file|mimetypes:image/*',
            'show_answer_date' => 'nullable|date',
            'visible_at' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        $data = $request->only([
            'school_id',
            'name',
            'time_limit',
            'start_time',
            'end_time',
            'description',
            'quiz_type',
            'show_answer_date',
            'visible_at',
            'no_score_questions',
        ]);

        $schoolCode = $this->getSchoolCode($request->input('school_id'));

        if ($request->filled('question_type')) {
            $data['content'] = $this->buildQuestionPayload($request, $request->input('question_type'), $schoolCode);
        }

        if ($request->filled('solution_type')) {
            $data['solution'] = $this->buildSolutionPayload($request, $request->input('solution_type'), $schoolCode);
        }

        $quiz = Quiz::create($data);

        return $this->jsonResponseOk($quiz->load(['quizClassAssignments.schoolClass', 'answerKeys', 'booklets', 'sessions']));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $quiz = Quiz::with([
            'quizClassAssignments.schoolClass',
            'quizClassAssignments.academicLevel',
            'answerKeys',
            'booklets',
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
            'quiz_type' => 'nullable|string|max:50',
            'question_type' => 'nullable|in:text,image',
            'questions_text' => 'nullable|string',
            'questions_images' => 'nullable|array',
            'questions_images.*' => 'nullable|file|mimetypes:image/*',
            'solution_type' => 'nullable|in:text,image',
            'solution_text' => 'nullable|string',
            'solution_image' => 'nullable|file|mimetypes:image/*',
            'show_answer_date' => 'nullable|date',
            'visible_at' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        $data = $request->only([
            'school_id',
            'name',
            'time_limit',
            'start_time',
            'end_time',
            'description',
            'quiz_type',
            'show_answer_date',
            'visible_at',
            'no_score_questions',
        ]);

        $schoolCode = $this->getSchoolCode($request->input('school_id'));

        if ($request->has('question_type')) {
            $data['content'] = $this->buildQuestionPayload($request, $request->input('question_type'), $schoolCode);
        }

        if ($request->has('solution_type')) {
            $data['solution'] = $this->buildSolutionPayload($request, $request->input('solution_type'), $schoolCode);
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
            ->where(function ($q) {
                $q->where('visible_at', '<=', now())
                  ->orWhereNull('visible_at');
            })
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

    private function getSchoolCode(?int $schoolId): string
    {
        if (!$schoolId) {
            return 'default';
        }

        $school = \App\Models\School::find($schoolId);
        if (!$school || empty($school->code)) {
            return 'default';
        }

        return $school->code;
    }

    private function storeUploadedFile(\Illuminate\Http\UploadedFile $file, string $schoolCode, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = sprintf('%s_%s_%s.%s', $prefix, $schoolCode, time(), $extension);
        $directory = 'quiz-content/' . $schoolCode;

        return $file->storeAs($directory, $filename, 'public');
    }

    private function buildQuestionPayload(Request $request, string $type, string $schoolCode): array
    {
        if ($type === 'text') {
            $text = $request->input('questions_text', '');
            return [
                [
                    'type' => 'text',
                    'body' => $text,
                ],
            ];
        }

        if ($type === 'image') {
            $files = $request->file('questions_images', []);
            $paths = [];
            foreach ($files as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $paths[] = $this->storeUploadedFile($file, $schoolCode, 'question');
                }
            }

            return array_map(function ($path) {
                return [
                    'type' => 'image',
                    'path' => $path,
                ];
            }, $paths);
        }

        return [];
    }

    private function buildSolutionPayload(Request $request, string $type, string $schoolCode): array
    {
        if ($type === 'text') {
            $text = $request->input('solution_text', '');
            return [
                [
                    'type' => 'text',
                    'body' => $text,
                ],
            ];
        }

        if ($type === 'image') {
            $file = $request->file('solution_image');
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $path = $this->storeUploadedFile($file, $schoolCode, 'solution');

                return [
                    [
                        'type' => 'image',
                        'path' => $path,
                    ],
                ];
            }
        }

        return [];
    }
}