<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineExamSessionResponse;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineExamSessionResponseController extends Controller
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
            'filterKeysExact' => ['online_exam_session_id', 'exam_id', 'user_id', 'is_correct'],
            'filterKeys' => ['question_number'],
            'eagerLoads' => ['onlineExamSession', 'exam', 'exam.category', 'user'],
        ];

        return $this->commonIndex($request, OnlineExamSessionResponse::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'online_exam_session_id' => 'required|exists:online_exam_sessions,id',
            'exam_id' => 'required|exists:exams,id',
            'user_id' => 'required|exists:users,id',
            'question_number' => 'required|integer|min:1',
            'submitted_option' => 'nullable|string|max:10',
            'answer_text' => 'nullable|string',
            'is_correct' => 'boolean',
            'marks_obtained' => 'nullable|numeric',
            'answered_at' => 'nullable|date',
        ]);

        return $this->commonStore($request, OnlineExamSessionResponse::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $response = OnlineExamSessionResponse::with(['onlineExamSession', 'exam', 'exam.category', 'user'])->findOrFail($id);

        return $this->jsonResponseOk($response);
    }

    public function update(Request $request, OnlineExamSessionResponse $onlineExamSessionResponse): JsonResponse
    {
        $request->validate([
            'online_exam_session_id' => 'sometimes|required|exists:online_exam_sessions,id',
            'exam_id' => 'sometimes|required|exists:exams,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'question_number' => 'sometimes|required|integer|min:1',
            'submitted_option' => 'nullable|string|max:10',
            'answer_text' => 'nullable|string',
            'is_correct' => 'boolean',
            'marks_obtained' => 'nullable|numeric',
            'answered_at' => 'nullable|date',
        ]);

        return $this->commonUpdate($request, $onlineExamSessionResponse);
    }

    public function destroy(OnlineExamSessionResponse $onlineExamSessionResponse): JsonResponse
    {
        return $this->commonDestroy($onlineExamSessionResponse);
    }
}
