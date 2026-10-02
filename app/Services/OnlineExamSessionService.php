<?php

namespace App\Services;

use App\Models\User;
use App\Models\Exam;
use App\Enums\UserRoleType;
use Carbon\CarbonInterface;
use App\Models\OnlineExamSession;
use Illuminate\Support\Facades\DB;
use App\Models\OnlineExamSessionResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    /**
     * بررسی صلاحیت و دسترسی دانش‌آموز به آزمون بر اساس ثبت‌نام (Enrollment)
     */
    public function validateStudentAccess(Exam $exam, User $user): void
    {
        // ادمین‌ها و کاربران با دسترسی مدیریت همیشه مجازند
        if ($user->hasRole(UserRoleType::Admin->value) || $user->hasPermissionTo('exams.view')) {
            return;
        }

        // اگر آزمون به کلاسی تخصیص نیافته، دسترسی عمومی تلقی می‌شود
        $examClassIds = DB::table('exam_classes')
            ->where('exam_id', $exam->id)
            ->pluck('class_id')
            ->all();

        if (empty($examClassIds)) {
            return;
        }

        // بررسی عضویت در کلاس‌های مربوطه
        $hasActiveEnrollment = DB::table('term_enrollments')
            ->where('user_id', $user->id)
            ->whereIn('class_id', $examClassIds)
            ->exists();

        if (! $hasActiveEnrollment) {
            throw new HttpException(403, 'شما دسترسی لازم برای شرکت در این آزمون را ندارید.');
        }
    }

    /**
     * بررسی بازه زمانی رسمی و آغاز/پایان آزمون
     */
    public function validateExamTiming(Exam $exam): void
    {
        $onlineDetail = $exam->onlineExamDetail;

        if (! $onlineDetail) {
            throw new HttpException(404, 'تنظیمات آزمون آنلاین برای این آزمون یافت نشد.');
        }

        $now = now();

        if ($onlineDetail->visible_at && $now->lt($onlineDetail->visible_at)) {
            throw new HttpException(403, 'آزمون هنوز برای شما در دسترس و قابل مشاهده نیست.');
        }

        if ($onlineDetail->starts_at && $now->lt($onlineDetail->starts_at)) {
            throw new HttpException(409, 'زمان برگزاری آزمون هنوز فرا نرسیده است.');
        }

        if ($onlineDetail->ends_at && $now->gt($onlineDetail->ends_at)) {
            throw new HttpException(409, 'مهلت برگزاری این آزمون به پایان رسیده است.');
        }
    }

    /**
     * ماسک کردن امنیتی کلیدهای پاسخ و فایل/متن پاسخ‌نامه تشریحی روی شیء Session
     */
    public function prepareSessionRelations(OnlineExamSession $session, bool $includeSolutions = false): OnlineExamSession
    {
        // اطمینان از لود بودن تمام وابستگی‌ها از جمله answerKeys
        $session->loadMissing([
            'responses',
            'exam.onlineExamDetail.booklets',
            'exam.onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true),
        ]);

        $onlineDetail = $session->exam?->onlineExamDetail;

        // ۱. مخفی‌سازی اطلاعات کل پاسخنامه تشریحی (solution) از onlineExamDetail در زمان آزمون
        if ($onlineDetail && ! $includeSolutions) {
            $onlineDetail->makeHidden([
                'solution',
                'solution_file',
                'solution_path',
                'solution_descriptive',
            ]);
        }

        // ۲. مخفی‌سازی correct_option از تک تک سوالات
        $answerKeys = $onlineDetail?->answerKeys;

        if ($answerKeys) {
            if (! $includeSolutions) {
                // حذف گزینه صحیح از دید دانش‌آموز در حین آزمون
                $answerKeys->makeHidden(['correct_option']);
            } else {
                // اگر آزمون تمام شده باشد، جزئیات پاسخ داوطلب هم برای نمایش کارنامه الصاق می‌شود
                $responsesByQuestion = $session->responses->keyBy('question_number');
                $answerKeys->each(function ($key) use ($responsesByQuestion) {
                    $response = $responsesByQuestion->get($key->question_number);
                    $key->submitted_option = $response?->submitted_option;
                    $key->is_correct = $response?->is_correct;
                    $key->marks_obtained = $response?->marks_obtained;
                });
            }
        }

        return $session;
    }

    /**
     * شروع نشست یا اتصال مجدد به نشست در جریان
     */
    public function startOrCreateSession(Exam $exam, User $user, int $attemptNumber = 1, ?string $ip = null, ?string $userAgent = null): array
    {
        $this->validateStudentAccess($exam, $user);
        $this->validateExamTiming($exam);

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

                if ($remaining !== null && $remaining <= 0) {
                    $existingSession->update(['status' => 'expired', 'is_locked' => true]);
                    throw new HttpException(410, 'مهلت زمانی نشست آزمون شما منقضی شده است.');
                }

                $this->prepareSessionRelations($existingSession, includeSolutions: false);

                return [
                    'session' => $existingSession,
                    'remaining_time' => $remaining,
                ];
            }

            if (in_array($existingSession->status, ['submitted', 'graded'], true)) {
                throw new HttpException(409, 'این آزمون قبلاً توسط شما ارسال و ثبت شده است.');
            }

            if ($existingSession->status === 'expired') {
                throw new HttpException(410, 'مهلت جلسه آزمون به پایان رسیده است.');
            }
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

        if ($durationSeconds !== null && $durationSeconds <= 0) {
            throw new HttpException(409, 'مهلت شرکت در آزمون خاتمه یافته است.');
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

        $this->prepareSessionRelations($session, includeSolutions: false);

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
        if ($session->student_id !== $userId) {
            throw new HttpException(403, 'دسترسی غیرمجاز به این نشست.');
        }

        if ($session->status !== 'in_progress') {
            throw new HttpException(409, 'این آزمون در حال برگزاری نیست.');
        }

        $remainingTime = $this->calculateRemainingTime($session);

        // ۱۵ ثانیه فرجه شبکه (Latency buffer)
        if ($remainingTime !== null && $remainingTime < -15) {
            $session->update(['status' => 'expired', 'is_locked' => true]);
            throw new HttpException(409, 'مهلت زمانی آزمون به پایان رسیده است.');
        }

        $session->loadMissing('exam.onlineExamDetail');

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
        if ($session->student_id !== $userId) {
            throw new HttpException(403, 'دسترسی غیرمجاز.');
        }

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
     * پکیج کردن خروجی استاندارد نشست به همراه ماسک کردن امنیتی کلیدها
     */
    public function buildSessionPayload(OnlineExamSession $session): array
    {
        $endsAt = $session->exam?->onlineExamDetail?->ends_at;
        $isExamEnded = $endsAt && now()->greaterThan($endsAt);

        // دانش‌آموز زمانی پاسخ‌های تشریحی و کلیدها را می‌بیند که:
        // ۱. آزمون را ثبت کرده یا تصحیح شده باشد
        // ۲. یا سشن منقضی شده باشد
        // ۳. یا مهلت کلی برگزاری آزمون به پایان رسیده باشد
        $includeSolutions = in_array($session->status, ['submitted', 'graded', 'expired'], true) || $isExamEnded;

        $this->prepareSessionRelations($session, includeSolutions: $includeSolutions);

        return [
            'session' => $session,
            'remaining_time' => $this->calculateRemainingTime($session),
            'answer_keys' => $this->formatAnswerKeys($session, includeSolutions: $includeSolutions),
        ];
    }

    /**
     * فرمت یکپارچه کلیدها بر اساس وضعیت آزمون (در حال آزمون یا بعد از تصحیح)
     */
    public function formatAnswerKeys(OnlineExamSession $session, bool $includeSolutions = false): ?array
    {
        $answerKeys = $session->exam?->onlineExamDetail?->answerKeys;
        if (! $answerKeys) {
            return null;
        }

        $responsesByQuestion = $session->responses->keyBy('question_number');

        return $answerKeys->map(function ($key) use ($responsesByQuestion, $includeSolutions) {
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

            if ($includeSolutions) {
                $payload['correct_option'] = $key->correct_option;
                $payload['is_correct'] = $response?->is_correct;
                $payload['marks_obtained'] = $response?->marks_obtained;
            }

            return $payload;
        })->values()->all();
    }

    /**
     * کلیدهای خام بدون افشای گزینه صحیح برای آزمون در حال اجرا
     */
    public function sanitizeAnswerKeys($answerKeys): ?array
    {
        if (! $answerKeys) {
            return null;
        }

        return $answerKeys->map(fn ($key) => [
            'id' => $key->id,
            'exam_id' => $key->exam_id,
            'question_number' => $key->question_number,
            'number_of_choices' => $key->number_of_choices,
            'weight' => $key->weight,
            'has_negative_mark' => $key->has_negative_mark,
            'is_active' => $key->is_active,
        ])->values()->all();
    }

    public function getExamResultPayload(int $examId, int $studentId, ?int $attemptNumber = null): array
    {
        $exam = Exam::query()
            ->where('id', $examId)
            ->where('delivery_mode', 'online')
            ->with(['onlineExamDetail'])
            ->first();

        if (! $exam) {
            throw new ModelNotFoundException('آزمون آنلاین مورد نظر یافت نشد.');
        }

        $query = OnlineExamSession::query()
            ->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->whereIn('status', ['submitted', 'graded', 'expired'])
            ->with($this->getSessionDetailRelations());

        if ($attemptNumber) {
            $query->where('attempt_number', $attemptNumber);
        } else {
            $query->orderByDesc('attempt_number');
        }

        $session = $query->first();

        // اگر سشن فعال یا ثبت‌شده‌ای نبود
        if (! $session) {
            $endsAt = $exam->onlineExamDetail?->ends_at;
            $isExamEnded = $endsAt && now()->greaterThan($endsAt);

            // اگر مهلت آزمون تمام شده، اجازه بده دفترچه سوالات و پاسخنامه را خالی (بدون پاسخ‌های دانش‌آموز) ببیند
            if ($isExamEnded) {
                return $this->buildEmptyExamReviewPayload($exam, $studentId);
            }

            throw new ModelNotFoundException('کارنامه یا نتیجه پایان‌یافته‌ای برای این دانش‌آموز در این آزمون یافت نشد.');
        }

        return $this->buildSessionPayload($session);
    }

    /**
     * تولید پی‌لود پیش‌فرض برای حالتی که دانش‌آموز در آزمون شرکت نکرده اما مهلت آزمون تمام شده است.
     */
    protected function buildEmptyExamReviewPayload(Exam $exam, int $studentId): array
    {
        // لود کردن سوالات و کلیدهای پاسخ
        $exam->loadMissing([
            'onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true),
            'lesson',
            'grade',
        ]);

        return [
            'id' => null,
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'attempt_number' => 1,
            'status' => 'not_participated',
            'started_at' => null,
            'finished_at' => null,
            'score' => 0,
            'total_score' => $exam->onlineExamDetail?->total_score ?? 0,
            'percentage' => 0,
            'total_questions' => $exam->onlineExamDetail?->total_questions ?? 0,
            'correct_answers_count' => 0,
            'wrong_answers_count' => 0,
            'unanswered_count' => $exam->onlineExamDetail?->total_questions ?? 0,
            'responses' => [],
            'exam' => $exam,
            'is_review_only' => true,
        ];
    }

    public function getExamResultPayloadBySessionId(int $sessionId): array
    {
        $session = OnlineExamSession::query()
            ->where('id', $sessionId)
            ->whereIn('status', ['submitted', 'graded'])
            ->with($this->getSessionDetailRelations())
            ->first();

        if (! $session) {
            throw new ModelNotFoundException('نشست آزمون یافت نشد یا هنوز به پایان نرسیده است.');
        }

        return $this->buildSessionPayload($session);
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
     * آیا دانش‌آموز پاسخ آزمون را ارسال کرده است؟
     */
    public function hasSubmitted(
        ?OnlineExamSession $session
    ): bool {
        return $session !== null
            && in_array($session->status, ['submitted', 'graded'], true);
    }

}
