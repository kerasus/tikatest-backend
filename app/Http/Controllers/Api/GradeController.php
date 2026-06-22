<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\User;
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
                [
                    'requestKey' => 'exam_session_ids',
                    'relationName' => 'examSession',
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

    public function validateBulk(Request $request): JsonResponse
    {
        $request->validate([
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|exists:users,id',
            'grades.*.lesson_id' => 'required|exists:lessons,id',
            'grades.*.class_id' => 'required|exists:classes,id',
            'grades.*.raw_grade' => 'required|numeric|min:0',
            'grades.*.grade_type' => 'required|string|max:50',
            'grades.*.gregorian_date' => 'required|date',
        ]);

        $errors = [];
        foreach ($request->grades as $index => $gradeData) {
            if ((float)$gradeData['raw_grade'] < 0) {
                $errors[] = "Row " . ($index + 1) . ": Grade cannot be negative";
            }
            $existing = Grade::where('student_id', $gradeData['student_id'])
                ->where('lesson_id', $gradeData['lesson_id'])
                ->where('grade_type', $gradeData['grade_type'])
                ->where('gregorian_date', $gradeData['gregorian_date'])
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                $student = User::find($gradeData['student_id']);
                $errors[] = "Row " . ($index + 1) . ": Grade already exists for student " . ($student->full_name ?? 'Unknown');
            }
        }

        if (count($errors) > 0) {
            return $this->jsonResponseServerError(['errors' => $errors]);
        }

        return $this->jsonResponseOk(['message' => 'All grades are valid']);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'lesson_id' => 'required|exists:lessons,id',
            'class_id' => 'required|exists:classes,id',
            'grade_type' => 'required|string|max:50',
            'gregorian_date' => 'required|date',
        ]);

        return $this->jsonResponseOk([
            'message' => 'Excel import endpoint ready. Install maatwebsite/excel package for full functionality.',
            'imported' => 0,
        ]);
    }

    public function statistics(Request $request, $lessonId, $classId): JsonResponse
    {
        $grades = Grade::where('lesson_id', $lessonId)
            ->where('class_id', $classId)
            ->where('is_descriptive', false)
            ->whereNotNull('calculated_grade')
            ->get();

        if ($grades->isEmpty()) {
            return $this->jsonResponseOk([
                'count' => 0,
                'average' => 0,
                'highest' => 0,
                'lowest' => 0,
                'pass_rate' => 0,
            ]);
        }

        $calculatedGrades = $grades->pluck('calculated_grade')->filter();
        $average = round($calculatedGrades->avg(), 2);
        $highest = $calculatedGrades->max();
        $lowest = $calculatedGrades->min();
        $stdDev = $calculatedGrades->count() > 1 ? round($calculatedGrades->std(1), 4) : 0;

        $passGrades = $grades->filter(function ($grade) {
            return $grade->raw_grade >= ($grade->min_grade ?? 10);
        });
        $passRate = round(($passGrades->count() / $grades->count()) * 100, 2);

        return $this->jsonResponseOk([
            'count' => $grades->count(),
            'average' => $average,
            'highest' => $highest,
            'lowest' => $lowest,
            'std_deviation' => $stdDev,
            'pass_rate' => $passRate,
        ]);
    }

    public function updateZScores(Request $request): JsonResponse
    {
        $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'class_id' => 'required|exists:classes,id',
            'grade_type' => 'required|string',
            'gregorian_date' => 'required|date',
        ]);

        $grades = Grade::where('lesson_id', $request->lesson_id)
            ->where('class_id', $request->class_id)
            ->where('grade_type', $request->grade_type)
            ->where('gregorian_date', $request->gregorian_date)
            ->whereNotNull('calculated_grade')
            ->get();

        if ($grades->isEmpty()) {
            return $this->jsonResponseOk(['message' => 'No grades found', 'updated' => 0]);
        }

        $avg = $grades->avg('calculated_grade');
        $stdDev = $grades->count() > 1 ? $grades->stdDev('calculated_grade') : 0;

        $updated = 0;
        foreach ($grades as $grade) {
            $zScore = $stdDev > 0 ? round((($grade->calculated_grade - $avg) / $stdDev), 4) : 0;
            $grade->update(['z_score' => $zScore]);
            $updated++;
        }

        return $this->jsonResponseOk([
            'message' => 'Z-scores updated successfully',
            'updated' => $updated,
            'average' => round($avg, 2),
            'std_deviation' => round($stdDev, 4),
        ]);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'grades' => 'required|array',
            'grades.*.school_id' => 'nullable|exists:schools,id',
            'grades.*.exam_session_id' => 'required|exists:exam_sessions,id',
            'grades.*.lesson_id' => 'required|exists:lessons,id',
            'grades.*.student_id' => 'required|exists:users,id',
            'grades.*.class_id' => 'required|exists:classes,id',
            'grades.*.raw_grade' => 'nullable|numeric|min:0',
            'grades.*.calculated_grade' => 'nullable|numeric|min:0',
            'grades.*.min_grade' => 'nullable|numeric|min:0',
            'grades.*.grade_type' => 'required|string|max:50',
            'grades.*.grade_name_for_other_type' => 'nullable|string|max:255',
            'grades.*.is_report_card' => 'boolean',
            'grades.*.is_descriptive' => 'boolean',
            'grades.*.descriptive_value' => 'nullable|string|max:255',
            'grades.*.is_visible' => 'boolean',
            'grades.*.z_score' => 'nullable|numeric',
            'grades.*.gregorian_date' => 'required|date',
            'grades.*.persian_date' => 'nullable|string|max:20',
            'grades.*.explanation' => 'nullable|string',
        ]);

        $createdGrades = [];
        foreach ($request->grades as $gradeData) {
            $grade = Grade::create($gradeData);
            $createdGrades[] = $grade;
        }

        return $this->jsonResponseOk($createdGrades);
    }

    public function lessonReport(Request $request, $lessonId): JsonResponse
    {
        $grades = Grade::where('lesson_id', $lessonId)
            ->where('is_descriptive', false)
            ->with(['student', 'schoolClass', 'examSession'])
            ->get();

        if ($grades->isEmpty()) {
            return $this->jsonResponseOk([
                'grades' => [],
                'stats' => [
                    'count' => 0,
                    'average' => 0,
                    'highest' => 0,
                    'lowest' => 0,
                    'pass_rate' => 0,
                ]
            ]);
        }

        $calculatedGrades = $grades->pluck('calculated_grade')->filter();
        $average = round($calculatedGrades->avg() ?? 0, 2);
        $highest = $calculatedGrades->max() ?? 0;
        $lowest = $calculatedGrades->min() ?? 0;

        $passGrades = $grades->filter(function ($grade) {
            return $grade->raw_grade >= ($grade->min_grade ?? 10);
        });
        $passRate = $grades->count() > 0 ? round(($passGrades->count() / $grades->count()) * 100, 2) : 0;

        return $this->jsonResponseOk([
            'grades' => $grades,
            'stats' => [
                'count' => $grades->count(),
                'average' => $average,
                'highest' => $highest,
                'lowest' => $lowest,
                'pass_rate' => $passRate,
            ]
        ]);
    }

    public function multipleLessonsReport(Request $request): JsonResponse
    {
        $request->validate([
            'lesson_ids' => 'required|array',
            'lesson_ids.*' => 'exists:lessons,id',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $lessonIds = $request->lesson_ids;
        $results = [];

        foreach ($lessonIds as $lessonId) {
            $query = Grade::where('lesson_id', $lessonId)
                ->where('is_descriptive', false)
                ->with(['student', 'schoolClass', 'examSession']);

            if ($request->filled('class_id')) {
                $query->where('class_id', $request->class_id);
            }

            $grades = $query->get();

            if ($grades->isEmpty()) {
                continue;
            }

            $calculatedGrades = $grades->pluck('calculated_grade')->filter();
            $average = round($calculatedGrades->avg() ?? 0, 2);
            $highest = $calculatedGrades->max() ?? 0;
            $lowest = $calculatedGrades->min() ?? 0;

            $passGrades = $grades->filter(function ($grade) {
                return $grade->raw_grade >= ($grade->min_grade ?? 10);
            });
            $passRate = $grades->count() > 0 ? round(($passGrades->count() / $grades->count()) * 100, 2) : 0;

            $results[] = [
                'lesson_id' => $lessonId,
                'lesson_name' => $grades->first()?->lesson?->name ?? '',
                'grades' => $grades,
                'stats' => [
                    'count' => $grades->count(),
                    'average' => $average,
                    'highest' => $highest,
                    'lowest' => $lowest,
                    'pass_rate' => $passRate,
                ]
            ];
        }

        return $this->jsonResponseOk($results);
    }

    public function studentReport(Request $request, $studentId): JsonResponse
    {
        $query = Grade::where('student_id', $studentId)
            ->where('is_descriptive', false)
            ->with(['lesson', 'schoolClass', 'examSession']);

        if ($request->filled('grade_type')) {
            $query->where('grade_type', $request->grade_type);
        }

        $grades = $query->orderBy('gregorian_date', 'desc')->get();

        return $this->jsonResponseOk($grades);
    }
}
