<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:grades.view')->only(['index', 'show']);
        $this->middleware('permission:grades.create')->only(['store']);
        $this->middleware('permission:grades.update')->only(['update']);
        $this->middleware('permission:grades.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['grade_type'],
            'filterKeysExact' => [
                'is_visible',
                'is_report_card',
                'is_descriptive',
            ],
            'filterDate' => [
                'gregorian_date',
                'created_at',
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'student_name',
                    'relationName' => 'student',
                    'relationColumn' => 'full_name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'lesson_name',
                    'relationName' => 'lesson',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'schoolClass',
                ],
                [
                    'requestKey' => 'student_ids',
                    'relationName' => 'student',
                ],
            ],
            'eagerLoads' => ['school', 'examSession', 'lesson', 'student', 'schoolClass'],
        ];

        return $this->commonIndex($request, Grade::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'exam_session_id' => 'required|exists:exam_sessions,id',
            'lesson_id' => 'required|exists:lessons,id',
            'student_id' => 'required|exists:users,id',
            'class_id' => 'required|exists:classes,id',
            'raw_grade' => 'nullable|numeric|min:0',
            'calculated_grade' => 'nullable|numeric|min:0',
            'min_grade' => 'nullable|numeric|min:0',
            'grade_type' => 'required|string|max:50',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'is_report_card' => 'boolean',
            'is_descriptive' => 'boolean',
            'descriptive_value' => 'nullable|string|max:255',
            'is_visible' => 'boolean',
            'z_score' => 'nullable|numeric',
            'gregorian_date' => 'required|date',
            'persian_date' => 'nullable|string|max:20',
            'explanation' => 'nullable|string',
        ]);

        return $this->commonStore($request, Grade::class);
    }

    public function show(int $id): JsonResponse
    {
        $grade = Grade::with(['school', 'examSession', 'lesson', 'student', 'schoolClass'])->findOrFail($id);

        return $this->jsonResponseOk($grade);
    }

    public function update(Request $request, Grade $grade): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'exam_session_id' => 'sometimes|required|exists:exam_sessions,id',
            'lesson_id' => 'sometimes|required|exists:lessons,id',
            'student_id' => 'sometimes|required|exists:users,id',
            'class_id' => 'sometimes|required|exists:classes,id',
            'raw_grade' => 'nullable|numeric|min:0',
            'calculated_grade' => 'nullable|numeric|min:0',
            'min_grade' => 'nullable|numeric|min:0',
            'grade_type' => 'sometimes|required|string|max:50',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'is_report_card' => 'boolean',
            'is_descriptive' => 'boolean',
            'descriptive_value' => 'nullable|string|max:255',
            'is_visible' => 'boolean',
            'z_score' => 'nullable|numeric',
            'gregorian_date' => 'sometimes|required|date',
            'persian_date' => 'nullable|string|max:20',
            'explanation' => 'nullable|string',
        ]);

        return $this->commonUpdate($request, $grade);
    }

    public function destroy(Grade $grade): JsonResponse
    {
        return $this->commonDestroy($grade);
    }
}
