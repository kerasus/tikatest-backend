<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\OnlineExamSession;
use App\Models\OnlineExamSessionResponse;
use App\Services\OnlineExamScoringService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnlineExamSessionController extends Controller
{
    use CommonCRUD, Filter;

    private OnlineExamScoringService $scoringService;

    public function __construct(OnlineExamScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'getExamSessions']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update', 'autoExpire']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['status'],
            'filterKeysExact' => ['exam_id', 'student_id', 'is_locked'],
            'filterDate' => ['started_at', 'submitted_at', 'created_at'],
            'eagerLoads' => ['exam', 'exam.category', 'exam.lesson', 'student', 'responses'],
        ];

        return $this->commonIndex($request, OnlineExamSession::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'student_id' => 'required|exists:users,id',
            'status' => 'in:not_started,in_progress,submitted,graded,expired',
            'started_at' => 'nullable|date',
            'submitted_at' => 'nullable|date',
            'duration_limit_seconds' => 'nullable|integer|min:0',
            'time_used_seconds' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0',
            'percent' => 'nullable|numeric|min:0|max:100',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string',
            'attempt_number' => 'nullable|integer|min:1',
            'is_locked' => 'boolean',
        ]);

        return $this->commonStore($request, OnlineExamSession::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $session = OnlineExamSession::with($this->sessionDetailRelations())->findOrFail($id);

        $user = $request->user();
        $canViewAllSessions = $user->hasRole(UserRoleType::Admin->value)
            || $user->hasPermissionTo('exams.view');

        if ($session->student_id !== $user->id && ! $canViewAllSessions) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        return $this->jsonResponseOk($this->buildSessionDetailPayload($session));
    }

    public function getResultByExamId(Request $request, int $examId): JsonResponse
    {
        $request->validate([
            'attempt_number' => 'sometimes|integer|min:1',
        ]);

        Exam::where('id', $examId)
            ->where('delivery_mode', 'online')
            ->firstOrFail();

        $studentId = auth()->id();

        $query = OnlineExamSession::where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->whereIn('status', ['submitted', 'graded'])
            ->with($this->sessionDetailRelations());

        if ($request->filled('attempt_number')) {
            $query->where('attempt_number', $request->integer('attempt_number'));
        } else {
            $query->orderByDesc('attempt_number');
        }

        $session = $query->first();

        if (! $session) {
            return $this->jsonResponseError('No completed session found for this exam', 404);
        }

        return $this->jsonResponseOk($this->buildSessionDetailPayload($session));
    }

    private function sessionDetailRelations(): array
    {
        return [
            'exam',
            'exam.category',
            'exam.lesson',
            'exam.onlineExamDetail',
            'exam.answerKeys',
            'student',
            'responses',
        ];
    }

    private function buildSessionDetailPayload(OnlineExamSession $session): array
    {
        $isCompleted = in_array($session->status, ['submitted', 'graded'], true);

        return [
            'session' => $session,
            'remaining_time' => null,
            'online_detail' => $session->exam->onlineExamDetail,
            'answer_keys' => $isCompleted
                ? $this->resultAnswerKeys($session)
                : $this->progressAnswerKeys($session),
        ];
    }

    public function update(Request $request, OnlineExamSession $onlineExamSession): JsonResponse
    {
        $request->validate([
            'exam_id' => 'sometimes|required|exists:exams,id',
            'student_id' => 'sometimes|required|exists:users,id',
            'status' => 'in:not_started,in_progress,submitted,graded,expired',
            'started_at' => 'nullable|date',
            'submitted_at' => 'nullable|date',
            'duration_limit_seconds' => 'nullable|integer|min:0',
            'time_used_seconds' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0',
            'percent' => 'nullable|numeric|min:0|max:100',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string',
            'attempt_number' => 'nullable|integer|min:1',
            'is_locked' => 'boolean',
        ]);

        return $this->commonUpdate($request, $onlineExamSession);
    }

    public function destroy(OnlineExamSession $onlineExamSession): JsonResponse
    {
        return $this->commonDestroy($onlineExamSession);
    }

    public function mySessions(Request $request): JsonResponse
    {
        $sessions = OnlineExamSession::where('student_id', auth()->id())
            ->with(['exam', 'exam.category', 'exam.lesson', 'responses'])
            ->orderBy('started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($sessions);
    }

    public function startSession(Request $request, $examId): JsonResponse
    {
        $request->validate([
            'attempt_number' => 'sometimes|integer|min:1',
        ]);

        $exam = Exam::findOrFail($examId);
        $studentId = auth()->id();
        $attemptNumber = $request->input('attempt_number', 1);

        $onlineExamDetail = $exam->onlineExamDetail;
        if (! $onlineExamDetail) {
            return $this->jsonResponseError('Online exam detail not found', 404);
        }

        if ($onlineExamDetail->visible_at && now()->lt($onlineExamDetail->visible_at)) {
            return $this->jsonResponseError('Exam is not available', 403);
        }

        if ($onlineExamDetail->starts_at && now()->lt($onlineExamDetail->starts_at)) {
            return $this->jsonResponseError('Exam has not started yet', 409);
        }

        if ($onlineExamDetail->ends_at && now()->gt($onlineExamDetail->ends_at)) {
            return $this->jsonResponseError('Exam time range has ended', 409);
        }

        try {
            $payload = DB::transaction(function () use ($request, $exam, $examId, $studentId, $attemptNumber, $onlineExamDetail) {
                $existingSession = OnlineExamSession::where('exam_id', $examId)
                    ->where('student_id', $studentId)
                    ->where('attempt_number', $attemptNumber)
                    ->lockForUpdate()
                    ->first();

                if ($existingSession) {
                    if ($existingSession->status === 'in_progress') {
                        $existingSession->load(['exam', 'exam.category', 'exam.lesson', 'responses']);

                        $elapsed = $existingSession->started_at
                            ? $existingSession->started_at->diffInSeconds(now())
                            : null;

                        $sessionRemaining = $existingSession->duration_limit_seconds && $elapsed !== null
                            ? max(0, $existingSession->duration_limit_seconds - $elapsed)
                            : null;

                        $globalRemaining = $onlineExamDetail->ends_at
                            ? max(0, now()->diffInSeconds($onlineExamDetail->ends_at, false))
                            : null;

                        $remainingTime = $sessionRemaining !== null && $globalRemaining !== null
                            ? min($sessionRemaining, $globalRemaining)
                            : ($sessionRemaining ?? $globalRemaining);

                        return [
                            'session' => $existingSession,
                            'remaining_time' => $remainingTime,
                            'online_detail' => $onlineExamDetail->load('booklets', 'exam.category', 'exam.lesson'),
                            'answer_keys' => $this->safeAnswerKeys($exam->answerKeys),
                        ];
                    }
                    if (in_array($existingSession->status, ['submitted', 'graded'])) {
                        return ['error' => 'Exam already submitted', 'status' => 409];
                    }
                    if ($existingSession->status === 'expired') {
                        return ['error' => 'Exam session expired', 'status' => 410];
                    }
                }

                $duration = $onlineExamDetail->time_limit_minutes
                    ? $onlineExamDetail->time_limit_minutes * 60
                    : 3600;

                $endsAt = now()->addSeconds($duration);
                if ($onlineExamDetail->ends_at && $endsAt->gt($onlineExamDetail->ends_at)) {
                    $duration = max(0, now()->diffInSeconds($onlineExamDetail->ends_at, false));
                    $endsAt = $onlineExamDetail->ends_at;
                }

                if ($duration <= 0) {
                    return ['error' => 'Exam time range has ended', 'status' => 409];
                }

                $session = OnlineExamSession::create([
                    'exam_id' => $examId,
                    'student_id' => $studentId,
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'duration_limit_seconds' => $duration,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'attempt_number' => $attemptNumber,
                ]);

                $session->load(['exam', 'exam.category', 'exam.lesson', 'responses']);

                return [
                    'session' => $session,
                    'remaining_time' => $duration,
                    'online_detail' => $onlineExamDetail->load(
                        'booklets',
                        'exam.category',
                        'exam.lesson'
                    ),
                    'answer_keys' => $this->safeAnswerKeys($exam->answerKeys),
                ];
            });

            if (isset($payload['error'])) {
                return $this->jsonResponseError($payload['error'], $payload['status'] ?? 400);
            }

            return $this->jsonResponseOk($payload);
        } catch (\Exception $e) {
            return $this->jsonResponseServerError(['errors' => ['session' => [$e->getMessage()]]]);
        }
    }

    public function getSession(int $sessionId): JsonResponse
    {
        $session = OnlineExamSession::with(['exam', 'exam.category', 'exam.lesson', 'responses'])
            ->findOrFail($sessionId);

        $userId = auth()->id();
        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        $onlineExamDetail = $session->exam->onlineExamDetail ?? null;
        $remaining = null;
        if ($session->status === 'in_progress' && $session->duration_limit_seconds) {
            $remaining = max(0, $session->duration_limit_seconds - $session->time_used_seconds);
        }

        return $this->jsonResponseOk([
            'session' => $session,
            'remaining_time' => $remaining,
            'is_expired' => $session->status === 'expired',
            'online_detail' => $onlineExamDetail,
            'answer_keys' => $this->safeAnswerKeys($session->exam->answerKeys ?? null),
        ]);
    }

    private function safeAnswerKeys($answerKeys): ?array
    {
        if (!$answerKeys) {
            return null;
        }

        return $answerKeys->map(function ($key) {
            return [
                'id' => $key->id,
                'exam_id' => $key->exam_id,
                'question_number' => $key->question_number,
                'number_of_choices' => $key->number_of_choices,
                'weight' => $key->weight,
                'has_negative_mark' => $key->has_negative_mark,
                'is_active' => $key->is_active,
            ];
        })->toArray();
    }

    private function progressAnswerKeys(OnlineExamSession $session): ?array
    {
        $answerKeys = $session->exam->answerKeys;

        if (! $answerKeys) {
            return null;
        }

        $responsesByQuestion = $session->responses->keyBy('question_number');

        return $answerKeys->map(function ($key) use ($responsesByQuestion) {
            $response = $responsesByQuestion->get($key->question_number);

            return [
                'id' => $key->id,
                'exam_id' => $key->exam_id,
                'question_number' => $key->question_number,
                'number_of_choices' => $key->number_of_choices,
                'submitted_option' => $response?->submitted_option,
                'weight' => $key->weight,
                'has_negative_mark' => $key->has_negative_mark,
                'is_active' => $key->is_active,
            ];
        })->toArray();
    }

    private function resultAnswerKeys(OnlineExamSession $session): ?array
    {
        $answerKeys = $session->exam->answerKeys;

        if (! $answerKeys) {
            return null;
        }

        $responsesByQuestion = $session->responses->keyBy('question_number');

        return $answerKeys->map(function ($key) use ($responsesByQuestion) {
            $response = $responsesByQuestion->get($key->question_number);

            return [
                'id' => $key->id,
                'exam_id' => $key->exam_id,
                'question_number' => $key->question_number,
                'number_of_choices' => $key->number_of_choices,
                'correct_option' => $key->correct_option,
                'submitted_option' => $response?->submitted_option,
                'weight' => $key->weight,
                'has_negative_mark' => $key->has_negative_mark,
                'is_active' => $key->is_active,
            ];
        })->toArray();
    }

    public function submitAnswer(Request $request, int $sessionId): JsonResponse
    {
        $request->validate([
            'question_number' => 'required|integer|min:1',
            'submitted_option' => 'nullable|string|max:10',
            'answer_text' => 'nullable|string',
        ]);

        $session = OnlineExamSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        if ($session->status !== 'in_progress') {
            return $this->jsonResponseError('Session is not in progress', 409);
        }

        $response = OnlineExamSessionResponse::updateOrCreate(
            [
                'online_exam_session_id' => $session->id,
                'question_number' => $request->input('question_number'),
            ],
            [
                'exam_id' => $session->exam_id,
                'user_id' => $userId,
                'submitted_option' => $request->input('submitted_option'),
                'answer_text' => $request->input('answer_text'),
                'answered_at' => now(),
            ]
        );

        return $this->jsonResponseOk([
            'message' => 'Answer saved successfully',
            'response' => $response,
        ]);
    }

    public function submitSession(Request $request, int $sessionId): JsonResponse
    {
        $session = OnlineExamSession::with(['exam', 'exam.category', 'exam.lesson', 'responses'])->findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        try {
            if (in_array($session->status, ['submitted', 'graded'], true)) {
                return $this->jsonResponseOk([
                    'message' => 'Exam already submitted',
                    'session' => $session,
                ]);
            }

            DB::transaction(function () use ($session) {
                $lockedSession = OnlineExamSession::whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedSession->status, ['submitted', 'graded'], true)) {
                    $timeUsedSeconds = $lockedSession->started_at
                        ? (int) $lockedSession->started_at->diffInSeconds(now())
                        : (int) ($lockedSession->time_used_seconds ?? 0);

                    if ($lockedSession->duration_limit_seconds) {
                        $timeUsedSeconds = min(
                            $timeUsedSeconds,
                            $lockedSession->duration_limit_seconds
                        );
                    }

                    $lockedSession->update([
                        'status' => 'submitted',
                        'submitted_at' => now(),
                        'time_used_seconds' => max(0, $timeUsedSeconds),
                    ]);

                    $scoreData = $this->scoringService->calculateSessionScore(
                        $lockedSession->load(['responses', 'exam.onlineExamDetail.booklets'])
                    );

                    $lockedSession->update([
                        'percent' => $scoreData['percent'],
                        'score' => $scoreData['obtained_marks'],
                        'status' => 'graded',
                    ]);
                }
            });

            $session->refresh();

            return $this->jsonResponseOk([
                'message' => 'Exam submitted successfully',
                'session' => $session,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponseServerError(['errors' => ['session' => [$e->getMessage()]]]);
        }
    }

    public function getExamSessions(int $examId): JsonResponse
    {
        $sessions = OnlineExamSession::where('exam_id', $examId)
            ->with(['student', 'exam.category', 'exam.lesson'])
            ->orderBy('started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($sessions);
    }

    public function autoExpire(Request $request): JsonResponse
    {
        $now = now();
        $expiredSessions = OnlineExamSession::where('status', 'in_progress')
            ->where(function ($query) use ($now) {
                $query->whereHas('exam.onlineExamDetail', function ($q) use ($now) {
                    $q->where('ends_at', '<', $now);
                })
                    ->orWhere(function ($q) use ($now) {
                        $q->whereRaw('started_at + INTERVAL duration_limit_seconds SECOND < ?', [$now->toDateTimeString()])
                            ->whereNotNull('started_at')
                            ->whereNotNull('duration_limit_seconds');
                    });
            })
            ->get();

        $count = 0;
        foreach ($expiredSessions as $session) {
            $session->update(['status' => 'expired', 'is_locked' => true]);
            $count++;
        }

        return $this->jsonResponseOk(['message' => $count.' sessions expired']);
    }
}
