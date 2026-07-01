<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\User;

class ReportService
{
    public function getSingleLessonReport(int $lessonId, int $classId, ?int $gradeBase = 20): array
    {
        $query = Grade::where('lesson_id', $lessonId)
            ->where('class_id', $classId)
            ->where('is_report_card', false)
            ->whereNull('deleted_at')
            ->with('student')
            ->get();

        if ($query->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No grades found for this lesson',
                'data' => [],
            ];
        }

        $reportData = [];
        $gradeGroups = [];

        foreach ($query as $grade) {
            $studentId = $grade->student_id;
            $gradeTypeLabel = $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type);
            $groupKey = $grade->gregorian_date . '|' . $grade->grade_type . '|' . ($grade->grade_name_for_other_type ?? '');

            if (!isset($gradeGroups[$groupKey])) {
                $gradeGroups[$groupKey] = [
                    'gregorian_date' => $grade->gregorian_date,
                    'grade_type' => $grade->grade_type,
                    'grade_type_label' => $gradeTypeLabel,
                    'is_descriptive' => $grade->is_descriptive,
                ];
            }

            if (!isset($reportData[$studentId])) {
                $reportData[$studentId] = [
                    'student_id' => $grade->student_id,
                    'student_name' => $grade->student->firstname ?? '',
                    'student_lastname' => $grade->student->lastname ?? '',
                    'full_name' => $grade->student->full_name ?? '',
                ];
            }

            $displayGrade = $this->convertGradeBase(
                $grade->is_descriptive ? $grade->descriptive_value : $grade->calculated_grade,
                20,
                $gradeBase
            );

