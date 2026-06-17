<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
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
            'correct_answers' => 'required|string',
            'timer' => 'nullable|date_format:H:i:s',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'explanation' => 'nullable|string',
            'is_visible' => 'boolean',
            'quiz_type' => 'nullable|string|max:50',
            'question_url' => 'nullable|string',
            'answer_explanation' => 'nullable|string',
            'false_negative_grading' => 'boolean',
            'questions_text' => 'nullable|string',
            'answers_text' => 'nullable|string',
            'picture_id' => 'nullable|string|max:255',
            'show_answer_date' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        return $this->commonStore($request, Quiz::class);
    }

    public function show(int $id): JsonResponse
    {
        $quiz = Quiz::with(['quizClassAssignments.schoolClass', 'quizClassAssignments.academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($quiz);
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'sometimes|required|string|max:255',
            'correct_answers' => 'sometimes|required|string',
            'timer' => 'nullable|date_format:H:i:s',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'explanation' => 'nullable|string',
            'is_visible' => 'boolean',
            'quiz_type' => 'nullable|string|max:50',
            'question_url' => 'nullable|string',
            'answer_explanation' => 'nullable|string',
            'false_negative_grading' => 'boolean',
            'questions_text' => 'nullable|string',
            'answers_text' => 'nullable|string',
            'picture_id' => 'nullable|string|max:255',
            'show_answer_date' => 'nullable|date',
            'no_score_questions' => 'nullable|string',
        ]);

        return $this->commonUpdate($request, $quiz);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        return $this->commonDestroy($quiz);
    }
}
