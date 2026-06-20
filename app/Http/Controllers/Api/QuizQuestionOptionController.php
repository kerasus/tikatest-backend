<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizQuestionOption;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizQuestionOptionController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:quiz_question_options.view')->only(['index', 'show']);
        $this->middleware('permission:quiz_question_options.create')->only(['store']);
        $this->middleware('permission:quiz_question_options.update')->only(['update']);
        $this->middleware('permission:quiz_question_options.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterRelationIds' => [
                [
                    'requestKey' => 'quiz_question_ids',
                    'relationName' => 'question',
                ],
            ],
            'eagerLoads' => ['question'],
        ];

        return $this->commonIndex($request, QuizQuestionOption::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'quiz_question_id' => 'required|exists:quiz_questions,id',
            'option_number' => 'required|integer|min:1',
            'option_text' => 'required|string',
            'option_image_url' => 'nullable|url',
            'is_correct_answer' => 'boolean',
        ]);

        return $this->commonStore($request, QuizQuestionOption::class);
    }

    public function show(int $id): JsonResponse
    {
        $option = QuizQuestionOption::with(['question'])->findOrFail($id);
        return $this->jsonResponseOk($option);
    }

    public function update(Request $request, QuizQuestionOption $quizQuestionOption): JsonResponse
    {
        $request->validate([
            'option_text' => 'sometimes|string',
            'option_image_url' => 'nullable|url',
            'is_correct_answer' => 'sometimes|boolean',
        ]);

        return $this->commonUpdate($request, $quizQuestionOption);
    }

    public function destroy(QuizQuestionOption $quizQuestionOption): JsonResponse
    {
        return $this->commonDestroy($quizQuestionOption);
    }
}
