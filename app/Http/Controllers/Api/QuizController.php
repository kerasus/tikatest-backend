<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizClassAssignment;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
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
            'content' => 'nullable|json',
            'solution' => 'nullable|json',
            'solution_file_path' => 'nullable|string|max:255',
            'show_answer_date' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        return $this->commonStore($request, Quiz::class);
    }

    public function show(int $id): JsonResponse
    {
        $quiz = Quiz::with(['quizClassAssignments.schoolClass', 'quizClassAssignments.academicLevel', 'answerKeys'])->findOrFail($id);

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
            'content' => 'nullable|json',
            'solution' => 'nullable|json',
            'solution_file_path' => 'nullable|string|max:255',
            'show_answer_date' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        return $this->commonUpdate($request, $quiz);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        return $this->commonDestroy($quiz);
    }

    public function resultsWithRank(Request $request, $quizId): JsonResponse
    {
        $quiz = Quiz::with(['quizAttempts.student', 'quizClassAssignments.schoolClass'])->findOrFail($quizId);
        $attempts = $quiz->quizAttempts()
            ->where('answer_status', 'sent')
            ->with('student')
            ->get();

        $ranked = $attempts->map(function ($attempt) {
            return [
                'student_id' => $attempt->student_id,
                'student_name' => $attempt->student->full_name ?? 'Unknown',
                'percent' => $attempt->percent,
                'started_at' => $attempt->started_at,
                'ended_at' => $attempt->ended_at,
                'answer_status' => $attempt->answer_status,
            ];
        })
            ->sortByDesc('percent')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });

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
}
