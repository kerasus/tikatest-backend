<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\StudySessionSource;
use App\Models\StudySession;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudySessionController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:study-sessions.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:study-sessions.create')->only(['store']);
        $this->middleware('admin_or_permission:study-sessions.update')->only(['update']);
        $this->middleware('admin_or_permission:study-sessions.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['student_id', 'lesson_id', 'term_id', 'source'],
            'filterDate' => ['started_at', 'ended_at'],
            'eagerLoads' => ['student', 'lesson', 'term'],
        ];

        return $this->commonIndex($request, StudySession::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'lesson_id' => 'required|exists:lessons,id',
            'term_id' => 'required|exists:academic_terms,id',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after_or_equal:started_at',
            'description' => 'nullable|string',
            'source' => 'required|in:' . implode(',', array_column(StudySessionSource::cases(), 'value')),
            'metadata' => 'nullable|array',
        ]);

        return $this->commonStore($request, StudySession::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $session = StudySession::with(['student', 'lesson', 'term'])->findOrFail($id);

        return $this->jsonResponseOk($session);
    }

    public function update(Request $request, StudySession $studySession): JsonResponse
    {
        $request->validate([
            'student_id' => 'sometimes|exists:users,id',
            'lesson_id' => 'sometimes|exists:lessons,id',
            'term_id' => 'sometimes|exists:academic_terms,id',
            'started_at' => 'sometimes|date',
            'ended_at' => 'sometimes|date|after_or_equal:started_at',
            'description' => 'nullable|string',
            'source' => 'sometimes|in:' . implode(',', array_column(StudySessionSource::cases(), 'value')),
            'metadata' => 'nullable|array',
        ]);

        return $this->commonUpdate($request, $studySession);
    }

    public function destroy(StudySession $studySession): JsonResponse
    {
        return $this->commonDestroy($studySession);
    }
}
