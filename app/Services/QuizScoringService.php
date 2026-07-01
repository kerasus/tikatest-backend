<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\QuizAnswerKey;

class QuizScoringService
{
    public function calculateSessionScore(QuizSession $session): array
    {
        $totalMarks = 0;
        $obtainedMarks = 0;

        $responses = $session->responses;

        foreach ($responses as $response) {
            $answerKey = QuizAnswerKey::where('quiz_id', $session->quiz_id)
                ->where('question_number', $response->question_number)
                ->first();

            $points = ($answerKey->weight ?? 1);
            $totalMarks += $points;

            if ($answerKey && $answerKey->is_active) {
                $correctOption = $answerKey->correct_option;
                if ($response->submitted_option === $correctOption) {
                    $obtainedMarks += $points;
                    $response->is_correct = true;
                } elseif ($response->answer_text !== null) {
                    $obtainedMarks += $points * 0.5;
                    $response->is_correct = true;
                } else {
                    $response->is_correct = false;
                }

                $response->marks_obtained = $response->is_correct ? $points : 0;
                $response->save();
            }
        }

        $percentage = $totalMarks > 0 ? ($obtainedMarks / $totalMarks) * 100 : 0;

        return [
            'percent' => max(0, $percentage),
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
        ];
    }

    public function recalculateAllSessions(Quiz $quiz): void
    {
        $sessions = $quiz->sessions()->get();

        foreach ($sessions as $session) {
            $scoreData = $this->calculateSessionScore($session);

            $session->update([
                'percent' => $scoreData['percent'],
            ]);
        }
    }

    public function getRankings(Quiz $quiz): array
    {
        $sessions = $quiz->sessions()
            ->where('answer_status', 'sent')
            ->with('student')
            ->get();

        $ranked = $sessions->map(function ($session) {
            return [
                'student_id' => $session->student_id,
                'student_name' => $session->student->full_name ?? 'Unknown',
                'percent' => $session->percent,
                'started_at' => $session->session_started_at,
                'ended_at' => $session->submitted_at,
                'answer_status' => $session->answer_status,
            ];
        })
            ->sortByDesc('percent')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            })
            ->toArray();

        return $ranked;
    }
}