            $reportData[$studentId][$gradeTypeLabel . '<br>' . $grade->gregorian_date] = $displayGrade;
        }

        $averages = [];
        foreach ($gradeGroups as $group) {
            $displayKey = $group['grade_type_label'] . '<br>' . $group['gregorian_date'];
            $average = 0;
            $count = 0;
            foreach ($reportData as $studentGrades) {
                if (isset($studentGrades[$displayKey])) {
                    $average += $studentGrades[$displayKey];
                    $count++;
                }
            }
            $averages[$group['grade_type'] . '|' . $group['gregorian_date']] = $count > 0 ? round($average / $count, 2) : 0;
        }

        $reportData[] = [
            'full_name' => 'میانگین نمرات آزمون',
            'averages' => $averages,
        ];

        return [
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => array_values($reportData),
            'grade_groups' => array_values($gradeGroups),
        ];
    }

    public function getMultipleLessonsReport(array $lessonIds, int $classId): array
    {
        $reportData = [];

        foreach ($lessonIds as $lessonId) {
            $query = Grade::where('lesson_id', $lessonId)
                ->where('class_id', $classId)
                ->where('is_report_card', false)
                ->whereNull('deleted_at')
                ->with(['student', 'lesson'])
                ->get();

            foreach ($query as $grade) {
                $studentId = $grade->student_id;

                if (!isset($reportData[$studentId])) {
                    $reportData[$studentId] = [
                        'id_user' => $studentId,
                        'name' => $grade->student->firstname ?? '',
                        'lastname' => $grade->student->lastname ?? '',
                        'full_name' => $grade->student->full_name ?? '',
                    ];
                }

                $gradeTypeLabel = $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type);
                $reportData[$studentId][$gradeTypeLabel . '<br>' . $grade->lesson->name ?? ''] = $grade->calculated_grade;
            }
        }

        $averages = [];
        $avgArrayForTable = [];
        foreach ($reportData as $studentId => $studentGrades) {
            foreach ($studentGrades as $key => $value) {
                if (!in_array($key, ['id_user', 'name', 'lastname', 'full_name'])) {
                    if (!isset($averages[$key])) {
                        $averages[$key] = ['total' => 0, 'count' => 0];
                    }
                    $averages[$key]['total'] += $value;
                    $averages[$key]['count']++;
                }
            }
        }

        foreach ($averages as $key => $data) {
            $avgArrayForTable[$key] = $data['count'] > 0 ? round($data['total'] / $data['count'], 2) : 0;
        }

        $processedData = [];
        foreach ($reportData as $studentGrades) {
            $processedData[] = $studentGrades;
        }
        $processedData[] = [
            'name' => '',
            'lastname' => '',
            'id_user' => '',
            'full_name' => 'میانگین نمرات',
        ] + $avgArrayForTable;

        return [
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => $processedData,
            'averages' => $avgArrayForTable,
        ];
    }

    public function getClassGradeSessions(int $classId, int $lessonId): array
    {
        $query = Grade::where('class_id', $classId)
            ->where('lesson_id', $lessonId)
            ->where('is_report_card', false)
            ->whereNull('deleted_at')
            ->select('gregorian_date', 'grade_type', 'grade_name_for_other_type', 'is_descriptive', 'is_visible')
            ->distinct()
            ->orderBy('gregorian_date', 'desc')
            ->get();

        $sessions = [];
        foreach ($query as $grade) {
            $sessions[] = [
                'gregorian_date' => $grade->gregorian_date,
                'grade_type' => $grade->grade_type,
                'grade_type_label' => $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type),
                'is_descriptive' => $grade->is_descriptive,
                'is_visible' => $grade->is_visible,
            ];
        }

        return [
            'success' => true,
            'message' => 'Grade sessions retrieved successfully',
            'data' => $sessions,
        ];
    }

    public function getStudentGradesForLesson(int $studentId, int $lessonId, int $classId): array
    {
        $grades = Grade::where('student_id', $studentId)
            ->where('lesson_id', $lessonId)
            ->where('class_id', $classId)
            ->where('is_report_card', false)
            ->whereNull('deleted_at')
            ->with('examSession')
            ->orderBy('gregorian_date', 'desc')
            ->get();

        $processedGrades = $grades->map(function ($grade) {
            return [
                'id' => $grade->id,
                'grade_session_id' => $grade->exam_session_id,
                'grade' => $grade->is_descriptive ? $grade->descriptive_value : $grade->calculated_grade,
                'raw_grade' => $grade->raw_grade,
                'calculated_grade' => $grade->calculated_grade,
                'grade_type' => $grade->grade_type,
                'grade_type_label' => $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type),
                'gregorian_date' => $grade->gregorian_date,
                'is_descriptive' => $grade->is_descriptive,
                'descriptive_value' => $grade->descriptive_value,
                'descriptive_label' => $grade->descriptive_label,
                'min_grade' => $grade->min_grade,
                'z_score' => $grade->z_score,
                'is_visible' => $grade->is_visible,
                'explanation' => $grade->explanation,
            ];
        });

        return [
            'success' => true,
            'message' => 'Student grades retrieved successfully',
            'data' => $processedGrades->values(),
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
            ->orderBy('gregorian_date', 'desc')
            ->get();

        $student = User::find($studentId);

        if ($grades->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No report card grades found',
                'data' => [],
            ];
        }

        $gradesByLesson = [];
        $averagesByTerm = [];

        foreach ($grades as $grade) {
            $lessonId = $grade->lesson_id;
            $gradeType = $grade->grade_type;

            if (!isset($gradesByLesson[$lessonId])) {
                $gradesByLesson[$lessonId] = [
                    'lesson_id' => $lessonId,
                    'lesson_name' => $grade->lesson->name ?? '',
                    'grades' => [],
                ];
            }

            $gradesByLesson[$lessonId]['grades'][] = [
                'grade_type' => $grade->grade_type,
                'grade_type_label' => $this->getGradeTypeLabel($grade->grade_type, $grade->grade_name_for_other_type),
                'calculated_grade' => $grade->calculated_grade,
                'gregorian_date' => $grade->gregorian_date,
                'z_score' => $grade->z_score,
            ];

            if (isset($averagesByTerm[$gradeType])) {
                if (!is_array($averagesByTerm[$gradeType])) {
                    $averagesByTerm[$gradeType] = [
                        'total' => 0,
                        'count' => 0,
                    ];
                }
                $averagesByTerm[$gradeType]['total'] += $grade->calculated_grade ?? 0;
                $averagesByTerm[$gradeType]['count']++;
            }
        }

        foreach ($averagesByTerm as $type => $data) {
            if (is_array($data) && $data['count'] > 0) {
                $averagesByTerm[$type] = round($data['total'] / $data['count'], 2);
            } else {
                unset($averagesByTerm[$type]);
            }
        }

        return [
            'success' => true,
            'message' => 'Student report card retrieved successfully',
            'data' => [
                'student_id' => $studentId,
                'student_name' => $student->firstname ?? '',
                'student_lastname' => $student->lastname ?? '',
                'grades_by_lesson' => array_values($gradesByLesson),
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

    private function convertGradeBase(float $grade, float $from, float $to): float
    {
        return round(($grade / $from) * $to, 2);
    }
}