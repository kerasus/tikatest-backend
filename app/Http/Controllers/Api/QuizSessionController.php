<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizSession;
use App\Models\QuizAttempt;
use App\Models\Quiz;
use App\Models\QuizAntiCheatEvent;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QuizSessionController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
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

        try {
            $existingSession = QuizSession::where('quiz_id', $quizId)
                ->where('student_id', $studentId)
                ->where('attempt_number', $attemptNumber)
                ->first();

            if ($existingSession) {
                if ($existingSession->isActive()) {
                    $existingSession->load(['quiz.questions.options', 'attempt']);
                    return $this->jsonResponseOk([
                        'session' => $existingSession,
                        'attempt' => $existingSession->attempt,
                        'remaining_time' => $existingSession->getRemainingTimeInSeconds(),
                    ]);
                }
                if ($existingSession->status === 'submitted' || $existingSession->status === 'graded') {
                    return $this->jsonResponseError('Quiz already submitted', 409);
                }
                if ($existingSession->isExpired()) {
                    $existingSession->update(['status' => 'expired']);
                    $attempt = QuizAttempt::where('quiz_id', $quizId)
                        ->where('student_id', $studentId)
                        ->where('quiz_session_id', $existingSession->id)
                        ->latest()
                        ->first();
                    if ($attempt && $attempt->answer_status === 'not_sent') {
                        $attempt->update([
                            'answer_status' => 'sent',
                            'is_locked' => true,
                            'ended_at' => now(),
                        ]);
                    }
                }
            }
                if ($existingSession->status === 'submitted' || $existingSession->status === 'graded') {
                    return $this->jsonResponseError('Quiz already submitted', 409);
                }
                if ($existingSession->isExpired()) {
                    $existingSession->update(['status' => 'expired']);
                    $attempt = QuizAttempt::where('quiz_id', $quizId)
                        ->where('student_id', $studentId)
                        ->where('quiz_session_id', $existingSession->id)
                        ->latest()
                        ->first();
                    if ($attempt && $attempt->answer_status === 'not_sent') {
                        $attempt->update([
                            'answer_status' => 'sent',
                            'is_locked' => true,
                            'ended_at' => now(),
                        ]);
                    }
                }
            }

            $duration = $this->getQuizDurationInSeconds($quiz);

            $sessionData = [
                'quiz_id' => $quizId,
                'student_id' => $studentId,
                'status' => 'in_progress',
                'session_started_at' => now(),
                'session_ended_at' => now()->addSeconds($duration),
                'duration_seconds' => $duration,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempt_number' => $attemptNumber,
            ];

            $session = QuizSession::create($sessionData);

            $attempt = QuizAttempt::create([
                'quiz_id' => $quizId,
                'quiz_session_id' => $session->id,
                'student_id' => $studentId,
                'started_at' => now(),
                'answer_status' => 'not_sent',
                'is_locked' => false,
            ]);

            $session->load(['quiz.questions.options']);

            return $this->jsonResponseOk([
                'session' => $session,
                'attempt' => $attempt,
                'remaining_time' => $session->getRemainingTimeInSeconds(),
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponseError('Failed to start session: ' . $e->getMessage(), 500);
        }
    }

    public function getSession(int $sessionId): JsonResponse
    {
        $session = QuizSession::with(['quiz.questions.options', 'attempt'])->findOrFail($sessionId);

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
            'quiz_question_id' => 'required|exists:quiz_questions,id',
            'quiz_question_option_id' => 'nullable|exists:quiz_question_options,id',
            'answer_text' => 'nullable|string',
        ]);

        $session = QuizSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        if ($session->status !== 'in_progress' || $session->isExpired()) {
            return $this->jsonResponseError('Session expired or not in progress', 409);
        }

        try {
            $attempt = QuizAttempt::where('quiz_id', $session->quiz_id)
                ->where('student_id', $userId)
                ->latest()
                ->firstOrFail();

            $response = $attempt->responses()->updateOrCreate(
                ['quiz_question_id' => $request->input('quiz_question_id')],
                [
                    'quiz_question_option_id' => $request->input('quiz_question_option_id'),
                    'answer_text' => $request->input('answer_text'),
                    'answered_at' => now(),
                ]
            );

            return $this->jsonResponseOk([
                'message' => 'Answer saved successfully',
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponseError('Failed to save answer: ' . $e->getMessage(), 500);
        }
    }

    public function submitSession(Request $request, int $sessionId): JsonResponse
    {
        $session = QuizSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        try {
            $session->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'time_used_seconds' => now()->diffInSeconds($session->session_started_at),
            ]);

            $this->gradeAttempt($session);

            return $this->jsonResponseOk([
                'message' => 'Quiz submitted successfully',
                'session' => $session,
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponseError('Failed to submit: ' . $e->getMessage(), 500);
        }
    }

    public function myAttempts(Request $request): JsonResponse
    {
        $attempts = QuizSession::where('student_id', auth()->id())
            ->with(['quiz'])
            ->orderBy('session_started_at', 'desc')
            ->get();

        return $this->jsonResponseOk($attempts);
    }

    public function antiCheatEvents(Request $request, $sessionId): JsonResponse
    {
        $session = QuizSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        $events = QuizAntiCheatEvent::where('quiz_session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->jsonResponseOk($events);
    }

    public function reportAntiCheatEvent(Request $request, $sessionId): JsonResponse
    {
        $session = QuizSession::findOrFail($sessionId);
        $userId = auth()->id();

        if ($session->student_id !== $userId) {
            return $this->jsonResponseError('Unauthorized', 403);
        }

        $request->validate([
            'event_type' => 'required|string|max:100',
            'event_data' => 'nullable|array',
        ]);

        QuizAntiCheatEvent::create([
            'quiz_session_id' => $sessionId,
            'student_id' => $userId,
            'event_type' => $request->event_type,
            'event_data' => $request->event_data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->jsonResponseOk(['message' => 'Event reported']);
    }

    public function autoExpire(Request $request): JsonResponse
    {
        $now = now();
        $expiredSessions = QuizSession::where('status', 'in_progress')
            ->where('session_ended_at', '<', $now)
            ->get();

        $count = 0;
        foreach ($expiredSessions as $session) {
            $session->update(['status' => 'expired']);
            $attempt = QuizAttempt::where('quiz_id', $session->quiz_id)
                ->where('student_id', $session->student_id)
                ->latest()
                ->first();
            if ($attempt && $attempt->answer_status === 'not_sent') {
                $attempt->update([
                    'answer_status' => 'sent',
                    'is_locked' => true,
                    'ended_at' => now(),
                ]);
            }
            $count++;
        }

        return $this->jsonResponseOk(['message' => $count . ' sessions expired']);
    }

    private function getQuizDurationInSeconds(Quiz $quiz): int
    {
        if ($quiz->timer) {
            return $quiz->timer ? \Carbon\Carbon::parse('00:00:00')->diffInSeconds(\Carbon\Carbon::parse($quiz->timer)) : 3600;
        }
        return 3600;
    }

    private function gradeAttempt(QuizSession $session): void
    {
        $attempt = QuizAttempt::where('quiz_id', $session->quiz_id)
            ->where('student_id', $session->student_id)
            ->latest()
            ->first();

        if (!$attempt) {
            return;
        }

        $totalMarks = 0;
        $obtainedMarks = 0;

        $responses = $attempt->responses()->with(['question', 'selectedOption'])->get();

        foreach ($responses as $response) {
            $question = $response->question;
            $totalMarks += $question->points;

            if ($response->selectedOption && $response->selectedOption->is_correct_answer) {
                $obtainedMarks += $question->points;
                $response->is_correct = true;
            } elseif ($question->has_negative_marking) {
                $obtainedMarks -= $question->negative_marks ?? 0;
                $response->is_correct = false;
            }

            $response->marks_obtained = $response->is_correct ? $question->points : 0;
            $response->save();
        }

        $percentage = $totalMarks > 0 ? ($obtainedMarks / $totalMarks) * 100 : 0;

        $attempt->update([
            'percent' => max(0, $percentage),
            'answer_status' => 'sent',
            'ended_at' => now(),
        ]);

        $session->update(['status' => 'graded']);
    }
}
