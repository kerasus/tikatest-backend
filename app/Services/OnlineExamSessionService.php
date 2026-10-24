<?php

namespace App\Services;

use Throwable;
use App\Models\User;
use App\Models\Exam;
use Carbon\CarbonInterface;
use App\Models\SchoolClass;
use App\Models\AcademicLevel;
use App\Models\TermEnrollment;
use App\Models\OnlineExamSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use App\Models\OnlineExamSessionResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OnlineExamSessionService
{
    public function __construct(
        protected OnlineExamScoringService $scoringService
    ) {}

    /**
     * روابط پیش‌فرض مورد نیاز برای نمایش جزئیات کامل یک نشست یا کارنامه
     */
    public function getSessionDetailRelations(): array
    {
        return [
            'exam',
            'exam.category',
            'exam.lesson',
            'exam.onlineExamDetail.booklets',
            'exam.onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true),
            'student',
            'responses',
            'results.booklet',
        ];
    }

    public function loadSessionRelations(OnlineExamSession $session): OnlineExamSession
    {
        $session->loadMissing([
            'responses',
            'exam.onlineExamDetail.booklets',
            'exam.onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true),
        ]);

        return $session;
    }

    /**
     * شروع نشست یا اتصال مجدد به نشست در جریان
     */
    public function startOrCreateSession(Exam $exam, User $user, int $attemptNumber = 1, ?string $ip = null, ?string $userAgent = null): array
    {
        $onlineDetail = $exam->onlineExamDetail;
        $now = now();

        $existingSession = OnlineExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $user->id)
            ->where('attempt_number', $attemptNumber)
            ->lockForUpdate()
            ->first();

        if ($existingSession) {
            if ($existingSession->status === 'in_progress') {
                $remaining = $this->calculateRemainingTime($existingSession, $now);

                return [
                    'session' => $existingSession,
                    'remaining_time' => $remaining,
                ];
            }

            return ['session' => $existingSession, 'remaining_time' => $this->calculateRemainingTime($existingSession, $now)];
        }

        // محاسبه کل مدت مجاز زمان آزمون به ثانیه
        $durationSeconds = $onlineDetail->time_limit_minutes !== null
            ? (int) ($onlineDetail->time_limit_minutes * 60)
            : null;

        if ($onlineDetail->ends_at) {
            $secondsUntilGlobalEnd = max(0, $now->diffInSeconds($onlineDetail->ends_at, false));
            $durationSeconds = $durationSeconds !== null
                ? min($durationSeconds, $secondsUntilGlobalEnd)
                : $secondsUntilGlobalEnd;
        }

        $session = OnlineExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $user->id,
            'status' => 'in_progress',
            'started_at' => $now,
            'duration_limit_seconds' => $durationSeconds,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'attempt_number' => $attemptNumber,
            'is_locked' => false,
        ]);

        return [
            'session' => $session,
            'remaining_time' => $durationSeconds,
        ];
    }

    /**
     * ثبت گزینه انتخابی برای سوال
     */
    public function saveAnswer(OnlineExamSession $session, int $userId, int $questionNumber, ?string $submittedOption, ?string $answerText): void
    {
        $now = now();

        OnlineExamSessionResponse::upsert(
            [
                [
                    'online_exam_session_id' => $session->id,
                    'question_number' => $questionNumber,

                    'exam_id' => $session->exam_id,
                    'user_id' => $userId,

                    'submitted_option' => $submittedOption,
                    'answer_text' => $answerText,
                    'answered_at' => $now,

                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            ],
            // کلید یکتا (مطابق Unique که در migration گذاشتیم)
            ['online_exam_session_id', 'question_number'],
            // ستون‌هایی که در صورت برخورد (conflict) آپدیت می‌شن
            ['submitted_option', 'answer_text', 'answered_at', 'updated_at']
        );
    }

    /**
     * ثبت نهایی آزمون و ارسال به موتور نمره‌دهی
     */
    public function submitAndGradeSession(OnlineExamSession $session, int $userId): OnlineExamSession
    {
        if (in_array($session->status, ['submitted', 'graded'], true)) {
            return $session;
        }

        /** @var OnlineExamSession $lockedSession */
        $lockedSession = OnlineExamSession::whereKey($session->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (in_array($lockedSession->status, ['submitted', 'graded'], true)) {
            return $lockedSession;
        }

        $now = now();
        $timeUsedSeconds = $lockedSession->started_at
            ? (int) $lockedSession->started_at->diffInSeconds($now)
            : (int) ($lockedSession->time_used_seconds ?? 0);

        $durationLimit = $lockedSession->duration_limit_seconds;
        $gracePeriodSeconds = 20;

        $isExpired = $durationLimit && $timeUsedSeconds > ($durationLimit + $gracePeriodSeconds);
        $finalTimeUsed = $durationLimit ? min($timeUsedSeconds, $durationLimit) : $timeUsedSeconds;

        $lockedSession->update([
            'status' => $isExpired ? 'expired' : 'submitted',
            'submitted_at' => $now,
            'time_used_seconds' => max(0, $finalTimeUsed),
            'is_locked' => true,
        ]);

        // لود کامل نیازمندی‌های سرویس نمره‌دهی
        $lockedSession->load([
            'responses',
            'exam.lesson',
            'exam.onlineExamDetail.answerKeys',
            'exam.onlineExamDetail.booklets.lesson',
        ]);

        $scoreData = $this->scoringService->calculateSessionScore($lockedSession);

        $lockedSession->update([
            'percent' => $scoreData['percent'] ?? 0,
            't_score' => $scoreData['obtained_marks'] ?? 0,
            'status' => 'graded',
        ]);

        $lockedSession->load($this->getSessionDetailRelations());

        return $lockedSession;
    }

    /**
     * محاسبه زمان باقی‌مانده (حداقل بین سقف زمان داوطلب و ساعت پایان کل آزمون)
     */
    public function calculateRemainingTime(OnlineExamSession $session, ?CarbonInterface $now = null): ?int
    {
        if ($session->status !== 'in_progress') {
            return in_array($session->status, ['submitted', 'graded', 'expired'], true) ? 0 : null;
        }

        $now ??= now();

        $elapsed = $session->started_at
            ? max(0, $session->started_at->diffInSeconds($now, false))
            : (int) ($session->time_used_seconds ?? 0);

        $sessionRemaining = $session->duration_limit_seconds !== null
            ? max(0, (int) $session->duration_limit_seconds - $elapsed)
            : null;

        $globalRemaining = null;
        $endsAt = $session->exam?->onlineExamDetail?->ends_at;

        if ($endsAt) {
            $globalRemaining = max(0, $now->diffInSeconds($endsAt, false));
        }

        return match (true) {
            $sessionRemaining !== null && $globalRemaining !== null => min($sessionRemaining, $globalRemaining),
            $sessionRemaining !== null => $sessionRemaining,
            default => $globalRemaining,
        };
    }

    /**
     * پکیج کردن خروجی استاندارد نتیجه آزمون (ساختار یکدست برای سشن‌دار و بی‌سشن)
     */
    public function buildExamResultPayload(Exam $exam, ?OnlineExamSession $session, int $studentId): array
    {
        // آماده‌سازی روابط آزمون و کلیدها
        $exam->loadMissing([
            'category',
            'lesson',
            'onlineExamDetail.booklets',
            'onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true),
        ]);

        if ($session) {
            $this->loadSessionRelations($session);
        }

        return [
            'exam' => $exam,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'attempt_number' => $session->attempt_number,
                'started_at' => $session->started_at,
                'submitted_at' => $session->submitted_at,
                'time_used_seconds' => $session->time_used_seconds,
                'percent' => $session->percent !== null ? (float) $session->percent : null,
                't_score' => $session->t_score,
                'is_locked' => $session->is_locked,
                'responses' => $session->responses,
            ] : null,
            'remaining_time' => $session ? $this->calculateRemainingTime($session) : null,
            'answer_keys' => $this->formatAnswerKeysForExam($exam, $session),
            'rankings' => $session ? $this->getSessionRankings($session) : [
                'metric' => 'percent',
                'attempt_policy' => 'latest_graded',
                'classes' => [],
                'levels' => [],
            ],
            'is_review_only' => $session === null,
        ];
    }

    /**
     * فرمت یکپارچه کلیدها برای آزمون (چه سشن باشد چه نباشد)
     */
    public function formatAnswerKeysForExam(Exam $exam, ?OnlineExamSession $session): ?array
    {
        $answerKeys = $exam->onlineExamDetail?->answerKeys;
        if (! $answerKeys) {
            return null;
        }

        $responsesByQuestion = $session ? $session->responses->keyBy('question_number') : collect();

        return $answerKeys->map(function ($key) use ($responsesByQuestion) {
            $response = $responsesByQuestion->get($key->question_number);

            $payload = [
                'id' => $key->id,
                'exam_id' => $key->exam_id,
                'question_number' => $key->question_number,
                'number_of_choices' => $key->number_of_choices,
                'submitted_option' => $response?->submitted_option,
                'weight' => $key->weight,
                'has_negative_mark' => $key->has_negative_mark,
                'is_active' => $key->is_active,
            ];

            $payload['correct_option'] = $key->correct_option;
            $payload['is_correct'] = $response?->is_correct;
            $payload['marks_obtained'] = $response?->marks_obtained;

            return $payload;
        })->values()->all();
    }

    /**
     * رتبه دانش‌آموز در کلاس‌ها و پایه‌ها برای همین آزمون.
     *
     * مبنا: آخرین تلاش تصحیح‌شده هر دانش‌آموز.
     * رتبه مساوی: 1, 1, 3
     *
     * تعداد شرکت‌کنندگان، فقط شامل دانش‌آموزان دارای نمره است.
     */
    public function getSessionRankings(OnlineExamSession $session): array
    {
        $metric = 'percent';
        $attemptPolicy = 'latest_graded';

        // 1) گرفتن آزمون و دانش‌آموز از روی سشن
        $exam = $session->exam;
        $studentId = (int) ($session->student_id ?? $session->user_id);

        if (! $exam || ! $studentId) {
            return [
                'metric'         => $metric,
                'attempt_policy' => $attemptPolicy,
                'classes'        => [],
                'levels'         => [],
            ];
        }

        // 2) دریافت ساختار دسته‌بندی‌شده آزمون
        $groupedData = $this->getExamSessionsGroupedByClass($exam);
        $levels = $groupedData['levels'] ?? [];

        $classesRankings = [];
        $levelsRankings = [];

        foreach ($levels as $levelData) {
            $levelId = $levelData['academic_level_id'];
            $levelName = $levelData['academic_level_name'];
            $classes = $levelData['classes'] ?? [];

            $studentEnrolledInThisLevel = false;
            $levelParticipants = [];

            foreach ($classes as $classData) {
                $classId = $classData['class_id'];
                $className = $classData['class_name'];
                $studentsInClass = $classData['students'] ?? [];

                // آیا این دانش‌آموز در این کلاس ثبت‌نام کرده؟
                $isStudentInClass = collect($studentsInClass)
                    ->contains(fn ($item) => (int)($item['student']['id'] ?? 0) === $studentId);

                // استخراج تمام شرکت‌کنندگانی که درصد معتبر دارند
                $classParticipants = collect($studentsInClass)
                    ->filter(fn ($item) => !empty($item['has_participated']) && isset($item['session']['percent']) && $item['session']['percent'] !== null)
                    ->map(fn ($item) => [
                        'student_id' => (int)$item['student']['id'],
                        'percent'    => (float)$item['session']['percent'],
                    ])
                    ->values();

                // ثبت شرکت‌کنندگان در سطح پایه
                foreach ($classParticipants as $p) {
                    $levelParticipants[$p['student_id']] = $p;
                }

                // اگر دانش‌آموز عضو این کلاس بود، رتبه‌اش محاسبه می‌شود
                if ($isStudentInClass) {
                    $studentEnrolledInThisLevel = true;

                    $sortedClassParticipants = $classParticipants->sortByDesc('percent')->values();
                    $totalClassParticipants = $sortedClassParticipants->count();

                    $studentRankInClass = null;
                    $rankIndex = $sortedClassParticipants->search(fn ($p) => $p['student_id'] === $studentId);
                    if ($rankIndex !== false) {
                        $studentRankInClass = $rankIndex + 1;
                    }

                    $classesRankings[] = [
                        'class_id'           => $classId,
                        'class_name'         => $className,
                        'academic_level_id'  => $levelId,
                        'academic_level_name'=> $levelName,
                        'rank'               => $studentRankInClass,
                        'total_participants' => $totalClassParticipants,
                    ];
                }
            }

            // اگر در این پایه ثبت‌نام داشت، رتبه پایه
            if ($studentEnrolledInThisLevel) {
                $sortedLevelParticipants = collect(array_values($levelParticipants))->sortByDesc('percent')->values();
                $totalLevelParticipants = $sortedLevelParticipants->count();

                $studentRankInLevel = null;
                $levelRankIndex = $sortedLevelParticipants->search(fn ($p) => $p['student_id'] === $studentId);
                if ($levelRankIndex !== false) {
                    $studentRankInLevel = $levelRankIndex + 1;
                }

                $levelsRankings[] = [
                    'academic_level_id'   => $levelId,
                    'academic_level_name' => $levelName,
                    'rank'                => $studentRankInLevel,
                    'total_participants'  => $totalLevelParticipants,
                ];
            }
        }

        return [
            'metric'         => $metric,
            'attempt_policy' => $attemptPolicy,
            'classes'        => $classesRankings,
            'levels'         => $levelsRankings,
        ];
    }

    /**
     * آخرین نشست دانش‌آموز برای یک آزمون
     */
    public function getLatestStudentSession(
        Exam $exam,
        User $student
    ): ?OnlineExamSession {
        return OnlineExamSession::query()
            ->where('exam_id', $exam->getKey())
            ->where('student_id', $student->getKey())
            ->orderByDesc('attempt_number')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * دریافت آمار عملکرد تفکیکی دانش‌آموزان به ازای تک‌تک سوالات و گزینه‌ها
     * جهت رسم در نمودارها و تحلیل آیتم‌های آزمون (Item Analysis)
     */
    public function getExamQuestionsAnalytics(Exam $exam): array
    {
        // اطمینان از لود بودن جزئیات آزمون و کلیدهای سوالات
        $exam->loadMissing([
            'onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true)->orderBy('question_number'),
        ]);

        $onlineDetail = $exam->onlineExamDetail;
        $answerKeys = $onlineDetail->answerKeys ?? collect();

        // 1. لیست شناسه سشن‌های تکمیل‌شده/تصحیح‌شده یا منقضی شده
        $submittedSessions = OnlineExamSession::query()
            ->where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'graded', 'expired'])
            ->get(['id', 'student_id']);

        $totalParticipants = $submittedSessions->count();
        $sessionIds = $submittedSessions->pluck('id')->all();

        // اگر هیچ دانش‌آموزی در آزمون شرکت نکرده باشد
        if ($totalParticipants === 0 || empty($sessionIds)) {
            return [
                'total_participants' => 0,
                'total_questions' => $answerKeys->count(),
                'questions' => $answerKeys->map(function ($key) {
                    $choicesCount = (int) ($key->number_of_choices ?: 4);
                    $optionsBreakdown = [];
                    for ($c = 1; $c <= $choicesCount; $c++) {
                        $optionsBreakdown[(string) $c] = 0;
                    }

                    return [
                        'question_number' => $key->question_number,
                        'correct_option' => (string) $key->correct_option,
                        'number_of_choices' => $choicesCount,
                        'total_participants' => 0,
                        'correct_count' => 0,
                        'wrong_count' => 0,
                        'unanswered_count' => 0,
                        'correct_percentage' => 0,
                        'options_breakdown' => $optionsBreakdown,
                    ];
                })->values()->all(),
            ];
        }

        // 2. تجمیع سریع پاسخ‌های ثبت‌شده از دیتابیس به تفکیک سوال و گزینه
        $responsesGrouped = OnlineExamSessionResponse::query()
            ->whereIn('online_exam_session_id', $sessionIds)
            ->whereNotNull('submitted_option')
            ->where('submitted_option', '!=', '')
            ->select([
                'question_number',
                'submitted_option',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('question_number', 'submitted_option')
            ->get()
            ->groupBy('question_number');

        // 3. محاسبه و ساختاردهی آمار هر سوال
        $questionsStats = $answerKeys->map(function ($key) use ($responsesGrouped, $totalParticipants) {
            $qNum = (int) $key->question_number;
            $correctOption = (string) $key->correct_option;
            $choicesCount = (int) ($key->number_of_choices ?: 4);

            // گزینه‌ها را به صورت پیش‌فرض صفر می‌کنیم (مثلا ۱ تا ۴)
            $optionsBreakdown = [];
            for ($c = 1; $c <= $choicesCount; $c++) {
                $optionsBreakdown[(string) $c] = 0;
            }

            $questionResponses = $responsesGrouped->get($qNum, collect());

            $totalAnswered = 0;
            $correctCount = 0;

            foreach ($questionResponses as $resp) {
                $option = (string) $resp->submitted_option;
                $count = (int) $resp->count;

                // در صورت وجود گزینه‌ای خارج از رِنج یا استاندارد، ست می‌کنیم
                $optionsBreakdown[$option] = $count;
                $totalAnswered += $count;

                if ($option === $correctOption) {
                    $correctCount += $count;
                }
            }

            $wrongCount = max(0, $totalAnswered - $correctCount);
            $unansweredCount = max(0, $totalParticipants - $totalAnswered);
            $correctPercentage = $totalParticipants > 0
                ? round(($correctCount / $totalParticipants) * 100, 1)
                : 0;

            return [
                'question_number' => $qNum,
                'correct_option' => $correctOption,
                'number_of_choices' => $choicesCount,
                'total_participants' => $totalParticipants,
                'correct_count' => $correctCount,
                'wrong_count' => $wrongCount,
                'unanswered_count' => $unansweredCount,
                'correct_percentage' => $correctPercentage,
                'options_breakdown' => $optionsBreakdown, // مثال: {"1": 12, "2": 5, "3": 34, "4": 1}
            ];
        })->values()->all();

        return [
            'total_participants' => $totalParticipants,
            'total_questions' => $answerKeys->count(),
            'questions' => $questionsStats,
        ];
    }

    /**
     * ترمیم سشن‌های منقضی‌شده یا ناتمام یک دانش‌آموز.
     *
     * سشن‌های زیر بررسی می‌شوند:
     * - in_progress: فقط در صورتی که زمانشان تمام شده باشد.
     * - expired: در صورتی که هنوز نمره‌دهی نشده باشند.
     */
    public function repairExpiredSessionsForStudent(int $studentId): array
    {
        $query = OnlineExamSession::query()
            ->where('student_id', $studentId)
            ->whereIn('status', ['in_progress', 'expired']);

        // اگر هیچ سشن بازی نداره، سریع برگرد و دیتابیس رو درگیر نکن
        if (! $query->exists()) {
            return [
                'checked' => 0,
                'repaired' => 0,
                'skipped' => 0,
                'failed' => 0,
                'repaired_session_ids' => [],
                'failed_session_ids' => [],
            ];
        }

        return $this->repairExpiredSessions($query);
    }

    /**
     * ترمیم سشن‌های منقضی‌شده یا ناتمام یک آزمون.
     */
    public function repairExpiredSessionsForExam(int $examId): array
    {
        $query = OnlineExamSession::query()
            ->where('exam_id', $examId)
            ->whereIn('status', ['in_progress', 'expired']);

        // خروج سریع: اگر هیچ سشن بازی برای این آزمون وجود ندارد
        if (! $query->exists()) {
            return [
                'checked' => 0,
                'repaired' => 0,
                'skipped' => 0,
                'failed' => 0,
                'repaired_session_ids' => [],
                'failed_session_ids' => [],
            ];
        }

        return $this->repairExpiredSessions($query);
    }

    /**
     * هستهٔ مشترک ترمیم سشن‌ها.
     */
    private function repairExpiredSessions(Builder $query): array
    {
        $result = [
            'checked' => 0,
            'repaired' => 0,
            'skipped' => 0,
            'failed' => 0,
            'repaired_session_ids' => [],
            'failed_session_ids' => [],
        ];

        $query
            ->with(['exam.onlineExamDetail'])
            ->chunkById(100, function ($sessions) use (&$result) {
                foreach ($sessions as $session) {
                    $result['checked']++;

                    try {
                        $wasRepaired = DB::transaction(function () use ($session) {
                            /** @var OnlineExamSession|null $lockedSession */
                            $lockedSession = OnlineExamSession::query()
                                ->whereKey($session->id)
                                ->lockForUpdate()
                                ->first();

                            if (! $lockedSession) {
                                return false;
                            }

                            // اگر هم‌زمان درخواست دیگری آن را پردازش کرده باشد
                            if (! in_array($lockedSession->status, ['in_progress', 'expired'], true)) {
                                return false;
                            }

                            $lockedSession->loadMissing('exam.onlineExamDetail');

                            /*
                             * برای in_progress باید زمان واقعاً تمام شده باشد.
                             * اما expired قبلاً منقضی شده و مستقیماً قابل نمره‌دهی است.
                             */
                            if ($lockedSession->status === 'in_progress') {
                                $remainingTime = $this->calculateRemainingTime(
                                    $lockedSession,
                                    now()
                                );

                                // نبودن محدودیت زمانی به‌تنهایی دلیل ترمیم نیست.
                                if ($remainingTime === null || $remainingTime > 0) {
                                    return false;
                                }
                            }

                            $this->submitAndGradeSession(
                                $lockedSession,
                                (int) $lockedSession->student_id
                            );

                            return true;
                        });

                        if ($wasRepaired) {
                            $result['repaired']++;
                            $result['repaired_session_ids'][] = $session->id;
                        } else {
                            $result['skipped']++;
                        }
                    } catch (Throwable $exception) {
                        $result['failed']++;
                        $result['failed_session_ids'][] = $session->id;

                        Log::error(
                            'Failed to repair expired online exam session.',
                            [
                                'session_id' => $session->id,
                                'student_id' => $session->student_id,
                                'exam_id' => $session->exam_id,
                                'status' => $session->status,
                                'exception' => $exception->getMessage(),
                            ]
                        );
                    }
                }
            });

        return $result;
    }

    /**
     * دریافت لیست دانش‌آموزان واجد شرایط آزمون به تفکیک کلاس
     * به همراه وضعیت شرکت در آزمون (OnlineExamSession) برای هر دانش‌آموز
     */
    public function getExamSessionsGroupedByClass(Exam $exam): array
    {
        // 1) کلاس‌های صریح آزمون (exam_classes)
        $explicitClasses = SchoolClass::query()
            ->whereIn('id', DB::table('exam_classes')->where('exam_id', $exam->id)->pluck('class_id'))
            ->with('academicLevel')
            ->get();

        $explicitClassIds = $explicitClasses->pluck('id')->all();
        $explicitByLevelId = $explicitClasses->groupBy('academic_level_id'); // [level_id => Collection<SchoolClass>]

        // 2) پایه‌های آزمون (academic levels)
        $levelIds = $exam->academicLevels()
            ->pluck('academic_levels.id')
            ->all();

        $levelsWithClasses = collect();
        if (!empty($levelIds)) {
            $levelsWithClasses = \App\Models\AcademicLevel::query()
                ->whereIn('id', $levelIds)
                ->with(['classes.academicLevel'])
                ->get();
        }

        // 3) ساخت لیست نهایی کلاس‌های مشمول آزمون طبق قاعده ترکیبی
        $eligibleClasses = collect();

        foreach ($levelsWithClasses as $level) {
            $levelId = $level->id;

            if ($explicitByLevelId->has($levelId) && $explicitByLevelId->get($levelId)->isNotEmpty()) {
                $eligibleClasses = $eligibleClasses->merge($explicitByLevelId->get($levelId));
            } else {
                $eligibleClasses = $eligibleClasses->merge($level->classes);
            }
        }

        if (empty($levelIds) && !empty($explicitClassIds)) {
            $eligibleClasses = $explicitClasses;
        }

        $eligibleClasses = $eligibleClasses->unique('id')->values();

        // 4) سشن‌ها: آخرین attempt هر دانش‌آموز
        $sessionsByStudent = OnlineExamSession::query()
            ->where('exam_id', $exam->id)
            ->orderByDesc('attempt_number')
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        // 5) ساخت خروجی: پایه -> کلاس -> دانش‌آموزان
        $classesGroupedByLevel = $eligibleClasses->groupBy('academic_level_id');

        $levelsPayload = [];

        foreach ($classesGroupedByLevel as $levelId => $classesInLevel) {
            $levelName = $classesInLevel->first()?->academicLevel?->name ?? 'نامشخص';

            $classesPayload = [];

            foreach ($classesInLevel as $schoolClass) {
                $enrollmentsQuery = TermEnrollment::query()
                    ->where('class_id', $schoolClass->id)
                    ->active()
                    ->with('user');

                if ($exam->term_id) {
                    $enrollmentsQuery->where('term_id', $exam->term_id);
                }

                $enrollments = $enrollmentsQuery->get();

                $studentsPayload = [];
                foreach ($enrollments as $enrollment) {
                    $student = $enrollment->user;
                    if (! $student) continue;

                    $session = $sessionsByStudent->get($student->id);

                    $studentsPayload[] = [
                        'student' => [
                            'id' => $student->id,
                            'name' => $student->name ?? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                            'national_code' => $student->national_code ?? null,
                            'mobile' => $student->mobile ?? null,
                        ],
                        'enrollment_id' => $enrollment->id,
                        'has_participated' => $session !== null,
                        'session' => $session ? [
                            'id' => $session->id,
                            'status' => $session->status,
                            'attempt_number' => $session->attempt_number,
                            'started_at' => $session->started_at,
                            'submitted_at' => $session->submitted_at,
                            'time_used_seconds' => $session->time_used_seconds,
                            'percent' => $session->percent !== null ? (float) $session->percent : null,
                            't_score' => $session->t_score,
                            'is_locked' => $session->is_locked,
                        ] : null,
                    ];
                }

                // ⭐ 6) مرتب‌سازی نزولی دانش‌آموزان بر اساس درصد (دانش‌آموزان بدون درصد در انتهای لیست قرار می‌گیرند)
                $sortedStudents = collect($studentsPayload)->sort(function ($a, $b) {
                    $pA = $a['session']['percent'] ?? null;
                    $pB = $b['session']['percent'] ?? null;

                    // هر دو درصد دارند => مقایسه عددی نزولی
                    if ($pA !== null && $pB !== null) {
                        if ($pA == $pB) {
                            // در صورت برابری، بر اساس کمترین زمان مصرف‌شده (یا نام دانش‌آموز)
                            return ($a['session']['time_used_seconds'] ?? 0) <=> ($b['session']['time_used_seconds'] ?? 0);
                        }
                        return $pB <=> $pA; // نزولی (بزرگتر بالاتر)
                    }

                    // اگر A درصد دارد و B ندارد => A بالاتر است (-1)
                    if ($pA !== null && $pB === null) {
                        return -1;
                    }

                    // اگر B درصد دارد و A ندارد => B بالاتر است (1)
                    if ($pA === null && $pB !== null) {
                        return 1;
                    }

                    // اگر هیچ‌کدام درصد ندارند => مرتب‌سازی بر اساس نام
                    return strcmp($a['student']['name'] ?? '', $b['student']['name'] ?? '');
                })->values()->all();

                $classesPayload[] = [
                    'class_id' => $schoolClass->id,
                    'class_name' => $schoolClass->name,
                    'total_students' => count($sortedStudents),
                    'participated_count' => collect($sortedStudents)->where('has_participated', true)->count(),
                    'students' => $sortedStudents,
                ];
            }

            $levelsPayload[] = [
                'academic_level_id' => (int) $levelId,
                'academic_level_name' => $levelName,
                'classes' => $classesPayload,
            ];
        }

        return [
            'exam_id' => $exam->id,
            'exam_title' => $exam->name ?? null,
            'levels' => $levelsPayload,
        ];
    }

    /**
     * محاسبه مجدد نتایج ثبت‌شده‌ی یک آزمون (بعد از تغییر کلید/وزن/نمره منفی و ...)
     *
     * نکته: فقط سشن‌های پایان‌یافته را بازنمره‌دهی می‌کند (submitted/graded/expired).
     * پیش‌فرض: فقط آخرین attempt هر دانش‌آموز را محاسبه می‌کند.
     */
    public function recalculateExamResults(
        int $examId,
        ?int $termId = null,
        bool $latestAttemptOnly = true
    ): array {
        $exam = Exam::query()
            ->whereKey($examId)
            ->where('delivery_mode', 'online')
            ->with([
                'lesson',
                'onlineExamDetail.answerKeys',
                'onlineExamDetail.booklets.lesson',
            ])
            ->first();

        if (! $exam) {
            throw new ModelNotFoundException('آزمون آنلاین مورد نظر یافت نشد.');
        }

        $result = [
            'exam_id' => $examId,
            'checked' => 0,
            'rescored' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rescored_session_ids' => [],
            'failed_session_ids' => [],
            'latest_attempt_only' => $latestAttemptOnly,
        ];

        // 1) پایه‌ی کوئری: فقط سشن‌های پایان‌یافته
        $baseQuery = OnlineExamSession::query()
            ->where('exam_id', $examId)
            ->whereIn('status', ['submitted', 'graded', 'expired']);

//        if ($termId) {
            // اگر term_id روی خود exam هست، معمولاً لازم نیست.
            // ولی اگر سشن‌ها به ترم خاصی باید محدود شوند، اینجا جای فیلتر است.
            // (فعلاً چیزی روی OnlineExamSession نداریم، فقط گذاشتم برای توسعه‌ی بعدی)
//        }

        // 2) اگر فقط آخرین تلاش هر دانش‌آموز مدنظر است:
        //    یک زیرکوئری می‌گیریم که session_id آخرین attempt را برگرداند.
        if ($latestAttemptOnly) {
            // انتخاب آخرین attempt_number و در صورت تساوی آخرین id
            // روش ساده: همه را مرتب کنیم و unique کنیم (ممکن است سنگین شود)
            // روش بهتر: subquery.
            $latestIds = OnlineExamSession::query()
                ->select(DB::raw('MAX(id) as id'))
                ->where('exam_id', $examId)
                ->whereIn('status', ['submitted', 'graded', 'expired'])
                ->groupBy('student_id')
                ->pluck('id')
                ->all();

            $baseQuery->whereIn('id', $latestIds);
        }

        // 3) پردازش chunk
        $baseQuery
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$result, $exam) {
                foreach ($sessions as $session) {
                    $result['checked']++;

                    try {
                        DB::transaction(function () use ($session, $exam, &$result) {
                            /** @var OnlineExamSession $lockedSession */
                            $lockedSession = OnlineExamSession::query()
                                ->whereKey($session->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            // اگر در این فاصله وضعیتش تغییر کرد (مثلاً رفت in_progress)، دست نزن
                            if (! in_array($lockedSession->status, ['submitted', 'graded', 'expired'], true)) {
                                $result['skipped']++;
                                return;
                            }

                            // لود وابستگی‌های لازم برای scoring
                            $lockedSession->load([
                                'responses',
                                'exam.lesson',
                                'exam.onlineExamDetail.answerKeys',
                                'exam.onlineExamDetail.booklets.lesson',
                            ]);

                            // اگر پاسخ‌ها نیست یا answerKey نداریم، منطقیه skip کنیم
                            if (! $lockedSession->exam?->onlineExamDetail?->answerKeys) {
                                $result['skipped']++;
                                return;
                            }

                            $scoreData = $this->scoringService->calculateSessionScore($lockedSession);

                            $lockedSession->update([
                                'percent' => $scoreData['percent'] ?? 0,
                                't_score' => $scoreData['obtained_marks'] ?? 0,
                                // وضعیت را graded نگه می‌داریم چون نتیجه محاسبه شده
                                'status' => 'graded',
                            ]);

                            $result['rescored']++;
                            $result['rescored_session_ids'][] = $lockedSession->id;
                        }, 3);
                    } catch (Throwable $e) {
                        $result['failed']++;
                        $result['failed_session_ids'][] = $session->id;

                        Log::error('Failed to recalculate exam session score.', [
                            'exam_id' => $exam->id,
                            'session_id' => $session->id,
                            'student_id' => $session->student_id,
                            'status' => $session->status,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $result;
    }
}
