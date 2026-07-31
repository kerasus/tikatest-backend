<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\InPersonExamDetail;
use App\Models\InPersonExamResult;
use App\Models\Lesson;
use App\Models\User;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:grades.view')->only(['index', 'show', 'lessonReport', 'multipleLessonsReport', 'studentReport', 'statistics', 'reportCard']);
        $this->middleware('admin_or_permission:grades.create')->only(['store', 'bulkStore', 'createExamWithGrades']);
        $this->middleware('admin_or_permission:grades.update')->only(['update', 'updateZScores']);
        $this->middleware('admin_or_permission:grades.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['raw_score', 'scaled_score'],
            'filterKeysExact' => [
                'in_place_exam_id',
                'user_id',
                'recorded_by',
            ],
            'filterDate' => [
                'created_at',
                'updated_at',
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
                    'relationName' => 'inPersonExamDetail.exam.lesson',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'category_title',
                    'relationName' => 'inPersonExamDetail.exam.category',
                    'relationColumn' => 'title',
                    'exact' => false,
                ],
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'inPersonExamDetail.exam.classes',
                ],
                [
                    'requestKey' => 'student_ids',
                    'relationName' => 'student',
                ],
                [
                    'requestKey' => 'exam_ids',
                    'relationName' => 'inPersonExamDetail.exam',
                ],
                [
                    'requestKey' => 'lesson_id',
                    'relationName' => 'inPersonExamDetail.exam.lesson',
                ],
            ],
            'eagerLoads' => ['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.classes', 'student'],
            'returnModelQuery' => true,
        ];

        $result = $this->commonIndex($request, InPlaceExamResult::class, $config);

        if (is_array($result) && isset($result['modelQuery'])) {
            $modelQuery = $result['modelQuery'];

            if ($request->has('school_id')) {
                $modelQuery->whereHas('inPlaceExamDetail.exam.category', function ($q) use ($request) {
                    $q->where('school_id', $request->get('school_id'));
                });
            }

            if ($request->has('field_id')) {
                $modelQuery->whereHas('inPlaceExamDetail.exam.classes', function ($q) use ($request) {
                    $q->whereHas('academicLevel', function ($subQ) use ($request) {
                        $subQ->where('field_id', $request->get('field_id'));
                    });
                });
            }

            if ($request->has('level_id')) {
                $modelQuery->where(function ($q) use ($request) {
                    $q->whereHas('inPlaceExamDetail.exam.classes', function ($subQ) use ($request) {
                        $subQ->where('level_id', $request->get('level_id'));
                    })->orWhereHas('inPlaceExamDetail.exam.lesson', function ($subQ) use ($request) {
                        $subQ->where('level_id', $request->get('level_id'));
                    });
                });
            }

            if ($request->has('grade_type')) {
                $gradeTypeLabel = $this->getGradeTypeLabel($request->get('grade_type'), $request->get('grade_name_for_other_type'));
                $modelQuery->whereHas('inPlaceExamDetail.exam.category', function ($q) use ($gradeTypeLabel) {
                    $q->where('title', $gradeTypeLabel);
                });
            }

            if ($request->has('is_report_card') && $request->boolean('is_report_card')) {
                $reportCardTypes = ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2'];
                $reportCardLabels = array_map([$this, 'getGradeTypeLabel'], $reportCardTypes);
                $modelQuery->whereHas('inPlaceExamDetail.exam.category', function ($q) use ($reportCardLabels) {
                    $q->whereIn('title', $reportCardLabels);
                });
            }

            return $result['responseWithAttachedCollection']($modelQuery);
        }

        return $result;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'in_person_exam_id' => 'required|exists:in_person_exam_details,id',
            'user_id' => 'required|exists:users,id',
            'raw_score' => 'nullable|numeric|min:0',
            'scaled_score' => 'nullable|numeric|min:0',
            'z_score' => 'nullable|numeric',
        ]);

        return $this->commonStore($request, InPersonExamResult::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $result = InPersonExamResult::with(['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.classes', 'student'])->findOrFail($id);

        return $this->jsonResponseOk($result);
    }

    public function update(Request $request, InPersonExamResult $inPersonExamResult): JsonResponse
    {
        $request->validate([
            'in_person_exam_id' => 'sometimes|required|exists:in_person_exam_details,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'raw_score' => 'nullable|numeric|min:0',
            'scaled_score' => 'nullable|numeric|min:0',
            'z_score' => 'nullable|numeric',
        ]);

        return $this->commonUpdate($request, $inPersonExamResult);
    }

    public function destroy(InPersonExamResult $inPersonExamResult): JsonResponse
    {
        return $this->commonDestroy($inPersonExamResult);
    }

    public function createExamWithGrades(Request $request): JsonResponse
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

        if (! $request->is_descriptive) {
            if (! $request->filled('max_score')) {
                throw ValidationException::withMessages(['max_score' => 'حداکثر نمره الزامی است.']);
            }
            if ($request->filled('min_passing_score') && $request->min_passing_score >= $request->max_score) {
                throw ValidationException::withMessages(['min_passing_score' => 'حداقل نمره قبولی باید از حداکثر نمره کمتر باشد.']);
            }
            foreach ($request->grades as $index => $gradeData) {
                if (isset($gradeData['raw_grade']) && is_numeric($gradeData['raw_grade']) && $gradeData['raw_grade'] >= $request->max_score) {
                    throw ValidationException::withMessages(["grades.$index.raw_grade" => 'نمره باید کمتر از حداکثر نمره باشد.']);
                }
            }
        }

        $isReportCard = in_array($request->grade_type, ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2']);

        DB::beginTransaction();
        try {
            $exam = $this->createExamFromRequest($request, $isReportCard);

            $createdResults = [];
            $errors = [];

            foreach ($request->grades as $index => $gradeData) {
                $rawScore = $gradeData['raw_grade'] ?? null;
                $descriptiveValue = $gradeData['descriptive_value'] ?? null;
                $scaledScore = null;

                if (! $request->is_descriptive) {
                    $minGrade = $request->min_passing_score;
                    if ($rawScore !== null && $minGrade !== null && $rawScore > $minGrade) {
                        $errors[] = 'Row '.($index + 1).': نمره دانش‌آموز بیش از حداکثر نمره است.';

                        continue;
                    }

                    $scaledScore = $request->min_passing_score
                        ? round(($rawScore / $request->min_passing_score) * 20, 2)
                        : $rawScore;
                } else {
                    $rawScore = $descriptiveValue;
                }

                $existing = InPersonExamResult::where('user_id', $gradeData['student_id'])
                    ->where('in_person_exam_id', $exam->inPersonDetail->id)
                    ->first();

                if ($existing) {
                    $student = User::find($gradeData['student_id']);
                    $errors[] = 'Row '.($index + 1).': نمره قبلاً برای دانش‌آموز '.($student->full_name ?? 'Unknown').' ثبت شده است.';

                    continue;
                }

                $result = InPersonExamResult::create([
                    'in_person_exam_id' => $exam->inPersonDetail->id,
                    'user_id' => $gradeData['student_id'],
                    'raw_score' => $request->is_descriptive ? (float) $descriptiveValue : $rawScore,
                    'scaled_score' => $scaledScore,
                ]);

                $createdResults[] = $result;
            }

            if (! $request->is_descriptive && count($createdResults) > 0) {
                $this->calculateZScores($exam->inPersonDetail->id);
            }

            DB::commit();

            return $this->jsonResponseOk([
                'exam' => $exam->load(['category', 'inPersonDetail']),
                'results' => $createdResults,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->jsonResponseServerError(['errors' => [$e->getMessage()]]);
        }
    }

    protected function calculateZScores(int $inPersonExamId): void
    {
        $results = InPersonExamResult::where('in_person_exam_id', $inPersonExamId)
            ->whereNotNull('scaled_score')
            ->get();

        if ($results->isEmpty() || $results->count() < 2) {
            return;
        }

        $scaledScores = $results->pluck('scaled_score')->filter();
        $avg = $scaledScores->avg();
        $stdDev = $scaledScores->std(1);

        foreach ($results as $result) {
            $zScore = $stdDev > 0 ? round(($result->scaled_score - $avg) / $stdDev, 4) : 0;
            $result->update(['z_score' => $zScore]);
        }
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'nullable|exists:exams,id',
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

        return $this->createExamWithGrades($request);
    }

    public function statistics(Request $request, $lessonId, $classId): JsonResponse
    {
        $results = InPersonExamResult::whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
            $q->where('id', $lessonId);
        })
            ->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->whereNotNull('scaled_score')
            ->with(['student'])
            ->get();

        if ($results->isEmpty()) {
            return $this->jsonResponseOk([
                'count' => 0,
                'average' => 0,
                'highest' => 0,
                'lowest' => 0,
                'pass_rate' => 0,
            ]);
        }

        $scaledScores = $results->pluck('scaled_score')->filter();
        $average = round($scaledScores->avg(), 2);
        $highest = $scaledScores->max();
        $lowest = $scaledScores->min();
        $stdDev = $scaledScores->count() > 1 ? round($scaledScores->std(1), 4) : 0;

        $passResults = $results->filter(function ($result) {
            $minPassing = $result->inPersonExamDetail?->exam?->min_passing_score ?? 10;

            return $result->raw_score >= $minPassing;
        });
        $passRate = round(($passResults->count() / $results->count()) * 100, 2);

        return $this->jsonResponseOk([
            'count' => $results->count(),
            'average' => $average,
            'highest' => $highest,
            'lowest' => $lowest,
            'std_deviation' => $stdDev,
            'pass_rate' => $passRate,
        ]);
    }

    public function lessonReport(Request $request, $lessonId): JsonResponse
    {
        $query = InPersonExamResult::whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
            $q->where('id', $lessonId);
        })
            ->whereNotNull('scaled_score')
            ->with(['student', 'inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.classes']);

        if ($request->filled('class_id')) {
            $query->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return $this->jsonResponseOk([
                'results' => [],
                'stats' => [
                    'count' => 0,
                    'average' => 0,
                    'highest' => 0,
                    'lowest' => 0,
                    'pass_rate' => 0,
                ],
            ]);
        }

        $scaledScores = $results->pluck('scaled_score')->filter();
        $average = round($scaledScores->avg() ?? 0, 2);
        $highest = $scaledScores->max() ?? 0;
        $lowest = $scaledScores->min() ?? 0;
        $stdDev = $scaledScores->count() > 1 ? round($scaledScores->std(1), 4) : 0;

        $passResults = $results->filter(function ($result) {
            $minPassing = $result->inPersonExamDetail?->exam?->min_passing_score ?? 10;

            return $result->raw_score >= $minPassing;
        });
        $passRate = $results->count() > 0 ? round(($passResults->count() / $results->count()) * 100, 2) : 0;

        return $this->jsonResponseOk([
            'results' => $results,
            'stats' => [
                'count' => $results->count(),
                'average' => $average,
                'highest' => $highest,
                'lowest' => $lowest,
                'pass_rate' => $passRate,
                'std_deviation' => $stdDev,
            ],
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
            $query = InPersonExamResult::whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
                $q->where('id', $lessonId);
            })
                ->whereNotNull('scaled_score')
                ->with(['student', 'inPersonExamDetail', 'inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.classes']);

            if ($request->filled('class_id')) {
                $query->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($request) {
                    $q->where('class_id', $request->class_id);
                });
            }

            $lessonResults = $query->get();

            if ($lessonResults->isEmpty()) {
                continue;
            }

            $scaledScores = $lessonResults->pluck('scaled_score')->filter();
            $average = round($scaledScores->avg() ?? 0, 2);
            $highest = $scaledScores->max() ?? 0;
            $lowest = $scaledScores->min() ?? 0;
            $stdDev = $scaledScores->count() > 1 ? round($scaledScores->std(1), 4) : 0;

            $passResults = $lessonResults->filter(function ($result) {
                $minPassing = $result->inPersonExamDetail?->exam?->min_passing_score ?? 10;

                return $result->raw_score >= $minPassing;
            });
            $passRate = $lessonResults->count() > 0 ? round(($passResults->count() / $lessonResults->count()) * 100, 2) : 0;

            $results[] = [
                'lesson_id' => $lessonId,
                'lesson_name' => $lessonResults->first()?->inPersonExamDetail?->exam?->lesson?->name ?? '',
                'results' => $lessonResults,
                'stats' => [
                    'count' => $lessonResults->count(),
                    'average' => $average,
                    'highest' => $highest,
                    'lowest' => $lowest,
                    'pass_rate' => $passRate,
                    'std_deviation' => $stdDev,
                ],
            ];
        }

        return $this->jsonResponseOk($results);
    }

    public function studentReport(Request $request, $studentId): JsonResponse
    {
        $query = InPersonExamResult::where('user_id', $studentId)
            ->whereNotNull('scaled_score')
            ->with(['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.classes']);

        if ($request->filled('category_title')) {
            $query->whereHas('inPersonExamDetail.exam.category', function ($q) use ($request) {
                $q->where('title', $request->category_title);
            });
        }

        $results = $query->orderBy('created_at', 'desc')->get();

        return $this->jsonResponseOk($results);
    }

    public function getStudentReportCard(Request $request, $studentId): JsonResponse
    {
        $reportCardTypes = ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2'];

        $query = InPersonExamResult::where('user_id', $studentId)
            ->whereNotNull('scaled_score')
            ->whereHas('inPersonExamDetail.exam.category', function ($q) use ($reportCardTypes) {
                $q->whereIn('title', $reportCardTypes);
            })
            ->with(['inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.classes'])
            ->orderBy('created_at', 'desc');

        $results = $query->get();

        if ($results->isEmpty()) {
            return $this->jsonResponseOk([
                'student_id' => $studentId,
                'results' => [],
                'term_averages' => [],
            ]);
        }

        $averagesByTerm = [];
        $resultsByCategory = [];
        foreach ($reportCardTypes as $type) {
            $typeResults = $results->filter(function ($result) use ($type) {
                return $result->grade_type === $type;
            });
            if ($typeResults->isNotEmpty()) {
                $averagesByTerm[$type] = round($typeResults->pluck('scaled_score')->avg(), 2);
                $resultsByCategory[$type] = $typeResults->values();
            }
        }

        return $this->jsonResponseOk([
            'student_id' => $studentId,
            'results' => $results,
            'term_averages' => $averagesByTerm,
            'results_by_category' => $resultsByCategory,
        ]);
    }

    public function updateZScores(Request $request): JsonResponse
    {
        $request->validate([
            'in_person_exam_id' => 'required|exists:in_person_exam_details,id',
        ]);

        $this->calculateZScores($request->in_person_exam_id);

        return $this->jsonResponseOk(['message' => 'Z-scores updated']);
    }

    protected function createExamFromRequest(Request $request, bool $isReportCard): Exam
    {
        $schoolId = $request->user()->school_id;
        $gradeType = $request->grade_type;
        $gradeTypeLabel = $this->getGradeTypeLabel($gradeType, $request->grade_name_for_other_type);

        $category = ExamCategory::firstOrCreate(
            ['school_id' => $schoolId, 'title' => $gradeTypeLabel],
            ['term_number' => null, 'sort_order' => 0]
        );

        $lesson = Lesson::find($request->lesson_id);
        $examName = $gradeTypeLabel.' - '.($lesson?->name ?? 'بدون درس');

        $exam = Exam::create([
            'name' => $examName,
            'description' => null,
            'lesson_id' => $request->lesson_id,
            'min_passing_score' => $request->min_passing_score,
            'max_score' => $request->max_score,
            'delivery_mode' => 'in_person',
            'exam_category_id' => $category->id,
            'created_by' => $request->user()->id,
        ]);

        $inPersonDetail = InPersonExamDetail::create([
            'exam_id' => $exam->id,
            'held_at' => $request->exam_date,
            'is_descriptive' => $request->is_descriptive ?? false,
            'created_by' => $request->user()->id,
        ]);

        if ($request->filled('class_id')) {
            $exam->classes()->sync([$request->class_id], false);
        }

        if ($request->filled('class_ids')) {
            $exam->classes()->sync($request->class_ids, false);
        }

        $exam->setRelation('inPersonDetail', $inPersonDetail);

        return $exam;
    }

    private function getGradeTypeLabel(string $gradeType, ?string $gradeNameForOtherType = null): string
    {
        return match ($gradeType) {
            'class_quiz' => 'آزمون کلاسی',
            'monthly_quiz' => 'آزمون ماهانه',
            'mid_term_1' => 'میان ترم اول',
            'continuous_1' => 'مستمر اول',
            'final_1' => 'پایان ترم اول',
            'mid_term_2' => 'میان ترم دوم',
            'continuous_2' => 'مستمر دوم',
            'final_2' => 'پایان ترم دوم',
            'other' => $gradeNameForOtherType ?: 'سایر',
            default => $gradeType,
        };
    }
}
