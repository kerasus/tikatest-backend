<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:quiz_attempts.view')->only(['index', 'show']);
        $this->middleware('permission:quiz_attempts.create')->only(['store']);
        $this->middleware('permission:quiz_attempts.update')->only(['update']);
        $this->middleware('permission:quiz_attempts.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => [
                'answer_status',
                'is_locked',
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'quiz_ids',
                    'relationName' => 'quiz',
                ],
                [
                    'requestKey' => 'student_ids',
                    'relationName' => 'student',
                ],
            ],
            'eagerLoads' => ['school', 'quiz', 'student', 'lesson'],
        ];

        return $this->commonIndex($request, QuizAttempt::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'quiz_id' => 'required|exists:quizzes,id',
            'student_id' => 'required|exists:users,id',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        return $this->commonStore($request, QuizAttempt::class);
    }

    public function show(int $id): JsonResponse
    {
        $attempt = QuizAttempt::with(['school', 'quiz', 'student', 'lesson'])->findOrFail($id);

        return $this->jsonResponseOk($attempt);
    }

    public function update(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $request->validate([
            'user_answer' => 'nullable|string',
            'temp_answer' => 'nullable|string',
            'answer_status' => 'nullable|string|in:not_sent,sent',
            'is_locked' => 'boolean',
            'percent' => 'nullable|numeric|min:0|max:100',
        ]);

        return $this->commonUpdate($request, $attempt);
    }

    public function destroy(QuizAttempt $attempt): JsonResponse
    {
        return $this->commonDestroy($attempt);
    }
}
