<?php

namespace App\Services;

use App\Models\InPersonExamResult;
use App\Models\User;

class ReportService
{
    public function getSingleLessonReport(int $lessonId, int $classId, ?int $gradeBase = 20): array
    {
        $query = InPersonExamResult::whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
            $q->where('id', $lessonId);
        })
            ->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->where('scaled_score', '!=', null)
            ->with(['student', 'inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.classes']);

        $results = $query->get();

        if ($results->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No results found for this lesson',
                'data' => [],
            ];
        }

        $reportData = [];
        $resultGroups = [];

        foreach ($results as $result) {
            $studentId = $result->user_id;
            $categoryTitle = $result->grade_type ?? '';
            $examDate = $result->exam_date ?? '';
            $isDescriptive = $result->is_descriptive ?? false;
            $groupKey = $examDate.'|'.$categoryTitle;

            if (! isset($resultGroups[$groupKey])) {
                $resultGroups[$groupKey] = [
                    'exam_date' => $examDate,
                    'grade_type' => $categoryTitle,
                    'is_descriptive' => $isDescriptive,
                ];
            }

            if (! isset($reportData[$studentId])) {
                $reportData[$studentId] = [
                    'student_id' => $result->user_id,
                    'student_name' => $result->student->first_name ?? '',
                    'student_last_name' => $result->student->last_name ?? '',
                    'full_name' => $result->student->full_name ?? '',
                ];
            }

            $displayGrade = $this->convertGradeBase(
                $isDescriptive ? $result->raw_score : $result->scaled_score,
                20,
                $gradeBase
            );

            $reportData[$studentId][$categoryTitle.'<br>'.$examDate] = $displayGrade;
        }

        $averages = [];
        foreach ($resultGroups as $group) {
            $displayKey = $group['grade_type'].'<br>'.$group['exam_date'];
            $average = 0;
            $count = 0;
            foreach ($reportData as $studentGrades) {
                if (isset($studentGrades[$displayKey])) {
                    $average += $studentGrades[$displayKey];
                    $count++;
                }
            }
            $averages[$group['grade_type'].'|'.$group['exam_date']] = $count > 0 ? round($average / $count, 2) : 0;
        }

        $reportData[] = [
            'full_name' => 'میانگین نمرات آزمون',
            'averages' => $averages,
        ];

        return [
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => array_values($reportData),
            'grade_groups' => array_values($resultGroups),
        ];
    }

    public function getMultipleLessonsReport(array $lessonIds, int $classId): array
    {
        $reportData = [];

        foreach ($lessonIds as $lessonId) {
            $query = InPersonExamResult::whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
                $q->where('id', $lessonId);
            })
                ->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($classId) {
                    $q->where('class_id', $classId);
                })
                ->where('scaled_score', '!=', null)
                ->with(['student', 'lesson', 'inPersonExamDetail.exam.lesson']);

            $results = $query->get();

            if ($results->isEmpty()) {
                continue;
            }

            foreach ($results as $result) {
                $studentId = $result->user_id;

                if (! isset($reportData[$studentId])) {
                    $reportData[$studentId] = [
                        'id_user' => $studentId,
                        'name' => $result->student->first_name ?? '',
                        'last_name' => $result->student->last_name ?? '',
                        'full_name' => $result->student->full_name ?? '',
                    ];
                }

                $categoryTitle = $result->grade_type ?? '';
                $reportData[$studentId][$categoryTitle.'<br>'.($result->inPersonExamDetail?->exam?->lesson?->name ?? '')] = $result->scaled_score;
            }
        }

        $averages = [];
        $avgArrayForTable = [];
        foreach ($reportData as $studentId => $studentGrades) {
            foreach ($studentGrades as $key => $value) {
                if (! in_array($key, ['id_user', 'name', 'last_name', 'full_name'])) {
                    if (! isset($averages[$key])) {
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
            'last_name' => '',
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

    public function getClassExamSessions(int $classId, int $lessonId): array
    {
        $query = InPersonExamResult::whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
            $q->where('id', $lessonId);
        })
            ->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->with(['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category'])
            ->select('in_person_exam_details.exam_id', 'in_person_exam_details.held_at', 'in_person_exam_details.is_descriptive', 'exams.name', 'exams.exam_category_id', 'exam_categories.title as category_title')
            ->join('in_person_exam_details', 'in_person_exam_results.in_person_exam_id', '=', 'in_person_exam_details.id')
            ->join('exams', 'in_person_exam_details.exam_id', '=', 'exams.id')
            ->leftJoin('exam_categories', 'exams.exam_category_id', '=', 'exam_categories.id')
            ->distinct()
            ->orderBy('in_person_exam_details.held_at', 'desc')
            ->get();

        $sessions = [];
        foreach ($query as $result) {
            $sessions[] = [
                'exam_id' => $result->exam_id ?? $result->inPersonExamDetail?->exam?->id,
                'exam_date' => $result->held_at ?? $result->inPersonExamDetail?->held_at?->toDateString(),
                'exam_name' => $result->name ?? $result->inPersonExamDetail?->exam?->name,
                'grade_type' => $result->category_title ?? $result->inPersonExamDetail?->exam?->category?->title,
                'is_descriptive' => $result->is_descriptive ?? $result->inPersonExamDetail?->is_descriptive,
                'is_visible' => true,
            ];
        }

        return [
            'success' => true,
            'message' => 'Exam sessions retrieved successfully',
            'data' => $sessions,
        ];
    }

    public function getStudentResultsForLesson(int $studentId, int $lessonId, int $classId): array
    {
        $query = InPersonExamResult::where('user_id', $studentId)
            ->whereHas('inPersonExamDetail.exam.lesson', function ($q) use ($lessonId) {
                $q->where('id', $lessonId);
            })
            ->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->with(['inPersonExamDetail', 'inPersonExamDetail.exam', 'inPersonExamDetail.exam.category', 'inPersonExamDetail.exam.classes'])
            ->orderBy('in_person_exam_details.held_at', 'desc');

        $results = $query->get();

        $processedResults = $results->map(function ($result) {
            return [
                'id' => $result->id,
                'in_person_exam_id' => $result->in_person_exam_id,
                'exam_id' => $result->exam_id,
                'grade' => $result->scaled_score,
                'raw_grade' => $result->raw_score,
                'grade_type' => $result->grade_type,
                'exam_date' => $result->exam_date,
                'is_descriptive' => $result->is_descriptive,
                'descriptive_value' => $result->raw_score,
                'descriptive_label' => $this->getDescriptiveLabel($result->raw_score),
                'min_passing_score' => $result->inPersonExamDetail?->exam?->min_passing_score,
                'z_score' => $result->z_score,
                'is_visible' => true,
                'explanation' => null,
            ];
        });

        return [
            'success' => true,
            'message' => 'Student results retrieved successfully',
            'data' => $processedResults->values(),
        ];
    }

    private function getDescriptiveLabel(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ((int) $value) {
            1 => 'خیلی خوب',
            2 => 'خوب',
            3 => 'قابل قبول',
            4 => 'نیاز به آموزش و تلاش بیشتر',
            default => null,
        };
    }

    public function getStudentReportCard(int $studentId, int $classId): array
    {
        $reportCardTypes = [
            'mid_term_1' => 'میان ترم اول',
            'continuous_1' => 'مستمر اول',
            'final_1' => 'پایان ترم اول',
            'mid_term_2' => 'میان ترم دوم',
            'continuous_2' => 'مستمر دوم',
            'final_2' => 'پایان ترم دوم',
        ];

        $results = InPersonExamResult::where('user_id', $studentId)
            ->where('scaled_score', '!=', null)
            ->whereHas('inPersonExamDetail.exam.classes', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            })
            ->whereHas('inPersonExamDetail.exam.category', function ($q) use ($reportCardTypes) {
                $q->whereIn('title', array_keys($reportCardTypes));
            })
            ->with(['inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.category'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($results->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No report card results found',
                'data' => [],
            ];
        }

        $resultsByLesson = [];
        $averagesByTerm = [];

        foreach ($results as $result) {
            $lessonId = $result->lesson_id;
            $gradeType = $result->grade_type;

            if (! isset($resultsByLesson[$lessonId])) {
                $resultsByLesson[$lessonId] = [
                    'lesson_id' => $lessonId,
                    'lesson_name' => $result->inPersonExamDetail?->exam?->lesson?->name ?? '',
                    'grades' => [],
                ];
            }

            $resultsByLesson[$lessonId]['grades'][] = [
                'grade_type' => $gradeType,
                'grade_type_label' => $this->getGradeTypeLabel($gradeType),
                'calculated_grade' => $result->scaled_score,
                'grade_date' => $result->exam_date,
                'z_score' => $result->z_score,
            ];

            if (isset($averagesByTerm[$gradeType])) {
                $averagesByTerm[$gradeType]['total'] += $result->scaled_score ?? 0;
                $averagesByTerm[$gradeType]['count']++;
            } else {
                $averagesByTerm[$gradeType] = ['total' => $result->scaled_score ?? 0, 'count' => 1];
            }
        }

        foreach ($averagesByTerm as $type => $data) {
            $averagesByTerm[$type] = $data['count'] > 0 ? round($data['total'] / $data['count'], 2) : 0;
        }

        $student = User::find($studentId);

        return [
            'success' => true,
            'message' => 'Student report card retrieved successfully',
            'data' => [
                'student_id' => $studentId,
                'student_name' => $student->first_name ?? '',
                'student_last_name' => $student->last_name ?? '',
                'grades_by_lesson' => array_values($resultsByLesson),
                'term_averages' => $averagesByTerm,
            ],
        ];
    }

    private function getGradeTypeLabel(?string $gradeType): string
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
            default => $gradeType ?? 'ناشناخته',
        };
    }

    private function convertGradeBase(float $grade, float $from, float $to): float
    {
        return round(($grade / $from) * $to, 2);
    }
}
