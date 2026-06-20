<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizQuestion;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizQuestionController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:quiz_questions.view')->only(['index', 'show']);
        $this->middleware('permission:quiz_questions.create')->only(['store']);
        $this->middleware('permission:quiz_questions.update')->only(['update']);
        $this->middleware('permission:quiz_questions.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterRelationIds' => [
                [
                    'requestKey' => 'quiz_ids',
                    'relationName' => 'quiz',
                ],
            ],
            'eagerLoads' => ['quiz', 'options'],
        ];

        return $this->commonIndex($request, QuizQuestion::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question_number' => 'required|integer|min:1',
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,true_false,fill_blank,essay',
            'points' => 'required|numeric|min:0',
            'has_negative_marking' => 'boolean',
            'negative_marks' => 'nullable|numeric|min:0',
            'question_image_url' => 'nullable|url',
            'explanation' => 'nullable|string',
        ]);

        return $this->commonStore($request, QuizQuestion::class);
    }

    public function show(int $id): JsonResponse
    {
        $question = QuizQuestion::with(['quiz', 'options'])->findOrFail($id);
        return $this->jsonResponseOk($question);
    }

    public function update(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        $request->validate([
            'question_text' => 'sometimes|string',
            'question_type' => 'sometimes|in:multiple_choice,true_false,fill_blank,essay',
            'points' => 'sometimes|numeric|min:0',
            'has_negative_marking' => 'sometimes|boolean',
            'negative_marks' => 'nullable|numeric|min:0',
            'question_image_url' => 'nullable|url',
            'explanation' => 'nullable|string',
        ]);

        return $this->commonUpdate($request, $quizQuestion);
    }

    public function destroy(QuizQuestion $quizQuestion): JsonResponse
    {
        return $this->commonDestroy($quizQuestion);
    }
}
