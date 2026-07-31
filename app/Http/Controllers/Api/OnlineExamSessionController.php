<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineExamSession;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineExamSessionController extends Controller
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
            'filterKeys' => ['status'],
            'filterKeysExact' => ['exam_id', 'student_id', 'is_locked'],
            'filterDate' => ['started_at', 'submitted_at', 'created_at'],
            'eagerLoads' => ['exam', 'exam.category', 'exam.lesson', 'student', 'responses'],
        ];

        return $this->commonIndex($request, OnlineExamSession::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'student_id' => 'required|exists:users,id',
            'status' => 'in:not_started,in_progress,submitted,graded,expired',
            'started_at' => 'nullable|date',
            'submitted_at' => 'nullable|date',
            'duration_limit_seconds' => 'nullable|integer|min:0',
            'time_used_seconds' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0',
            'percent' => 'nullable|numeric|min:0|max:100',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string',
            'attempt_number' => 'nullable|integer|min:1',
            'is_locked' => 'boolean',
        ]);

        return $this->commonStore($request, OnlineExamSession::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $session = OnlineExamSession::with(['exam', 'exam.category', 'exam.lesson', 'student', 'responses'])->findOrFail($id);

        return $this->jsonResponseOk($session);
    }

    public function update(Request $request, OnlineExamSession $onlineExamSession): JsonResponse
    {
        $request->validate([
            'exam_id' => 'sometimes|required|exists:exams,id',
            'student_id' => 'sometimes|required|exists:users,id',
            'status' => 'in:not_started,in_progress,submitted,graded,expired',
            'started_at' => 'nullable|date',
            'submitted_at' => 'nullable|date',
            'duration_limit_seconds' => 'nullable|integer|min:0',
            'time_used_seconds' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0',
            'percent' => 'nullable|numeric|min:0|max:100',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string',
            'attempt_number' => 'nullable|integer|min:1',
            'is_locked' => 'boolean',
        ]);

        return $this->commonUpdate($request, $onlineExamSession);
    }

    public function destroy(OnlineExamSession $onlineExamSession): JsonResponse
    {
        return $this->commonDestroy($onlineExamSession);
    }
}
