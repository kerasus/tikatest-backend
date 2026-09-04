<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Exam;
use App\Models\InPersonExamResult;
use App\Models\Lesson;
use App\Models\OnlineExamSession;
use App\Models\OnlineExamSessionResult;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TermEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|exists:users,id',
            'school_id' => 'nullable|exists:schools,id',
            'term_id' => 'nullable|exists:academic_terms,id',
            'class_id' => 'nullable|exists:classes,id',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $studentId = $request->filled('student_id')
            ? $request->integer('student_id')
            : auth()->id();

        $student = User::with([
            'studentProfile',
            'userClassRegistrations.schoolClass.academicLevel.academicField.school',
            'userClassRegistrations.term',
        ])->findOrFail($studentId);

        $enrollments = TermEnrollment::where('user_id', $studentId)
            ->with(['schoolClass.academicLevel', 'term', 'school'])
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->school_id))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->class_id))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->term_id))
            ->get();

        return $this->jsonResponseOk([
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_code' => $student->studentProfile?->code,
                'username' => $student->username,
            ],
            'enrollments' => $enrollments->map(fn ($e) => [
                'class_id' => $e->class_id,
                'class_name' => $e->schoolClass?->name,
                'term_id' => $e->term_id,
                'term_name' => $e->term?->name,
                'school_id' => $e->school_id,
            ]),
        ]);
    }

    public function studentReport(Request $request, $studentId): JsonResponse
    {
        $request->validate([
            'term_id' => 'nullable|exists:academic_terms,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'scope' => 'in:in_person,online,both',
        ]);

        $student = User::findOrFail($studentId);

        $inPersonResults = InPersonExamResult::where('user_id', $studentId)
            ->whereNotNull('scaled_score')
            ->with([
                'inPersonExamDetail.exam.lesson',
                'inPersonExamDetail.exam.category',
                'inPersonExamDetail',
            ])
            ->when($request->filled('term_id'), function ($q) use ($request) {
                $q->whereHas('inPersonExamDetail.exam', fn ($q) => $q->where('term_id', $request->term_id));
            })
            ->when($request->filled('lesson_id'), function ($q) use ($request) {
                $q->whereHas('inPersonExamDetail.exam', fn ($q) => $q->where('lesson_id', $request->lesson_id));
            })
            ->get();

        $onlineResults = OnlineExamSessionResult::where('student_id', $studentId)
            ->with([
                'exam.lesson',
                'exam.category',
                'onlineExamBooklet.lesson',
            ])
            ->when($request->filled('term_id'), function ($q) use ($request) {
                $q->whereHas('exam', fn ($q) => $q->where('term_id', $request->term_id));
            })
            ->when($request->filled('lesson_id'), function ($q) use ($request) {
                $q->where('lesson_id', $request->lesson_id)
                    ->orWhereHas('exam', fn ($q) => $q->where('lesson_id', $request->lesson_id));
            })
            ->get();

        $scope = $request->input('scope', 'both');

        $lessons = [];

        $inPersonLessons = collect();
        if (in_array($scope, ['in_person', 'both'])) {
            foreach ($inPersonResults as $result) {
                $lesson = $result->inPersonExamDetail?->exam?->lesson;
                $lessonId = $lesson?->id;
                if (!$lessonId) continue;

                if (!isset($lessons[$lessonId])) {
                    $lessons[$lessonId] = [
                        'id' => $lessonId,
                        'name' => $lesson->name,
                        'coefficient' => $lesson->coefficient,
                        'results' => [],
                        'in_person_results' => [],
                        'online_results' => [],
                    ];
                }

                $gradeType = $result->inPersonExamDetail?->exam?->category?->title ?? 'سایر';
                $lessons[$lessonId]['in_person_results'][] = [
                    'id' => $result->id,
                    'exam_id' => $result->inPersonExamDetail?->exam?->id,
                    'exam_name' => $result->inPersonExamDetail?->exam?->name,
                    'category' => $gradeType,
                    'raw_score' => $result->raw_score,
                    'scaled_score' => $result->scaled_score,
                    'percent' => $result->percent,
                    'is_descriptive' => $result->inPersonExamDetail?->is_descriptive,
                    'held_at' => $result->inPersonExamDetail?->held_at,
                ];
            }
        }

        if (in_array($scope, ['online', 'both'])) {
            foreach ($onlineResults as $result) {
                $lesson = $result->lesson ?? $result->exam?->lesson ?? $result->onlineExamBooklet?->lesson;
                $lessonId = $lesson?->id;
                if (!$lessonId) continue;

                if (!isset($lessons[$lessonId])) {
                    $lessons[$lessonId] = [
                        'id' => $lessonId,
                        'name' => $lesson->name,
                        'coefficient' => $lesson->coefficient,
                        'results' => [],
                        'in_person_results' => [],
                        'online_results' => [],
                    ];
                }

                $gradeType = $result->exam?->category?->title ?? 'سایر';
                $lessons[$lessonId]['online_results'][] = [
                    'id' => $result->id,
                    'exam_id' => $result->exam_id,
                    'exam_name' => $result->exam?->name,
                    'booklet_id' => $result->online_exam_booklet_id,
                    'booklet_title' => $result->onlineExamBooklet?->title,
                    'category' => $gradeType,
                    'scope' => $result->scope,
                    'raw_score' => $result->raw_score,
                    'scaled_score' => $result->scaled_score,
                    'percent' => $result->percent,
                    'correct_count' => $result->correct_count,
                    'question_count' => $result->question_count,
                    'max_score' => $result->max_score,
                ];
            }
        }

        foreach ($lessons as $lessonId => &$lessonData) {
            $allScores = [];
            $allMaxScores = [];
            foreach (array_merge($lessonData['in_person_results'], $lessonData['online_results']) as $r) {
                $allScores[] = (float) ($r['scaled_score'] ?? $r['raw_score']);
                $allMaxScores[] = (float) $r['max_score'];
            }

            $lessonData['results'] = [
                'total_count' => count($allScores),
                'avg_score' => count($allScores) > 0 ? round(array_sum($allScores) / count($allScores), 2) : 0,
                'max_score' => max($allMaxScores) ?: max($allScores) ?: 0,
            ];
        }

        return $this->jsonResponseOk([
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name,
                'last_name' => $student->last_name,
            ],
            'lessons' => array_values($lessons),
        ]);
    }

    public function classReport(Request $request, $classId): JsonResponse
    {
        $request->validate([
            'term_id' => 'nullable|exists:academic_terms,id',
        ]);

        $enrollments = TermEnrollment::where('class_id', $classId)
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->term_id))
            ->with('user')
            ->get();

        $students = $enrollments->map(fn ($e) => [
            'student_id' => $e->user_id,
            'name' => $e->user->first_name,
            'last_name' => $e->user->last_name,
        ]);

        return $this->jsonResponseOk($students);
    }


    public function classReportCards(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'term_id' => 'nullable|exists:academic_terms,id',
            'class_id' => 'required|exists:classes,id',
        ]);

        $classId = $request->integer('class_id');
        $termId = $request->filled('term_id') ? $request->integer('term_id') : null;

        $class = SchoolClass::with('academicLevel.academicField.school')->findOrFail($classId);
        $school = $class->academicLevel?->academicField?->school;

        $term = $termId
            ? AcademicTerm::find($termId)
            : TermEnrollment::where('class_id', $classId)
            ->whereHas('term', fn ($q) => $q->where('is_active', true))
            ->with('term')
            ->first()?->term
            ?? AcademicTerm::where('is_active', true)->first();

        $enrollments = TermEnrollment::where('class_id', $classId)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->with('user.studentProfile')
            ->get();

        $studentIds = $enrollments->pluck('user_id')->toArray();

        // ۱. واکشی تمام نتایج کلاسی برای محاسبه یکجای آمار و تراز (بدون N+1)
        $allInPersonResults = InPersonExamResult::whereIn('user_id', $studentIds)
            ->whereNotNull('scaled_score')
            ->with([
                'inPersonExamDetail.exam.lesson',
                'inPersonExamDetail.exam.category',
                'inPersonExamDetail',
            ])
            ->when($termId, fn ($q) => $q->whereHas('inPersonExamDetail.exam', fn ($q) => $q->where('term_id', $termId)))
            ->get();

        $allOnlineResults = OnlineExamSessionResult::whereIn('student_id', $studentIds)
            ->with([
                'exam.lesson',
                'exam.category',
                'onlineExamBooklet.lesson',
            ])
            ->when($termId, fn ($q) => $q->whereHas('exam', fn ($q) => $q->where('term_id', $termId)))
            ->get();

        // ۲. محاسبه آماره‌ها و تراز برای هر آزمون حضوری و آنلاین در سطح کلاس
        $inPersonExamStats = $this->calculateExamStats($allInPersonResults, 'in_person');
        $onlineExamStats = $this->calculateExamStats($allOnlineResults, 'online');

        // ۳. ساخت خروجی کارنامه برای هر دانش‌آموز
        $students = $enrollments->map(function ($e) use ($allInPersonResults, $allOnlineResults, $inPersonExamStats, $onlineExamStats) {
            $student = $e->user;

            $studentInPerson = $allInPersonResults->where('user_id', $student->id);
            $studentOnline = $allOnlineResults->where('student_id', $student->id);

            return [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name ?? $student->name,
                    'last_name' => $student->last_name,
                    'student_code' => $student->studentProfile?->code,
                    'username' => $student->username,
                ],
                'lessons' => $this->buildLessonsWithStats(
                    $studentInPerson,
                    $studentOnline,
                    $inPersonExamStats,
                    $onlineExamStats
                ),
            ];
        })->values()->toArray();

        return $this->jsonResponseOk([
            'school' => [
                'id' => $school?->id,
                'name' => $school?->name,
                'address' => $school?->address,
                'phone' => $school?->phone,
            ],
            'term' => $term ? ['id' => $term->id, 'name' => $term->name, 'type' => $term->type] : null,
            'class' => ['id' => $class->id, 'name' => $class->name],
            'students' => $students,
        ]);
    }

    /**
     * محاسبه آماره‌های توصیفی و انحراف معیار جهت تراز
     */
    private function calculateExamStats($results, string $type): array
    {
        $grouped = $results->groupBy(function ($item) use ($type) {
            return $type === 'in_person'
                ? ($item->inPersonExamDetail?->in_person_exam_id ?? $item->exam_id)
                : $item->exam_id;
        });

        $stats = [];
        foreach ($grouped as $examId => $items) {
            $scores = $items->map(fn($i) => (float)($i->scaled_score ?? $i->raw_score ?? 0))->values();
            $count = $scores->count();

            if ($count === 0) continue;

            $avg = $scores->avg();
            $max = $scores->max();
            $min = $scores->min();

            // محاسبه انحراف معیار (Standard Deviation)
            $variance = $scores->reduce(fn($carry, $val) => $carry + pow($val - $avg, 2), 0) / ($count > 1 ? ($count - 1) : 1);
            $stdDev = sqrt($variance);

            // محاسبه بیشترین و کمترین تراز ممکن در این آزمون
            $maxTaraz = $stdDev > 0 ? round(((($max - $avg) / $stdDev) * 1000) + 5000) : 5000;
            $minTaraz = $stdDev > 0 ? round(((($min - $avg) / $stdDev) * 1000) + 5000) : 5000;

            $stats[$examId] = [
                'avg_grade' => round($avg, 2),
                'max_grade' => $max,
                'min_grade' => $min,
                'std_dev' => round($stdDev, 2),
                'max_taraz' => $maxTaraz,
                'min_taraz' => $minTaraz,
                'count' => $count,
            ];
        }

        return $stats;
    }

    private function buildLessonsWithStats($inPersonResults, $onlineResults, $inPersonStats, $onlineStats): array
    {
        $lessonsMap = [];

        // نتایج آزمون‌های حضوری دانش‌آموز
        foreach ($inPersonResults as $res) {
            $examDetail = $res->inPersonExamDetail;
            $exam = $examDetail?->exam;
            $lesson = $exam?->lesson;
            if (!$lesson) continue;

            $lessonId = $lesson->id;
            if (!isset($lessonsMap[$lessonId])) {
                $lessonsMap[$lessonId] = [
                    'id' => $lesson->id,
                    'name' => $lesson->name,
                    'coefficient' => $lesson->coefficient ?? '1.00',
                    'in_person_results' => [],
                    'online_results' => [],
                ];
            }

            $examId = $examDetail->in_person_exam_id ?? $exam->id;
            $stat = $inPersonStats[$examId] ?? null;
            $studentScore = (float)($res->scaled_score ?? $res->raw_score ?? 0);

            // محاسبه تراز دانش‌آموز
            $studentTaraz = 5000;
            if ($stat && $stat['std_dev'] > 0) {
                $studentTaraz = round(((($studentScore - $stat['avg_grade']) / $stat['std_dev']) * 1000) + 5000);
            }

            $lessonsMap[$lessonId]['in_person_results'][] = [
                'id' => $res->id,
                'exam_id' => $examId,
                'exam_name' => $exam->title ?? $exam->name ?? 'آزمون حضوری',
                'category' => $exam->category?->name ?? 'آزمون کلاسی',
                'raw_score' => $res->raw_score,
                'scaled_score' => $res->scaled_score,
                'student_grade' => $studentScore,
                'avg_grade' => $stat['avg_grade'] ?? $studentScore,
                'max_grade' => $stat['max_grade'] ?? $studentScore,
                'min_grade' => $stat['min_grade'] ?? $studentScore,
                'student_score' => $studentTaraz, // تراز دانش‌آموز
                'max_score' => $stat['max_taraz'] ?? 5000, // بالاترین تراز
                'min_score' => $stat['min_taraz'] ?? 5000, // پایین‌ترین تراز
                'avg_score' => 5000, // میانگین تراز همیشه 5000 است
                'percent' => $res->percent,
                'held_at' => $exam->held_at ?? $res->created_at,
            ];
        }

        // نتایج آنلاین دانش‌آموز
        foreach ($onlineResults as $res) {
            $exam = $res->exam;
            $booklet = $res->onlineExamBooklet;
            $lesson = $booklet?->lesson ?? $exam?->lesson;
            if (!$lesson) continue;

            $lessonId = $lesson->id;
            if (!isset($lessonsMap[$lessonId])) {
                $lessonsMap[$lessonId] = [
                    'id' => $lesson->id,
                    'name' => $lesson->name,
                    'coefficient' => $lesson->coefficient ?? '1.00',
                    'in_person_results' => [],
                    'online_results' => [],
                ];
            }

            $examId = $exam->id;
            $stat = $onlineStats[$examId] ?? null;
            $studentScore = (float)($res->scaled_score ?? $res->raw_score ?? 0);

            $studentTaraz = 5000;
            if ($stat && $stat['std_dev'] > 0) {
                $studentTaraz = round(((($studentScore - $stat['avg_grade']) / $stat['std_dev']) * 1000) + 5000);
            }

            $lessonsMap[$lessonId]['online_results'][] = [
                'id' => $res->id,
                'exam_id' => $examId,
                'booklet_title' => $booklet?->title ?? 'دفترچه اصلی',
                'exam_name' => $exam->title ?? $exam->name ?? 'آزمون آنلاین',
                'raw_score' => $res->raw_score,
                'scaled_score' => $res->scaled_score,
                'student_grade' => $studentScore,
                'avg_grade' => $stat['avg_grade'] ?? $studentScore,
                'max_grade' => $stat['max_grade'] ?? $studentScore,
                'min_grade' => $stat['min_grade'] ?? $studentScore,
                'student_score' => $studentTaraz,
                'max_score' => $stat['max_taraz'] ?? 5000,
                'min_score' => $stat['min_taraz'] ?? 5000,
                'avg_score' => 5000,
                'held_at' => $exam->held_at ?? $res->created_at,
            ];
        }

        // محاسبه میانگین کل هر درس برای دانش‌آموز
        foreach ($lessonsMap as &$lessonData) {
            $allScores = array_merge(
                array_column($lessonData['in_person_results'], 'student_grade'),
                array_column($lessonData['online_results'], 'student_grade')
            );
            $count = count($allScores);
            $lessonData['results'] = [
                'total_count' => $count,
                'avg_score' => $count > 0 ? round(array_sum($allScores) / $count, 2) : 0,
                'max_score' => $count > 0 ? max($allScores) : 0,
            ];
        }

        return array_values($lessonsMap);
    }

    protected function buildLessonsFromResults ($inPersonResults, $onlineResults): array
    {
        $lessons = [];

        foreach ($inPersonResults as $result) {
            $lesson = $result->inPersonExamDetail?->exam?->lesson;
            $lessonId = $lesson?->id;
            if (!$lessonId) continue;

            if (!isset($lessons[$lessonId])) {
                $lessons[$lessonId] = [
                    'id' => $lessonId,
                    'name' => $lesson->name,
                    'coefficient' => $lesson->coefficient,
                    'in_person_results' => [],
                    'online_results' => [],
                ];
            }

            $gradeType = $result->inPersonExamDetail?->exam?->category?->title ?? 'سایر';
            $lessons[$lessonId]['in_person_results'][] = [
                'id' => $result->id,
                'exam_id' => $result->inPersonExamDetail?->exam?->id,
                'exam_name' => $result->inPersonExamDetail?->exam?->name,
                'category' => $gradeType,
                'raw_score' => $result->raw_score,
                'scaled_score' => $result->scaled_score,
                'percent' => $result->percent,
                'is_descriptive' => $result->inPersonExamDetail?->is_descriptive,
                'held_at' => $result->inPersonExamDetail?->held_at,
            ];
        }

        foreach ($onlineResults as $result) {
            $lesson = $result->lesson ?? $result->exam?->lesson ?? $result->onlineExamBooklet?->lesson;
            $lessonId = $lesson?->id;
            if (!$lessonId) continue;

            if (!isset($lessons[$lessonId])) {
                $lessons[$lessonId] = [
                    'id' => $lessonId,
                    'name' => $lesson->name,
                    'coefficient' => $lesson->coefficient,
                    'in_person_results' => [],
                    'online_results' => [],
                ];
            }

            $gradeType = $result->exam?->category?->title ?? 'سایر';
            $lessons[$lessonId]['online_results'][] = [
                'id' => $result->id,
                'exam_id' => $result->exam_id,
                'exam_name' => $result->exam?->name,
                'booklet_id' => $result->online_exam_booklet_id,
                'booklet_title' => $result->onlineExamBooklet?->title,
                'category' => $gradeType,
                'scope' => $result->scope,
                'raw_score' => $result->raw_score,
                'scaled_score' => $result->scaled_score,
                'percent' => $result->percent,
                'correct_count' => $result->correct_count,
                'question_count' => $result->question_count,
                'max_score' => $result->max_score,
            ];
        }

        foreach ($lessons as $lessonId => &$lessonData) {
            $allScores = [];
            foreach (array_merge($lessonData['in_person_results'], $lessonData['online_results']) as $r) {
                $allScores[] = (float) ($r['scaled_score'] ?? $r['raw_score']);
            }
            $lessonData['results'] = [
                'total_count' => count($allScores),
                'avg_score' => count($allScores) > 0 ? round(array_sum($allScores) / count($allScores), 2) : 0,
                'max_score' => max($allScores) ?: 0,
            ];
        }

        return array_values($lessons);
    }

        public function comprehensiveReport (Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'term_id' => 'nullable|exists:academic_terms,id',
            'class_id' => 'nullable|exists:classes,id',
            'category_id' => 'nullable|exists:exam_categories,id',
        ]);

        $classId = $request->integer('class_id');
        $termId = $request->filled('term_id') ? $request->integer('term_id') : null;
        $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;

        $class = SchoolClass::with('academicLevel.academicField.school')->findOrFail($classId);
        $school = $class->academicLevel?->academicField?->school;
        $term = $termId ? AcademicTerm::find($termId) : null;

        $enrollments = TermEnrollment::where('class_id', $classId)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->with('user.studentProfile')
            ->get();

        $studentIds = $enrollments->pluck('user_id')->toArray();

        $examQuery = Exam::whereHas('classes', fn ($q) => $q->where('classes.id', $classId))
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with(['lesson', 'category', 'inPersonExamDetail']);

        $exams = $examQuery->get();

        $allInPersonResults = InPersonExamResult::whereIn('user_id', $studentIds)
            ->whereNotNull('scaled_score')
            ->whereHas('inPersonExamDetail.exam', fn ($q) => $q->whereIn('id', $exams->pluck('id')))
            ->with(['inPersonExamDetail.exam.lesson', 'inPersonExamDetail.exam.category', 'inPersonExamDetail'])
            ->get();

        $allOnlineResults = OnlineExamSessionResult::whereIn('student_id', $studentIds)
            ->whereHas('exam', fn ($q) => $q->whereIn('id', $exams->pluck('id')))
            ->with(['exam.lesson', 'exam.category', 'onlineExamBooklet.lesson'])
            ->get();

        $inPersonExamStats = $this->calculateExamStats($allInPersonResults, 'in_person');
        $onlineExamStats = $this->calculateExamStats($allOnlineResults, 'online');

        $classLessonStats = $this->calculateClassLessonStats($allInPersonResults, $allOnlineResults, $exams, $inPersonExamStats, $onlineExamStats);

        $students = $enrollments->map(function ($e) use ($exams, $inPersonExamStats, $onlineExamStats, $classLessonStats, $allInPersonResults, $allOnlineResults) {
            $student = $e->user;

            $studentInPerson = $allInPersonResults->where('user_id', $student->id);
            $studentOnline = $allOnlineResults->where('student_id', $student->id);

            $lessons = [];
            foreach ($exams as $exam) {
                $lesson = $exam->lesson;
                if (!$lesson) continue;

                $lessonId = $lesson->id;
                if (!isset($lessons[$lessonId])) {
                    $lessons[$lessonId] = [
                        'id' => $lesson->id,
                        'name' => $lesson->name,
                        'coefficient' => $lesson->coefficient,
                        'exam_scores' => [],
                    ];
                }

                $examResults = $studentInPerson->filter(fn ($r) => $r->inPersonExamDetail?->exam?->lesson_id === $lessonId);
                $onlineExamResults = $studentOnline->filter(fn ($r) => ($r->exam?->lesson_id ?? $r->lesson_id) === $lessonId);

                $allScores = [];
                foreach ($examResults as $r) {
                    $allScores[] = (float) ($r->scaled_score ?? $r->raw_score);
                }
                foreach ($onlineExamResults as $r) {
                    $allScores[] = (float) ($r->scaled_score ?? $r->raw_score);
                }

                if (count($allScores) > 0) {
                    $studentAvg = round(array_sum($allScores) / count($allScores), 2);
                    $lessons[$lessonId]['exam_scores'][] = [
                        'exam_id' => $exam->id,
                        'exam_name' => $exam->name,
                        'category' => $exam->category?->title ?? 'سایر',
                        'score' => $studentAvg,
                        'count' => count($allScores),
                    ];
                }
            }

            foreach ($lessons as $lessonId => $lessonData) {
                $scores = array_column($lessonData['exam_scores'], 'score');
                $studentAvg = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;

                $classStat = $classLessonStats[$lessonId] ?? null;
                $classAvg = $classStat['class_avg'] ?? null;
                $classMax = $classStat['class_max'] ?? null;
                $classMin = $classStat['class_min'] ?? null;

                $studentScore = $studentAvg !== null ? (float) $studentAvg : 0;
                $studentTaraz = 5000;
                if ($classAvg !== null && $classStat['std_dev'] > 0) {
                    $studentTaraz = round((($studentScore - $classAvg) / $classStat['std_dev']) * 1000) + 5000;
                }

                $lessons[$lessonId] = array_merge($lessonData, [
                    'student_avg' => $studentAvg,
                    'class_max' => $classMax,
                    'class_min' => $classMin,
                    'class_avg' => $classAvg,
                    'student_score' => $studentTaraz,
                    'max_score' => $classStat['max_taraz'] ?? 5000,
                    'min_score' => $classStat['min_taraz'] ?? 5000,
                    'avg_score' => 5000,
                ]);
            }

            return [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'student_code' => $student->studentProfile?->code,
                ],
                'lessons' => array_values($lessons),
            ];
        })->values()->toArray();

        return $this->jsonResponseOk([
            'school' => [
                'id' => $school?->id,
                'name' => $school?->name,
                'address' => $school?->address,
                'phone' => $school?->phone,
            ],
            'term' => $term ? ['id' => $term->id, 'name' => $term->name] : null,
            'class' => ['id' => $class->id, 'name' => $class->name],
            'students' => $students,
        ]);
    }

    private function calculateClassLessonStats($inPersonResults, $onlineResults, $exams, $inPersonStats, $onlineStats): array
    {
        $stats = [];

        foreach ($exams as $exam) {
            $lesson = $exam->lesson;
            if (!$lesson) continue;

            $lessonId = $lesson->id;
            if (!isset($stats[$lessonId])) {
                $stats[$lessonId] = [
                    'lesson_id' => $lessonId,
                    'lesson_name' => $lesson->name,
                    'scores' => [],
                    'student_avgs' => [],
                ];
            }

            $examInPerson = $inPersonResults->filter(fn ($r) => ($r->inPersonExamDetail?->exam?->lesson_id ?? null) === $lessonId);
            $examOnline = $onlineResults->filter(fn ($r) => ($r->exam?->lesson_id ?? $r->lesson_id) === $lessonId);

            $studentScores = [];
            foreach ($examInPerson as $r) {
                $studentScores[$r->user_id] = ($studentScores[$r->user_id] ?? 0) + (float) ($r->scaled_score ?? $r->raw_score);
            }
            foreach ($examOnline as $r) {
                $studentScores[$r->student_id] = ($studentScores[$r->student_id] ?? 0) + (float) ($r->scaled_score ?? $r->raw_score);
            }

            foreach ($studentScores as $studentId => $score) {
                $stats[$lessonId]['scores'][] = $score;
            }
        }

        foreach ($stats as $lessonId => $stat) {
            $scores = $stat['scores'];
            $count = count($scores);

            $classMax = $count > 0 ? max($scores) : null;
            $classMin = $count > 0 ? min($scores) : null;
            $classAvg = $count > 0 ? round(array_sum($scores) / $count, 2) : null;

            $variance = $count > 1 ? array_sum(array_map(fn ($s) => pow($s - ($classAvg ?? 0), 2), $scores)) / ($count - 1) : 0;
            $stdDev = sqrt($variance);

            $maxTaraz = $stdDev > 0 ? round(((($classMax ?? 0) - ($classAvg ?? 0)) / $stdDev) * 1000) + 5000 : 5000;
            $minTaraz = $stdDev > 0 ? round(((($classMin ?? 0) - ($classAvg ?? 0)) / $stdDev) * 1000) + 5000 : 5000;

            $stats[$lessonId] = array_merge($stat, [
                'class_max' => $classMax,
                'class_min' => $classMin,
                'class_avg' => $classAvg,
                'std_dev' => round($stdDev, 2),
                'max_taraz' => $maxTaraz,
                'min_taraz' => $minTaraz,
            ]);
        }

        return $stats;
    }    public function gradeMatrix (Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'lesson_id' => 'required|exists:lessons,id',
            'term_id' => 'nullable|exists:academic_terms,id',
        ]);

        $classId = $request->integer('class_id');
        $lessonId = $request->integer('lesson_id');
        $termId = $request->filled('term_id') ? $request->integer('term_id') : null;

        $class = SchoolClass::with('academicLevel.academicField.school')->findOrFail($classId);
        $school = $class->academicLevel?->academicField?->school;
        $term = $termId ? AcademicTerm::find($termId) : null;

        $exams = Exam::whereHas('classes', fn ($q) => $q->where('classes.id', $classId))
            ->where('lesson_id', $lessonId)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->with(['category', 'inPersonExamDetail'])
            ->orderBy('id')
            ->get();

        $enrollments = TermEnrollment::where('class_id', $classId)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->with('user.studentProfile')
            ->get();

        $examIds = $exams->pluck('id')->toArray();

        $students = $enrollments->map(function ($e) use ($examIds, $exams) {
            $student = $e->user;

            $inPersonResults = InPersonExamResult::where('user_id', $student->id)
                ->whereNotNull('scaled_score')
                ->whereHas('inPersonExamDetail.exam', fn ($q) => $q->whereIn('id', $examIds))
                ->with('inPersonExamDetail.exam')
                ->get()
                ->keyBy(fn ($r) => $r->inPersonExamDetail?->exam?->id);

            $onlineResults = OnlineExamSessionResult::where('student_id', $student->id)
                ->whereHas('exam', fn ($q) => $q->whereIn('id', $examIds))
                ->with('exam')
                ->get()
                ->keyBy(fn ($r) => $r->exam_id);

            $scores = [];
            $allScores = [];
            foreach ($exams as $exam) {
                $result = $inPersonResults->get($exam->id) ?? $onlineResults->get($exam->id);
                $score = $result ? ($result->scaled_score ?? $result->raw_score) : null;
                $scores[] = $score !== null ? (float) $score : null;
                if ($score !== null) {
                    $allScores[] = (float) $score;
                }
            }

            $avg = count($allScores) > 0 ? round(array_sum($allScores) / count($allScores), 2) : null;

            return [
                'student_id' => $student->id,
                'name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_code' => $student->studentProfile?->code,
                'scores' => $scores,
                'avg_score' => $avg,
            ];
        })->toArray();

        $examStats = [];
        foreach ($exams as $exam) {
            $examScores = [];
            foreach ($students as $student) {
                $idx = $exams->search(fn ($e) => $e->id === $exam->id);
                if ($idx !== false && $student['scores'][$idx] !== null) {
                    $examScores[] = $student['scores'][$idx];
                }
            }
            $examStats[] = [
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'category' => $exam->category?->title ?? 'سایر',
                'max_score' => count($examScores) > 0 ? max($examScores) : null,
                'min_score' => count($examScores) > 0 ? min($examScores) : null,
                'avg_score' => count($examScores) > 0 ? round(array_sum($examScores) / count($examScores), 2) : null,
            ];
        }

        return $this->jsonResponseOk([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'address' => $school->address,
                'phone' => $school->phone,
            ],
            'term' => $term ? ['id' => $term->id, 'name' => $term->name] : null,
            'class' => ['id' => $class->id, 'name' => $class->name],
            'lesson' => ['id' => $lessonId, 'name' => Lesson::find($lessonId)?->name],
            'exams' => $exams->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'category' => $e->category?->title ?? 'سایر',
                'held_at' => $e->inPersonExamDetail?->held_at ?? null,
            ]),
            'students' => $students,
            'exam_stats' => $examStats,
            'class_avg' => count($students) > 0 ? round(array_sum(array_column($students, 'avg_score')) / count($students), 2) : null,
        ]);
    }

    public function classGradeSheet (Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'nullable|exists:academic_terms,id',
            'lesson_ids' => 'nullable|array',
            'lesson_ids.*' => 'exists:lessons,id',
            'exam_ids' => 'nullable|array',
            'exam_ids.*' => 'exists:exams,id',
        ]);

        $classId = $request->integer('class_id');
        $termId = $request->filled('term_id') ? $request->integer('term_id') : null;
        $lessonIds = $request->input('lesson_ids', []);
        $examIds = $request->input('exam_ids', []);

        $class = SchoolClass::with('academicLevel.academicField.school')->findOrFail($classId);
        $school = $class->academicLevel?->academicField?->school;
        $term = $termId ? AcademicTerm::find($termId) : null;

        $examsQuery = Exam::whereHas('classes', fn ($q) => $q->where('classes.id', $classId))
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->with(['lesson', 'category']);

        if (count($lessonIds) > 0) {
            $examsQuery->whereIn('lesson_id', $lessonIds);
        }
        if (count($examIds) > 0) {
            $examsQuery->whereIn('id', $examIds);
        }

        $exams = $examsQuery->orderBy('lesson_id')->get();
        $selectedExamIds = $exams->pluck('id')->toArray();

        $enrollments = TermEnrollment::where('class_id', $classId)
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->with('user.studentProfile')
            ->get();

        $students = $enrollments->map(function ($e) use ($selectedExamIds, $exams) {
            $student = $e->user;

            $inPersonResults = InPersonExamResult::where('user_id', $student->id)
                ->whereNotNull('scaled_score')
                ->whereHas('inPersonExamDetail.exam', fn ($q) => $q->whereIn('id', $selectedExamIds))
                ->with('inPersonExamDetail.exam')
                ->get()
                ->keyBy(fn ($r) => $r->inPersonExamDetail?->exam?->id);

            $onlineResults = OnlineExamSessionResult::where('student_id', $student->id)
                ->whereHas('exam', fn ($q) => $q->whereIn('id', $selectedExamIds))
                ->with('exam')
                ->get()
                ->keyBy(fn ($r) => $r->exam_id);

            $scores = [];
            $lessonAvgs = [];
            foreach ($exams as $exam) {
                $result = $inPersonResults->get($exam->id) ?? $onlineResults->get($exam->id);
                $score = $result ? ($result->scaled_score ?? $result->raw_score) : null;
                $scores[] = $score !== null ? (float) $score : null;

                $lessonId = $exam->lesson_id;
                if (!isset($lessonAvgs[$lessonId])) {
                    $lessonAvgs[$lessonId] = [];
                }
                if ($score !== null) {
                    $lessonAvgs[$lessonId][] = (float) $score;
                }
            }

            $overallAvg = count(array_filter($scores, fn ($s) => $s !== null)) > 0
                ? round(array_sum(array_filter($scores, fn ($s) => $s !== null)) / count(array_filter($scores, fn ($s) => $s !== null)), 2)
                : null;

            return [
                'student_id' => $student->id,
                'name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_code' => $student->studentProfile?->code,
                'scores' => $scores,
                'overall_avg' => $overallAvg,
            ];
        })->toArray();

        $columnStats = [];
        foreach ($exams as $idx => $exam) {
            $examScores = [];
            foreach ($students as $student) {
                if ($student['scores'][$idx] !== null) {
                    $examScores[] = $student['scores'][$idx];
                }
            }
            $columnStats[] = [
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'lesson_name' => $exam->lesson?->name,
                'max_score' => count($examScores) > 0 ? max($examScores) : null,
                'min_score' => count($examScores) > 0 ? min($examScores) : null,
                'avg_score' => count($examScores) > 0 ? round(array_sum($examScores) / count($examScores), 2) : null,
            ];
        }

        $lessonStats = [];
        foreach ($exams->groupBy('lesson_id') as $lessonId => $lessonExams) {
            $lesson = $lessonExams->first()->lesson;
            $lessonScores = [];
            foreach ($students as $student) {
                $studentLessonScores = [];
                foreach ($lessonExams as $exam) {
                    $idx = $exams->search(fn ($e) => $e->id === $exam->id);
                    if ($idx !== false && $student['scores'][$idx] !== null) {
                        $studentLessonScores[] = $student['scores'][$idx];
                    }
                }
                if (count($studentLessonScores) > 0) {
                    $lessonScores[] = round(array_sum($studentLessonScores) / count($studentLessonScores), 2);
                }
            }
            $lessonStats[] = [
                'lesson_id' => $lessonId,
                'lesson_name' => $lesson?->name,
                'max_score' => count($lessonScores) > 0 ? max($lessonScores) : null,
                'min_score' => count($lessonScores) > 0 ? min($lessonScores) : null,
                'avg_score' => count($lessonScores) > 0 ? round(array_sum($lessonScores) / count($lessonScores), 2) : null,
            ];
        }

        return $this->jsonResponseOk([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'address' => $school->address,
                'phone' => $school->phone,
            ],
            'term' => $term ? ['id' => $term->id, 'name' => $term->name] : null,
            'class' => ['id' => $class->id, 'name' => $class->name],
            'exams' => $exams->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'lesson_id' => $e->lesson_id,
                'lesson_name' => $e->lesson?->name,
                'category' => $e->category?->title ?? 'سایر',
            ]),
            'students' => $students,
            'column_stats' => $columnStats,
            'lesson_stats' => $lessonStats,
        ]);
    }
}
