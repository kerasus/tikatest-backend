<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Services\QuizScoringService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizSessionController extends Controller
{
    use CommonCRUD, Filter;

    private QuizScoringService $scoringService;

    public function __construct(QuizScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
        $this->middleware('auth:sanctum');
    }

    public function startSession(Request $request, $quizId): JsonResponse
    {
        $request->validate([
            'attempt_number' => 'sometimes|integer|min:1',
        ]);

        $quiz = Quiz::findOrFail($quizId);
        $studentId = $request->user()->id;
        $attemptNumber = $request->input('attempt_number', 1);

        if ($quiz->visible_at && now()->lt($quiz->visible_at)) {
            return $this->jsonResponseError('Quiz is not available', 403);
        }

        if ($quiz->start_time && now()->lt($quiz->start_time)) {
            return $this->jsonResponseError('Quiz has not started yet', 409);
        }

        if ($quiz->end_time && now()->gt($quiz->end_time)) {
            return $this->jsonResponseError('Quiz time range has ended', 409);
        }

        try {
            $payload = DB::transaction(function () use ($request, $quiz, $quizId, $studentId, $attemptNumber) {
                $existingSession = QuizSession::where('quiz_id', $quizId)
                    ->where('student_id', $studentId)
                    ->where('attempt_number', $attemptNumber)
                    ->lockForUpdate()
                    ->first();

                if ($existingSession) {
                    if ($existingSession->isActive()) {
                        $existingSession->load(['quiz', 'responses']);

                        return [
                            'session' => $existingSession,
                            'remaining_time' => $existingSession->getRemainingTimeInSeconds(),
                        ];
                    }
                    if ($existingSession->status === 'submitted' || $existingSession->status === 'graded') {
                        return ['error' => 'Quiz already submitted', 'status' => 409];
                    }
                    if ($existingSession->isExpired()) {
                        $existingSession->update(['status' => 'expired', 'answer_status' => 'sent', 'is_locked' => true, 'ended_at' => now()]);
                    }
                }

                $duration = $this->getQuizDurationInSeconds($quiz);
                $endsAt = now()->addSeconds($duration);
                if ($quiz->end_time && $endsAt->gt($quiz->end_time)) {
                    $duration = max(0, now()->diffInSeconds($quiz->end_time, false));
                    $endsAt = $quiz->end_time;
                }

                if ($duration <= 0) {
                    return ['error' => 'Quiz time range has ended', 'status' => 409];
                }

                $session = QuizSession::create([
                    'quiz_id' => $quizId,
                    'student_id' => $studentId,
                    'status' => 'in_progress',
                    'session_started_at' => now(),
                    'session_ended_at' => $endsAt,
                    'duration_seconds' => $duration,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'attempt_number' => $attemptNumber,
                ]);

                return [
                    'session' => $session,
                    'remaining_time' => $session->getRemainingTimeInSeconds(),
                ];
            });

            if (isset($payload['error'])) {
                return $this->jsonResponseError($payload['error'], $payload['status'] ?? 400);
            }

            return $this->jsonResponseOk($payload);
        } catch (\Exception $e) {
            return $this->jsonResponseError('Failed to start session: '.$e->getMessage(), 500);
        }
    }

    public function getSession(int $sessionId): JsonResponse
    {
        $session = QuizSession::with(['quiz', 'responses'])->findOrFail($sessionId);

        $userId = auth()->id();
        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        return $this->jsonResponseOk([
            'session' => $session,
            'remaining_time' => $session->getRemainingTimeInSeconds(),
            'is_expired' => $session->isExpired(),
        ]);
    }

    public function submitAnswer(Request $request, int $sessionId): JsonResponse
    {
        $request->validate([
            'question_number' => 'required|integer|min:1',
            'answer_text' => 'nullable|string',
            'submitted_option' => 'nullable|string|max:10',
        ]);

        $session = QuizSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        if ($session->status !== 'in_progress' || $session->isExpired()) {
            return $this->jsonResponseError('Session expired or not in progress', 409);
        }

        $response = $session->responses()->updateOrCreate(
            ['question_number' => $request->input('question_number')],
            [
                'quiz_id' => $session->quiz_id,
                'user_id' => $userId,
                'answer_text' => $request->input('answer_text'),
                'submitted_option' => $request->input('submitted_option'),
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
        $session = QuizSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        try {
            if (in_array($session->status, ['submitted', 'graded'], true)) {
                return $this->jsonResponseOk([
                    'message' => 'Quiz already submitted',
                    'session' => $session,
                ]);
            }

            DB::transaction(function () use ($session) {
                $lockedSession = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedSession->status, ['submitted', 'graded'], true)) {
                    $lockedSession->update([
                        'status' => 'submitted',
                        'submitted_at' => now(),
                        'time_used_seconds' => min(
                            $lockedSession->duration_seconds ?? 0,
                            now()->diffInSeconds($lockedSession->session_started_at)
                        ),
                    ]);

                    $scoreData = $this->scoringService->calculateSessionScore($lockedSession);

                    $lockedSession->update([
                        'percent' => $scoreData['percent'],
                        'booklet_scores' => $scoreData['booklet_scores'],
                        'answer_status' => 'sent',
                        'ended_at' => now(),
                    ]);

                    $lockedSession->update(['status' => 'graded']);
                }
            });

            $session->refresh();

            return $this->jsonResponseOk([
                'message' => 'Quiz submitted successfully',
                'session' => $session,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponseError('Failed to submit: '.$e->getMessage(), 500);
        }
    }

    public function mySessions(Request $request): JsonResponse
    {
        $sessions = QuizSession::where('student_id', auth()->id())
            ->with(['quiz', 'responses'])
            ->orderBy('session_started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($sessions);
    }

    public function getQuizSessions(int $quizId): JsonResponse
    {
        $sessions = QuizSession::where('quiz_id', $quizId)
            ->with(['student'])
            ->orderBy('session_started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($sessions);
    }

    public function autoExpire(Request $request): JsonResponse
    {
        $now = now();
        $expiredSessions = QuizSession::where('status', 'in_progress')
            ->where('session_ended_at', '<', $now)
            ->get();

        $count = 0;
        foreach ($expiredSessions as $session) {
            $session->update(['status' => 'expired', 'answer_status' => 'sent', 'is_locked' => true, 'ended_at' => now()]);
            $count++;
        }

        return $this->jsonResponseOk(['message' => $count.' sessions expired']);
    }

    private function getQuizDurationInSeconds(Quiz $quiz): int
    {
        if ($quiz->time_limit) {
            return $quiz->time_limit * 60;
        }

        return 3600;
    }
}
