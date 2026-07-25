<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\User;
use App\Services\GradeService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    use Filter, CommonCRUD;

    protected GradeService $gradeService;

    public function __construct()
    {
        $this->gradeService = new GradeService();

        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:grades.view')->only(['index', 'show', 'lessonReport', 'multipleLessonsReport', 'studentReport', 'statistics']);
        $this->middleware('admin_or_permission:grades.create')->only(['store', 'bulkStore', 'createExamSessionWithGrades']);
        $this->middleware('admin_or_permission:grades.update')->only(['update', 'updateZScores']);
        $this->middleware('admin_or_permission:grades.delete')->only(['destroy']);
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
                'grade_date',
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
            'exam_session_id' => 'nullable|exists:exam_sessions,id',
            'lesson_id' => 'required|exists:lessons,id',
            'student_id' => 'required|exists:users,id',
            'class_id' => 'required|exists:classes,id',
            'raw_grade' => 'nullable|numeric|min:0',
            'calculated_grade' => 'nullable|numeric|min:0',
            'min_passing_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
            'grade_type' => 'required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'is_report_card' => 'boolean',
            'is_descriptive' => 'boolean',
            'descriptive_value' => 'nullable|integer|min:1|max:4',
            'is_visible' => 'boolean',
            'z_score' => 'nullable|numeric',
            'grade_date' => 'required|date',
            'explanation' => 'nullable|string',
        ]);

        $isReportCard = in_array($request->grade_type, ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2']);

        $examSession = null;
        if (!$request->filled('exam_session_id')) {
            $examSession = ExamSession::create([
                'school_id' => $request->school_id,
                'lesson_id' => $request->lesson_id,
                'class_id' => $request->class_id,
                'exam_date' => $request->exam_date ?? $request->grade_date,
                'grade_type' => $request->grade_type,
                'grade_name_for_other_type' => $request->grade_name_for_other_type,
                'is_descriptive' => $request->is_descriptive ?? false,
                'is_report_card' => $isReportCard,
                'min_passing_score' => $request->min_passing_score,
                'max_score' => $request->max_score,
                'created_by' => $request->user()->id,
            ]);
            $request->merge(['exam_session_id' => $examSession->id]);
        }

        $grade = Grade::create($request->all());

        return $this->show($grade->id);
    }

    public function show(Request $request, $id): JsonResponse
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
            'min_passing_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
            'grade_type' => 'sometimes|required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'is_report_card' => 'boolean',
            'is_descriptive' => 'boolean',
            'descriptive_value' => 'nullable|integer|min:1|max:4',
            'is_visible' => 'boolean',
            'z_score' => 'nullable|numeric',
            'grade_date' => 'sometimes|required|date',
            'explanation' => 'nullable|string',
        ]);

        return $this->commonUpdate($request, $grade);
    }

    public function destroy(Grade $grade): JsonResponse
    {
        return $this->commonDestroy($grade);
    }

    public function createExamSessionWithGrades(Request $request): JsonResponse
    {
        $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'class_id' => 'required|exists:classes,id',
            'grade_type' => 'required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'exam_date' => 'required|date',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'min_passing_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
            'is_descriptive' => 'boolean',
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|exists:users,id',
            'grades.*.raw_grade' => 'nullable|numeric|min:0',
            'grades.*.descriptive_value' => 'nullable|integer|min:1|max:4',
        ]);

        $isReportCard = in_array($request->grade_type, ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2']);

        $examSession = ExamSession::create([
            'school_id' => $request->user()->school_id,
            'lesson_id' => $request->lesson_id,
            'class_id' => $request->class_id,
            'exam_date' => $request->exam_date,
            'grade_type' => $request->grade_type,
            'grade_name_for_other_type' => $request->grade_name_for_other_type,
            'is_descriptive' => $request->is_descriptive ?? false,
            'is_report_card' => $isReportCard,
            'min_passing_score' => $request->min_passing_score,
            'max_score' => $request->max_score,
            'created_by' => $request->user()->id,
        ]);

        $createdGrades = [];
        $errors = [];

        foreach ($request->grades as $index => $gradeData) {
            $rawGrade = $gradeData['raw_grade'] ?? null;
            $descriptiveValue = $gradeData['descriptive_value'] ?? null;
            $calculatedGrade = null;

            if (!$request->is_descriptive) {
                $minGrade = $request->min_grade;
                if ($rawGrade !== null && $minGrade !== null && $rawGrade > $minGrade) {
                    $errors[] = "Row " . ($index + 1) . ": Student grade cannot exceed base grade";
                    continue;
                }

                $calculatedGrade = $request->min_grade
                    ? round(($rawGrade / $request->min_grade) * 20, 2)
                    : $rawGrade;
            }

            $existing = Grade::where('student_id', $gradeData['student_id'])
                ->where('lesson_id', $request->lesson_id)
                ->where('grade_type', $request->grade_type)
                ->where('grade_name_for_other_type', $request->grade_name_for_other_type)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $student = User::find($gradeData['student_id']);
                $errors[] = "Row " . ($index + 1) . ": Grade already exists for student " . ($student->full_name ?? 'Unknown');
                continue;
            }

            $grade = Grade::create([
                'exam_session_id' => $examSession->id,
                'lesson_id' => $request->lesson_id,
                'class_id' => $request->class_id,
                'student_id' => $gradeData['student_id'],
                'raw_grade' => $rawGrade,
                'calculated_grade' => $request->is_descriptive ? null : $calculatedGrade,
                'min_passing_score' => $request->min_passing_score,
                'grade_type' => $request->grade_type,
                'grade_name_for_other_type' => $request->grade_name_for_other_type,
                'is_descriptive' => $request->is_descriptive ?? false,
                'is_report_card' => $isReportCard,
                'descriptive_value' => $descriptiveValue,
                'is_visible' => true,
                'grade_date' => $request->exam_date,
            ]);

            $createdGrades[] = $grade;
        }

        if (!$request->is_descriptive && count($createdGrades) > 0) {
            $this->calculateZScores($examSession->id, $request->lesson_id, $request->grade_type, $request->grade_name_for_other_type);
        }

        return $this->jsonResponseOk([
            'exam_session' => $examSession,
            'grades' => $createdGrades,
            'errors' => $errors,
        ]);
    }

    private function calculateZScores(int $examSessionId, int $lessonId, string $gradeType, ?string $gradeNameForOtherType): void
    {
        $grades = Grade::where('exam_session_id', $examSessionId)
            ->where('lesson_id', $lessonId)
            ->where('grade_type', $gradeType)
            ->where('grade_name_for_other_type', $gradeNameForOtherType)
            ->where('is_descriptive', false)
            ->whereNotNull('calculated_grade')
            ->get();

        if ($grades->isEmpty() || $grades->count() < 2) {
            return;
        }

        $calculatedGrades = $grades->pluck('calculated_grade')->filter();
        $avg = $calculatedGrades->avg();
        $stdDev = $calculatedGrades->std(1);

        foreach ($grades as $grade) {
            $zScore = $stdDev > 0 ? round(($grade->calculated_grade - $avg) / $stdDev, 4) : 0;
            $grade->update(['z_score' => $zScore]);
        }
    }

    public function validateBulk(Request $request): JsonResponse
    {
        $request->validate([
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|exists:users,id',
            'grades.*.lesson_id' => 'required|exists:lessons,id',
            'grades.*.class_id' => 'required|exists:classes,id',
            'grades.*.raw_grade' => 'nullable|numeric|min:0',
            'grades.*.grade_type' => 'required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'grades.*.grade_date' => 'required|date',
        ]);

        $errors = [];
        foreach ($request->grades as $index => $gradeData) {
            $existing = Grade::where('student_id', $gradeData['student_id'])
                ->where('lesson_id', $gradeData['lesson_id'])
                ->where('grade_type', $gradeData['grade_type'])
                ->where('grade_name_for_other_type', $gradeData['grade_name_for_other_type'] ?? null)
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

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'grades' => 'required|array',
            'grades.*.school_id' => 'nullable|exists:schools,id',
            'grades.*.exam_session_id' => 'nullable|exists:exam_sessions,id',
            'grades.*.lesson_id' => 'required|exists:lessons,id',
            'grades.*.student_id' => 'required|exists:users,id',
            'grades.*.class_id' => 'required|exists:classes,id',
            'grades.*.raw_grade' => 'nullable|numeric|min:0',
            'grades.*.calculated_grade' => 'nullable|numeric|min:0',
            'grades.*.min_grade' => 'nullable|numeric|min:0',
            'grades.*.max_grade' => 'nullable|numeric|min:0',
            'grades.*.grade_type' => 'required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'grades.*.grade_name_for_other_type' => 'nullable|string|max:255',
            'grades.*.is_report_card' => 'boolean',
            'grades.*.is_descriptive' => 'boolean',
            'grades.*.descriptive_value' => 'nullable|integer|min:1|max:4',
            'grades.*.is_visible' => 'boolean',
            'grades.*.z_score' => 'nullable|numeric',
            'grades.*.grade_date' => 'required|date',
            'grades.*.explanation' => 'nullable|string',
        ]);

        $createdGrades = [];
        $errors = [];

        foreach ($request->grades as $index => $gradeData) {
            $examSessionId = $gradeData['exam_session_id'];
            $isReportCard = in_array($gradeData['grade_type'], ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2']);

            if (!$examSessionId) {
                $examSession = ExamSession::create([
                    'school_id' => $gradeData['school_id'] ?? $request->user()->school_id,
                    'lesson_id' => $gradeData['lesson_id'],
                    'class_id' => $gradeData['class_id'],
                    'exam_date' => $gradeData['exam_date'] ?? $gradeData['grade_date'],
                    'grade_type' => $gradeData['grade_type'],
                    'grade_name_for_other_type' => $gradeData['grade_name_for_other_type'] ?? null,
                    'is_descriptive' => $gradeData['is_descriptive'] ?? false,
                    'is_report_card' => $isReportCard,
                    'min_passing_score' => $gradeData['min_passing_score'] ?? null,
                    'max_score' => $gradeData['max_score'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
                $examSessionId = $examSession->id;
            }

            $grade = Grade::create(array_merge($gradeData, ['exam_session_id' => $examSessionId]));
            $createdGrades[] = $grade;
        }

        return $this->jsonResponseOk([
            'grades' => $createdGrades,
            'errors' => $errors,
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
            'grade_date' => 'required|date',
        ]);

        $grades = Grade::where('lesson_id', $request->lesson_id)
            ->where('class_id', $request->class_id)
            ->where('grade_type', $request->grade_type)
            ->where('grade_date', $request->grade_date)
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

    public function lessonReport(Request $request, $lessonId): JsonResponse
    {
        $query = Grade::where('lesson_id', $lessonId)
            ->where('is_descriptive', false)
            ->with(['student', 'schoolClass', 'examSession']);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $grades = $query->get();

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
        $stdDev = $calculatedGrades->count() > 1 ? round($calculatedGrades->std(1), 4) : 0;

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
                'std_deviation' => $stdDev,
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
            $stdDev = $calculatedGrades->count() > 1 ? round($calculatedGrades->std(1), 4) : 0;

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
                    'std_deviation' => $stdDev,
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

        $grades = $query->orderBy('grade_date', 'desc')->get();

        return $this->jsonResponseOk($grades);
    }

    public function getStudentReportCard(Request $request, $studentId): JsonResponse
    {
        $query = Grade::where('student_id', $studentId)
            ->where('is_report_card', true)
            ->where('is_descriptive', false)
            ->whereNull('deleted_at')
            ->with(['lesson', 'examSession'])
            ->orderBy('grade_date', 'desc');

        $grades = $query->get();

        $averagesByTerm = [];
        foreach (['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2'] as $type) {
            $typeGrades = $grades->filter(fn($g) => $g->grade_type === $type);
            if ($typeGrades->isNotEmpty()) {
                $averagesByTerm[$type] = round($typeGrades->pluck('calculated_grade')->avg(), 2);
            }
        }

        return $this->jsonResponseOk([
            'student_id' => $studentId,
            'grades' => $grades,
            'term_averages' => $averagesByTerm,
        ]);
    }
}
