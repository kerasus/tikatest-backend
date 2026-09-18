<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningActivity;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningActivityController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:learning-activities.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:learning-activities.create')->only(['store']);
        $this->middleware('admin_or_permission:learning-activities.update')->only(['update']);
        $this->middleware('admin_or_permission:learning-activities.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['type'],
            'filterKeysExact' => ['student_id', 'lesson_id', 'study_session_id'],
            'filterDate' => ['occurred_at'],
            'eagerLoads' => ['student', 'lesson', 'studySession'],
        ];

        return $this->commonIndex($request, LearningActivity::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'lesson_id' => 'required|exists:lessons,id',
            'study_session_id' => 'nullable|exists:study_sessions,id',
            'type' => 'required|string|max:50',
            'occurred_at' => 'required|date',
            'duration_seconds' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        return $this->commonStore($request, LearningActivity::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $activity = LearningActivity::with(['student', 'lesson', 'studySession'])->findOrFail($id);

        return $this->jsonResponseOk($activity);
    }

    public function update(Request $request, LearningActivity $learningActivity): JsonResponse
    {
        $request->validate([
            'student_id' => 'sometimes|exists:users,id',
            'lesson_id' => 'sometimes|exists:lessons,id',
            'study_session_id' => 'sometimes|nullable|exists:study_sessions,id',
            'type' => 'sometimes|string|max:50',
            'occurred_at' => 'sometimes|date',
            'duration_seconds' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        return $this->commonUpdate($request, $learningActivity);
    }

    public function destroy(LearningActivity $learningActivity): JsonResponse
    {
        return $this->commonDestroy($learningActivity);
    }
}
