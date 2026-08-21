<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeworkSubmission;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkSubmissionController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:homework_submissions.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:homework_submissions.create')->only(['store']);
        $this->middleware('admin_or_permission:homework_submissions.update')->only(['update']);
        $this->middleware('admin_or_permission:homework_submissions.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterRelationIds' => [
                [
                    'requestKey' => 'homework_ids',
                    'relationName' => 'homework',
                ],
                [
                    'requestKey' => 'student_ids',
                    'relationName' => 'student',
                ],
            ],
            'filterKeysExact' => [
                'homework_id',
                'graded_by',
            ],
            'eagerLoads' => ['homework', 'student'],
        ];

        return $this->commonIndex($request, HomeworkSubmission::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'homework_id' => 'required|exists:homework,id',
            'student_id' => 'required|exists:users,id',
            'submitted_at' => 'nullable|date',
            'content' => 'nullable|array',
            'content.*' => 'array',
        ]);

        return $this->commonStore($request, HomeworkSubmission::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $submission = HomeworkSubmission::with(['homework', 'student'])->findOrFail($id);

        return $this->jsonResponseOk($submission);
    }

    public function update(Request $request, HomeworkSubmission $submission): JsonResponse
    {
        $request->validate([
            'homework_id' => 'sometimes|required|exists:homework,id',
            'student_id' => 'sometimes|required|exists:users,id',
            'submitted_at' => 'nullable|date',
            'student_seen_at' => 'nullable|date',
            'operator_seen_at' => 'nullable|date',
            'feedback' => 'nullable|string',
            'content' => 'nullable|array',
            'content.*' => 'array',
        ]);

        return $this->commonUpdate($request, $submission);
    }

    public function markAsSeen(HomeworkSubmission $homeworkSubmission): JsonResponse
    {
        if ($homeworkSubmission->operator_seen_at === null) {
//            $homeworkSubmission->operator_seen_at = now();
//            $homeworkSubmission->save();
            $homeworkSubmission->update([
                'operator_seen_at' => now(),
            ]);
        }

        return $this->jsonResponseOk($homeworkSubmission->fresh());
    }

    public function sendFeedback(Request $request, HomeworkSubmission $homeworkSubmission): JsonResponse
    {
        $request->validate([
            'feedback' => 'nullable|string',
        ]);

        $homeworkSubmission->update([
            'feedback' => $request->input('feedback'),
        ]);

        return $this->jsonResponseOk($homeworkSubmission);
    }

    public function destroy(HomeworkSubmission $submission): JsonResponse
    {
        return $this->commonDestroy($submission);
    }
}
