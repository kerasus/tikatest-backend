<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;

class GradeService
{
    public function insertBulkGrades(array $gradesData, ?int $createdBy = null): array
    {
        $createdGrades = [];
        $errors = [];
        $examSession = null;

        DB::beginTransaction();

        try {
            $firstGrade = $gradesData[0] ?? null;
            if (!$firstGrade) {
                throw new \Exception('No grades provided');
            }

            $lessonId = $firstGrade['lesson_id'];
            $classId = $firstGrade['class_id'];
            $gradeType = $firstGrade['grade_type'];
            $gradeNameForOtherType = $firstGrade['grade_name_for_other_type'] ?? null;

            $isReportCard = in_array($gradeType, ['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2']);

            $examSession = ExamSession::create([
                'school_id' => $firstGrade['school_id'] ?? null,
                'lesson_id' => $lessonId,
                'class_id' => $classId,
                'exam_date' => $firstGrade['exam_date'] ?? $firstGrade['grade_date'],
                'grade_type' => $gradeType,
                'grade_name_for_other_type' => $gradeNameForOtherType,
                'is_descriptive' => $firstGrade['is_descriptive'] ?? false,
                'is_report_card' => $isReportCard,
                'min_grade' => $firstGrade['min_grade'] ?? null,
                'created_by' => $createdBy,
            ]);

            foreach ($gradesData as $index => $gradeData) {
                $validation = $this->validateSingleGrade($gradeData, $examSession);

                if (!$validation['valid']) {
                    $errors[] = "Row " . ($index + 1) . ": " . $validation['error'];
                    continue;
                }

                $existing = Grade::where('student_id', $gradeData['student_id'])
                    ->where('lesson_id', $gradeData['lesson_id'])
                    ->where('grade_type', $gradeData['grade_type'])
                    ->where('grade_name_for_other_type', $gradeData['grade_name_for_other_type'] ?? null)
                    ->where('is_report_card', $isReportCard)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    $student = \App\Models\User::find($gradeData['student_id']);
                    $errors[] = "Row " . ($index + 1) . ": Grade already exists for student " . ($student->full_name ?? 'Unknown');
                    continue;
                }

                $gradeData['exam_session_id'] = $examSession->id;
                $grade = Grade::create($gradeData);
                $createdGrades[] = $grade;
            }

            if ($firstGrade['is_descriptive'] ?? false) {
                DB::commit();
                return [
                    'success' => true,
                    'grades' => $createdGrades,
                    'errors' => $errors,
                    'exam_session' => $examSession,
                ];
            }

            $this->calculateZScores($examSession->id, $lessonId, $gradeType, $gradeNameForOtherType);

            DB::commit();

            return [
                'success' => true,
                'grades' => $createdGrades,
                'errors' => $errors,
                'exam_session' => $examSession,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    private function validateSingleGrade(array $gradeData, ExamSession $examSession): array
    {
        $isDescriptive = $gradeData['is_descriptive'] ?? false;

        if (!$isDescriptive) {
            $rawGrade = $gradeData['raw_grade'] ?? null;
            $minGrade = $examSession->min_grade;

            if ($rawGrade === null) {
                return ['valid' => false, 'error' => 'Raw grade is required for non-descriptive grades'];
            }

            if ($rawGrade < 0) {
                return ['valid' => false, 'error' => 'Grade cannot be negative'];
            }

            if ($minGrade && $rawGrade > $minGrade) {
                return ['valid' => false, 'error' => 'Student grade cannot exceed base grade'];
            }
        } else {
            $descriptiveValue = $gradeData['descriptive_value'] ?? null;
            if (!in_array($descriptiveValue, [1, 2, 3, 4])) {
                return ['valid' => false, 'error' => 'Invalid descriptive grade value'];
            }
        }

        return ['valid' => true];
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

    public function getLessonGrades(int $lessonId, int $classId, ?int $examSessionId = null): array
    {
        $query = Grade::where('lesson_id', $lessonId)
            ->where('class_id', $classId)
            ->where('is_report_card', false)
            ->with(['student', 'schoolClass', 'examSession'])
            ->orderBy('grade_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($examSessionId) {
            $query->where('exam_session_id', $examSessionId);
        }

        $grades = $query->get();

        if ($grades->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No grades found',
                'data' => [],
            ];
        }

        $processedData = $grades->map(function ($grade) {
            return [
                'id' => $grade->id,
                'student_id' => $grade->student_id,
                'student_name' => $grade->student->full_name ?? '',
                'student_lastname' => $grade->student->lastname ?? '',
                'grade' => $grade->calculated_grade,
                'raw_grade' => $grade->raw_grade,
                'min_grade' => $grade->min_grade,
                'grade_type' => $grade->grade_type,
                'grade_type_label' => $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type),
                'descriptive_value' => $grade->descriptive_value,
                'descriptive_label' => $grade->descriptive_label,
                'is_visible' => $grade->is_visible,
                'z_score' => $grade->z_score,
                'explanation' => $grade->explanation,
            ];
        });

        $stats = $this->calculateGradeStatistics($grades);

        return [
            'success' => true,
            'message' => 'Grades retrieved successfully',
            'data' => $processedData->values(),
            'stats' => $stats,
        ];
    }

    private function calculateGradeStatistics($grades): array
    {
        $calculatedGrades = $grades->pluck('calculated_grade')->filter();

        return [
            'count' => $grades->count(),
            'average' => round($calculatedGrades->avg() ?? 0, 2),
            'highest' => $calculatedGrades->max() ?? 0,
            'lowest' => $calculatedGrades->min() ?? 0,
            'std_deviation' => $grades->count() > 1 ? round($calculatedGrades->std(1), 4) : 0,
        ];
    }

    public function getStudentReportCard(int $studentId, int $classId): array
    {
        $grades = Grade::where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('is_report_card', true)
            ->where('is_descriptive', false)
            ->whereNull('deleted_at')
            ->with(['lesson', 'examSession'])
            ->orderBy('grade_date', 'desc')
            ->get();

        $processedGrades = $grades->map(function ($grade) {
            return [
                'id' => $grade->id,
                'lesson_id' => $grade->lesson_id,
                'lesson_name' => $grade->lesson->name ?? '',
                'grade_type' => $grade->grade_type,
                'grade_type_label' => $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type),
                'calculated_grade' => $grade->calculated_grade,
                'z_score' => $grade->z_score,
            ];
        });

        $averagesByTerm = [];
        foreach (['mid_term_1', 'continuous_1', 'final_1', 'mid_term_2', 'continuous_2', 'final_2'] as $type) {
            $typeGrades = $grades->filter(fn($g) => $g->grade_type === $type);
            $averagesByTerm[$type] = round($typeGrades->pluck('calculated_grade')->avg() ?? 0, 2);
        }

        return [
            'success' => true,
            'message' => 'Student report card retrieved successfully',
            'data' => [
                'student_id' => $studentId,
                'grades' => $processedGrades->values(),
                'term_averages' => $averagesByTerm,
            ],
        ];
    }

    private function getGradeTypeLabel(string $gradeType, ?string $gradeNameForOtherType = null): string
    {
        return match($gradeType) {
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