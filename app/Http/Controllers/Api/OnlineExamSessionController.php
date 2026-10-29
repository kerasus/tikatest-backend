<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Traits\ChecksOnlineExamAccess;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\OnlineExamSession;
use App\Models\User;
use App\Services\OnlineExamSessionService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OnlineExamSessionController extends Controller
{
    use CommonCRUD, Filter, ChecksOnlineExamAccess;

    public function __construct(
        protected OnlineExamSessionService $sessionService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'getExamSessions', 'getExamSessionsGroupedByClass']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->denyStudentManagementAccess($request->user());

        if ($request->query('exam_id')) {
            $this->sessionService->repairExpiredSessionsForExam($request->query('exam_id'));
        }

        $config = [
            'filterKeys' => ['status'],
            'filterKeysExact' => ['exam_id', 'student_id', 'is_locked'],
            'filterDate' => ['started_at', 'submitted_at', 'created_at'],
            'eagerLoads' => $this->sessionListRelations(),
        ];

        return $this->commonIndex($request, OnlineExamSession::class, $config);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $session = OnlineExamSession::query()
            ->with($this->sessionService->getSessionDetailRelations())
            ->findOrFail($id);

        $user = $request->user();
        $canViewAll = ! $user->hasRole(UserRoleType::Student->value)
            && ($user->hasRole(UserRoleType::Admin->value) || $user->can('exams.view'));

        if ($session->student_id !== $user->id && ! $canViewAll) {
            return $this->jsonResponseError('دسترسی غیرمجاز', 403);
        }

        if (! $canViewAll) {
            $this->checkStudentExamAccess($session->exam, $user);
            $this->checkExamVisibility($session->exam);

            if ($session->status === 'in_progress') {
                $this->checkExamOpen($session->exam);

                if ($this->sessionTimeExpired($session)) {
                    return $this->jsonResponseError('مهلت زمانی نشست آزمون شما منقضی شده است.', 410);
                }
            } elseif (! $this->examHasEnded($session->exam) && $session->exam->onlineExamDetail->ends_at) {
                return $this->jsonResponseError('پاسخنامه هنوز قابل مشاهده نیست.', 409);
            }
        }

        $payload = $this->sessionService->buildExamResultPayload($session->exam, $session, $session->student_id);

        if (! $canViewAll) {
            $this->hideStudentSolutions($payload, $this->canShowSolutions($session->exam, $session));
        }

        return $this->jsonResponseOk($payload);
    }

    public function getMyResultByExamId(Request $request, int $examId): JsonResponse
    {
        $request->validate([
            'attempt_number' => 'sometimes|integer|min:1',
        ]);

        $user = $request->user();

        $exam = Exam::query()
            ->where('delivery_mode', 'online')
            ->with('onlineExamDetail')
            ->findOrFail($examId);

        $this->checkStudentExamAccess($exam, $user);
        $this->checkExamVisibility($exam);

        $query = OnlineExamSession::query()
            ->where('exam_id', $examId)
            ->where('student_id', $user->id)
            ->whereIn('status', ['submitted', 'graded', 'expired'])
            ->with($this->sessionService->getSessionDetailRelations());

        if ($request->filled('attempt_number')) {
            $query->where('attempt_number', $request->integer('attempt_number'));
        } else {
            $query->orderByDesc('attempt_number');
        }

        $session = $query->first();

        $detail = $exam->onlineExamDetail;

        /*
         * اولویت‌بندی مشاهده کلید و راه‌حل‌ها:
         * ۱. اگر اپراتور صراحتاً answers_visible_at تعریف کرده باشد:
         *    - ملاک منحصراً رسیدن به این تاریخ است (چه دانش‌آموز آزمون داده باشد چه غایب باشد).
         * ۲. اگر answers_visible_at تعریف نشده باشد:
         *    - اگر دانش‌آموز آزمون داده باشد ($session موجود است) => تابع canShowSolutions
         *    - اگر در آزمون غایب بوده ($session نال است) => پایان یافتن کل بازه زمانی آزمون (examHasEnded)
         */
        $hasCustomAnswerTime = ! empty($detail?->answers_visible_at);

        if ($hasCustomAnswerTime) {
            $canViewSolutions = now()->gte($detail->answers_visible_at);
        } else {
            $canViewSolutions = $session
                ? $this->canShowSolutions($exam, $session)
                : $this->examHasEnded($exam);
        }

        // اگر هنوز موعد مشاهده پاسخنامه نرسیده است:
        if (! $canViewSolutions) {
            if ($hasCustomAnswerTime) {
                return $this->jsonResponseError('پاسخنامه بر اساس زمان‌بندی اعلام‌شده هنوز قابل مشاهده نیست.', 409);
            }

            return $this->jsonResponseError('پاسخنامه هنوز قابل مشاهده نیست.', 409);
        }

        /*
         * ساخت نتیجه آزمون یا بازبینی (Review Mode):
         * اگر $session نال باشد (غایب یا شرکت نکرده)، متد سرویس به صورت خودکار
         * پی‌لود را با session = null و is_review_only = true می‌سازد.
         */
        $payload = $this->sessionService->buildExamResultPayload($exam, $session, $user->id);

        // اعمال مخفی‌سازی در صورتی که مجاز به دیدن راه‌حل‌ها نباشد
        $this->hideStudentSolutions($payload, $canViewSolutions);

        return $this->jsonResponseOk($payload);
    }

    public function getResultBySessionId(Request $request, int $sessionId): JsonResponse
    {
        $user = $request->user();

        try {
            $allowedRoles = [
                UserRoleType::Admin->value,
                UserRoleType::Manager->value,
                UserRoleType::Teacher->value,
                UserRoleType::Staff->value,
            ];

            if (! $user || $user->hasRole(UserRoleType::Student->value) || ! $user->hasAnyRole($allowedRoles)) {
                return $this->jsonResponseError('شما دسترسی به این نتیجه آزمون را ندارید.', 403);
            }

            $session = OnlineExamSession::query()
                ->whereIn('status', ['submitted', 'graded', 'expired'])
                ->with($this->sessionService->getSessionDetailRelations())
                ->findOrFail($sessionId);

            $result = $this->sessionService->buildExamResultPayload($session->exam, $session, $session->student_id);

            return $this->jsonResponseOk($result);
        } catch (ModelNotFoundException $e) {
            return $this->jsonResponseError($e->getMessage(), 404);
        }
    }

    /**
     * شروع یا ورود مجدد به جلسه آزمون
     */
    public function startSession(Request $request, int $examId): JsonResponse
    {
        $request->validate([
            'attempt_number' => 'sometimes|integer|min:1',
        ]);

        $exam = Exam::query()
            ->whereKey($examId)
            ->where('delivery_mode', 'online')
            ->with([
                'onlineExamDetail.answerKeys' => fn ($q) => $q->where('is_active', true),
            ])
            ->first();

        if (! $exam) {
            return $this->jsonResponseError(
                'آزمون آنلاین مورد نظر یافت نشد.',
                404
            );
        }

        try {
            $student = $request->user();
            $attemptNumber = $request->integer('attempt_number', 1);

            $this->checkStudentExamAccess($exam, $student);
            $this->checkExamOpen($exam);

            $result = DB::transaction(function () use (
                $exam,
                $request,
                $student,
                $attemptNumber
            ) {
                $this->checkExamOpen($exam);

                $existing = OnlineExamSession::query()
                    ->where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->where('attempt_number', $attemptNumber)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status !== 'in_progress') {
                    throw new HttpException(
                        $existing->status === 'expired' ? 410 : 409,
                        'این نشست آزمون دیگر قابل شروع نیست.'
                    );
                }

                $result = $this->sessionService->startOrCreateSession(
                    exam: $exam,
                    user: $student,
                    attemptNumber: $attemptNumber,
                    ip: $request->ip(),
                    userAgent: $request->userAgent()
                );

                if ($this->sessionTimeExpired($result['session'])) {
                    $result['session']->update([
                        'status' => 'expired',
                        'is_locked' => true,
                    ]);

                    return [
                        'error' => 'مهلت زمانی نشست آزمون شما منقضی شده است.',
                    ];
                }

                return $result;
            });

            if (isset($result['error'])) {
                return $this->jsonResponseError($result['error'], 410);
            }

            /** @var OnlineExamSession $session */
            $session = $result['session'];

            // لود مطمئن پاسخ‌های قبلی دانش‌آموز در این نشست (در صورت عدم لود)
            $session->loadMissing('responses');

            // هنگام شروع یا ادامه نشست، اطلاعات تصحیح پاسخ‌ها نباید برای دانش‌آموز قابل رویت باشد
            if ($session->relationLoaded('responses') && $session->responses) {
                $session->responses->makeHidden([
                    'correct_option',
                    'is_correct',
                    'marks_obtained',
                ]);
            }

            // ساخت پاسخ با همان ساختار استاندارد کارنامه
            $payload = $this->sessionService->buildExamResultPayload(
                exam: $exam,
                session: $session,
                studentId: (int) $student->id
            );

            // ۱. مخفی کردن راه‌حل‌ها و پاسخ‌های تشریحی از روی آبجکت exam
            $this->hideExamSolutions($payload['exam']);

            // روابط exam روی session ممکن است آبجکت جداگانه‌ای داشته باشند
            if ($session->relationLoaded('exam') && $session->exam) {
                $this->hideExamSolutions($session->exam);
            }

            // ۲. تابع کمکی برای امن‌سازی کلیدهای سوالات درون exam.online_exam_detail.answer_keys
            $sanitizeDetailAnswerKeys = static function ($examModel): void {
                if (
                    $examModel instanceof Exam
                    && $examModel->relationLoaded('onlineExamDetail')
                    && $examModel->onlineExamDetail?->relationLoaded('answerKeys')
                ) {
                    $examModel->onlineExamDetail->answerKeys->makeHidden([
                        'correct_option',
                        'is_correct',
                        'marks_obtained',
                        'solution_text',
                        'solution_file_path',
                    ]);
                }
            };

            // اعمال فیلتر امنیتی روی exam اصلی و exam احتمالی لود شده روی session
            $sanitizeDetailAnswerKeys($payload['exam'] ?? null);
            if (isset($payload['session']) && $payload['session'] instanceof OnlineExamSession) {
                $sanitizeDetailAnswerKeys($payload['session']->exam ?? null);
            }

            // ۳. حذف قطعی کلید answer_keys از ریشه payload تا فقط در exam.online_exam_detail بماند
            unset($payload['answer_keys']);

            return $this->jsonResponseOk($payload);
        } catch (HttpException $e) {
            return $this->jsonResponseError(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->jsonResponseServerError([
                'errors' => [
                    'session' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * ثبت یا به‌روزرسانی گزینه انتخاب شده
     */
    public function submitAnswer(Request $request, int $sessionId): JsonResponse
    {
        $request->validate([
            'question_number' => 'required|integer|min:1',
            'submitted_option' => 'nullable|string|max:10',
            'answer_text' => 'nullable|string',
        ]);

        $session = OnlineExamSession::query()
            ->with('exam.onlineExamDetail')
            ->find($sessionId);

        if (! $session) {
            return $this->jsonResponseError('جلسه آزمون یافت نشد.', 404);
        }

        try {
            $this->checkStudentExamAccess($session->exam, $request->user());
            $this->checkSessionOwner($session, $request->user());

            $saved = DB::transaction(function () use ($session, $request) {
                $locked = OnlineExamSession::query()->with('exam.onlineExamDetail')->lockForUpdate()->findOrFail($session->id);
                $this->checkSessionOwner($locked, $request->user());
                $this->checkExamOpen($locked->exam);

                if ($locked->status !== 'in_progress' || $locked->is_locked) {
                    throw new HttpException(409, 'این آزمون در حال برگزاری نیست.');
                }

                if ($this->sessionTimeExpired($locked)) {
                    $locked->update(['status' => 'expired', 'is_locked' => true]);

                    return false;
                }

                $this->sessionService->saveAnswer(
                    session: $locked,
                    userId: $request->user()->id,
                    questionNumber: $request->integer('question_number'),
                    submittedOption: $request->input('submitted_option'),
                    answerText: $request->input('answer_text')
                );

                return true;
            });

            if (! $saved) {
                return $this->jsonResponseError('مهلت زمانی آزمون به پایان رسیده است.', 409);
            }

            return $this->jsonResponseOk([
                'message' => 'پاسخ با موفقیت ذخیره شد.',
//                'response' => $response,
//                'remaining_time' => max(0, (int) $this->sessionService->calculateRemainingTime($session)),
            ]);
        } catch (HttpException $e) {
            return $this->jsonResponseError($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->jsonResponseServerError(['errors' => ['session' => $e->getMessage()]]);
        }
    }

    /**
     * تحویل نهایی آزمون و ارسال به سیستم نمره‌دهی
     */
    public function submitSession(Request $request, int $sessionId): JsonResponse
    {
        $session = OnlineExamSession::query()
            ->with('exam.onlineExamDetail')
            ->find($sessionId);

        if (! $session) {
            return $this->jsonResponseError('جلسه آزمون یافت نشد.', 404);
        }

        try {
            $this->checkStudentExamAccess($session->exam, $request->user());
            $this->checkSessionOwner($session, $request->user());

            $gradedSession = DB::transaction(function () use ($session, $request) {
                $locked = OnlineExamSession::query()->with('exam.onlineExamDetail')->lockForUpdate()->findOrFail($session->id);
                $this->checkSessionOwner($locked, $request->user());
                $this->checkExamOpen($locked->exam);

                if ($locked->status !== 'in_progress' || $locked->is_locked) {
                    throw new HttpException(409, 'این نشست آزمون قابل ثبت نیست.');
                }

                if ($this->sessionTimeExpired($locked)) {
                    $locked->update(['status' => 'expired', 'is_locked' => true]);

                    return null;
                }

                return $this->sessionService->submitAndGradeSession($locked, $request->user()->id);
            });

            if (! $gradedSession) {
                return $this->jsonResponseError('مهلت زمانی آزمون به پایان رسیده است.', 409);
            }

            $this->hideSessionSolutions($gradedSession);

            return $this->jsonResponseOk([
                'message' => 'آزمون با موفقیت ثبت و نمره‌دهی شد.',
                'session' => $gradedSession,
            ]);
        } catch (HttpException $e) {
            return $this->jsonResponseError($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->jsonResponseServerError(['errors' => ['session' => $e->getMessage()]]);
        }
    }

    public function mySessions(Request $request): JsonResponse
    {
        $sessions = OnlineExamSession::query()
            ->where('student_id', auth()->id())
            ->with($this->sessionListRelations())
            ->withCount('responses')
            ->latest('started_at')
            ->paginate($request->integer('per_page', 20));

        return $this->jsonResponseOk($sessions);
    }

    public function getExamSessions(Request $request, int $examId): JsonResponse
    {
        $this->denyStudentManagementAccess($request->user());

        $sessions = OnlineExamSession::query()
            ->where('exam_id', $examId)
            ->with(['student', 'exam.category', 'exam.lesson'])
            ->orderBy('started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($sessions);
    }

    public function getExamSessionsGroupedByClass(Request $request, int $examId): JsonResponse
    {
        $this->denyStudentManagementAccess($request->user());

        $exam = Exam::query()->where('delivery_mode', 'online')->find($examId);

        if (! $exam) {
            return $this->jsonResponseError('آزمون آنلاین مورد نظر یافت نشد.', 404);
        }

        return $this->jsonResponseOk($this->sessionService->getExamSessionsGroupedByClass($exam));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $this->denyStudentManagementAccess($request->user());

        $session = OnlineExamSession::query()->find($id);

        if (! $session) {
            return response()->json([
                'message' => 'نشست آزمون مورد نظر یافت نشد.',
            ], Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($session) {
            if (method_exists($session, 'responses')) {
                $session->responses()->delete();
            }

            $session->delete();
        });

        return response()->json([
            'message' => 'نشست آزمون با موفقیت حذف شد.',
        ], Response::HTTP_OK);
    }

    private function sessionListRelations(): array
    {
        return [
            'exam:id,name,exam_category_id,lesson_id',
            'exam.category:id,title',
            'exam.lesson:id,name',
            'student:id,first_name,last_name',
        ];
    }

    private function denyStudentManagementAccess(User $user): void
    {
        abort_if($user->hasRole(UserRoleType::Student->value), 403, 'دانش‌آموز به اطلاعات مدیریتی آزمون دسترسی ندارد.');
    }

    private function sessionTimeExpired(OnlineExamSession $session): bool
    {
        $remaining = $this->sessionService->calculateRemainingTime($session);

        return $remaining !== null && $remaining <= 0;
    }

    private function hideExamSolutions(Exam $exam): void
    {
        $detail = $exam->onlineExamDetail;

        if ($detail) {
            $detail->makeHidden(['solution', 'solution_file', 'solution_path', 'solution_descriptive']);
            $detail->answerKeys?->makeHidden(['correct_option']);
        }
    }

    private function hideSessionSolutions(OnlineExamSession $session): void
    {
        if ($this->canShowSolutions($session->exam, $session)) {
            return;
        }

        $this->hideExamSolutions($session->exam);
        $session->unsetRelation('results');
        $session->makeHidden(['percent', 't_score']);
        $session->responses?->makeHidden(['is_correct', 'marks_obtained']);
    }

    private function hideStudentSolutions(array &$payload, bool $canShowSolutions): void
    {
        if ($canShowSolutions) {
            return;
        }

        $this->hideExamSolutions($payload['exam']);

        if ($payload['answer_keys']) {
            foreach ($payload['answer_keys'] as &$key) {
                unset($key['correct_option'], $key['is_correct'], $key['marks_obtained']);
            }
            unset($key);
        }

        if ($payload['session']) {
            $payload['session']['percent'] = null;
            $payload['session']['t_score'] = null;
            $payload['session']['responses']?->makeHidden(['is_correct', 'marks_obtained']);
        }

        $payload['rankings'] = ['metric' => 'percent', 'attempt_policy' => 'latest_graded', 'classes' => [], 'levels' => []];
    }

    /**
     * دریافت آمار و تحلیل عملکرد سوالات آزمون آنلاین جهت نمایش در نمودارها
     */
    public function getExamQuestionsAnalytics(Request $request, int $examId): JsonResponse
    {
        $user = $request->user();

        $allowedRoles = [
            UserRoleType::Admin->value,
            UserRoleType::Manager->value,
            UserRoleType::Teacher->value,
            UserRoleType::Staff->value,
        ];

        if (! $user || $user->hasRole(UserRoleType::Student->value) || ! $user->hasAnyRole($allowedRoles)) {
            return $this->jsonResponseError('شما دسترسی به آمار این آزمون را ندارید.', 403);
        }

        $exam = Exam::query()
            ->whereKey($examId)
            ->where('delivery_mode', 'online')
            ->first();

        if (! $exam) {
            return $this->jsonResponseError('آزمون آنلاین مورد نظر یافت نشد.', 404);
        }

        if (! $exam->onlineExamDetail) {
            return $this->jsonResponseError('تنظیمات آزمون آنلاین برای این آزمون یافت نشد.', 404);
        }

        try {
            $analyticsData = $this->sessionService->getExamQuestionsAnalytics($exam);

            return $this->jsonResponseOk($analyticsData);
        } catch (HttpException $e) {
            return $this->jsonResponseError($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->jsonResponseServerError(['errors' => ['analytics' => $e->getMessage()]]);
        }
    }
}
