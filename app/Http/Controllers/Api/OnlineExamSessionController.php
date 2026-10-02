<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\OnlineExamSession;
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
    use CommonCRUD, Filter;

    public function __construct(
        protected OnlineExamSessionService $sessionService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'getExamSessions']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
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
        $canViewAll = $user->hasRole(UserRoleType::Admin->value) || $user->hasPermissionTo('exams.view');

        if ($session->student_id !== $user->id && ! $canViewAll) {
            return $this->jsonResponseError('دسترسی غیرمجاز', 403);
        }

        return $this->jsonResponseOk($this->sessionService->buildSessionPayload($session));
    }

    public function getMyResultByExamId(Request $request, int $examId): JsonResponse
    {
        $request->validate([
            'attempt_number' => 'sometimes|integer|min:1',
        ]);

        try {
            $studentId = auth()->id();
            $attemptNumber = $request->input('attempt_number');

            $result = $this->sessionService->getExamResultPayload($examId, $studentId, $attemptNumber);

            return $this->jsonResponseOk($result);
        } catch (ModelNotFoundException $e) {
            return $this->jsonResponseError($e->getMessage(), 404);
        }
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

            if (! $user || ! $user->hasAnyRole($allowedRoles)) {
                return $this->jsonResponseError('شما دسترسی به این نتیجه آزمون را ندارید.', 403);
            }

            $result = $this->sessionService->getExamResultPayloadBySessionId($sessionId);

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
            return $this->jsonResponseError('آزمون آنلاین مورد نظر یافت نشد.', 404);
        }

        try {
            $payload = DB::transaction(function () use ($exam, $request) {
                return $this->sessionService->startOrCreateSession(
                    exam: $exam,
                    user: $request->user(),
                    attemptNumber: $request->integer('attempt_number', 1),
                    ip: $request->ip(),
                    userAgent: $request->userAgent()
                );
            });

            return $this->jsonResponseOk($payload);
        } catch (HttpException $e) {
            return $this->jsonResponseError($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            return $this->jsonResponseServerError(['errors' => ['session' => $e->getMessage()]]);
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
            $this->sessionService->saveAnswer(
                session: $session,
                userId: auth()->id(),
                questionNumber: $request->integer('question_number'),
                submittedOption: $request->input('submitted_option'),
                answerText: $request->input('answer_text')
            );

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
            $gradedSession = DB::transaction(function () use ($session) {
                return $this->sessionService->submitAndGradeSession($session, auth()->id());
            });

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

    public function getExamSessions(int $examId): JsonResponse
    {
        $sessions = OnlineExamSession::query()
            ->where('exam_id', $examId)
            ->with(['student', 'exam.category', 'exam.lesson'])
            ->orderBy('started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($sessions);
    }

    public function destroy($id): JsonResponse
    {
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
}
