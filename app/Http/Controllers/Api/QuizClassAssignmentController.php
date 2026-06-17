<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizClassAssignment;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizClassAssignmentController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:quiz_assignments.view')->only(['index', 'show']);
        $this->middleware('permission:quiz_assignments.create')->only(['store']);
        $this->middleware('permission:quiz_assignments.update')->only(['update']);
        $this->middleware('permission:quiz_assignments.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterRelationIds' => [
                [
                    'requestKey' => 'quiz_ids',
                    'relationName' => 'quiz',
                ],
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'schoolClass',
                ],
            ],
            'eagerLoads' => ['quiz', 'schoolClass', 'academicLevel'],
        ];

        return $this->commonIndex($request, QuizClassAssignment::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'class_id' => 'required|exists:classes,id',
            'level_id' => 'nullable|exists:academic_levels,id',
        ]);

        return $this->commonStore($request, QuizClassAssignment::class);
    }

    public function show(int $id): JsonResponse
    {
        $assignment = QuizClassAssignment::with(['quiz', 'schoolClass', 'academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($assignment);
    }

    public function update(Request $request, QuizClassAssignment $assignment): JsonResponse
    {
        $request->validate([
            'quiz_id' => 'sometimes|required|exists:quizzes,id',
            'class_id' => 'sometimes|required|exists:classes,id',
            'level_id' => 'nullable|exists:academic_levels,id',
        ]);

        return $this->commonUpdate($request, $assignment);
    }

    public function destroy(QuizClassAssignment $assignment): JsonResponse
    {
        return $this->commonDestroy($assignment);
    }
}
