<?php

namespace App\Services;

use App\Models\OnlineExamAnswerKey;
use App\Models\OnlineExamDetail;
use App\Models\OnlineExamSession;

class OnlineExamScoringService
{
    public function calculateSessionScore(OnlineExamSession $session): array
    {
        $totalMarks = 0;
        $obtainedMarks = 0;

        $responses = $session->responses;
        $onlineExamDetail = $session->exam->onlineExamDetail ?? null;

        foreach ($responses as $response) {
            $answerKey = OnlineExamAnswerKey::where('exam_id', $session->exam_id)
                ->where('question_number', $response->question_number)
                ->first();

            $points = ($answerKey->weight ?? 1);
            $totalMarks += $points;

            if ($answerKey && $answerKey->is_active) {
                $correctOption = $answerKey->correct_option;
                if ($response->submitted_option === $correctOption) {
                    $obtainedMarks += $points;
                    $response->is_correct = true;
                } elseif ($answerKey->has_negative_mark && $response->submitted_option !== null && $response->submitted_option !== $correctOption) {
                    $response->is_correct = false;
                } else {
                    $response->is_correct = false;
                }

                $response->marks_obtained = $response->is_correct ? $points : 0;
                $response->save();
            }
        }

        $percentage = $totalMarks > 0 ? ($obtainedMarks / $totalMarks) * 100 : 0;

        $bookletScores = $this->calculateBookletScores($session);

        return [
            'percent' => max(0, $percentage),
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'booklet_scores' => $bookletScores,
        ];
    }

    public function calculateBookletScores(OnlineExamSession $session): array
    {
        $onlineExamDetail = $session->exam->onlineExamDetail ?? null;

        if (! $onlineExamDetail) {
            return [];
        }

        $booklets = $onlineExamDetail->booklets ?? [];

        if ($booklets->isEmpty()) {
            return [];
        }

        $responses = $session->responses;
        $scores = [];

        foreach ($booklets as $booklet) {
            $bookletTotal = 0;
            $bookletObtained = 0;

            foreach ($responses as $response) {
                if ($response->question_number < $booklet->from_question
                    || $response->question_number > $booklet->to_question) {
                    continue;
                }

                $answerKey = OnlineExamAnswerKey::where('exam_id', $session->exam_id)
                    ->where('question_number', $response->question_number)
                    ->first();

                $points = ($answerKey->weight ?? 1);
                $bookletTotal += $points;

                if ($answerKey && $answerKey->is_active) {
                    if ($response->submitted_option === $answerKey->correct_option) {
                        $bookletObtained += $points;
                    }
                }
            }

            $percent = $bookletTotal > 0 ? max(0, round(($bookletObtained / $bookletTotal) * 100, 2)) : 0;

            $scores[] = [
                'id' => $booklet->id,
                'title' => $booklet->title,
                'from_question' => $booklet->from_question,
                'to_question' => $booklet->to_question,
                'total_marks' => $bookletTotal,
                'obtained_marks' => $bookletObtained,
                'percent' => $percent,
            ];
        }

        return $scores;
    }

    public function recalculateAllSessions(OnlineExamDetail $onlineExamDetail): void
    {
        $sessions = OnlineExamSession::where('exam_id', $onlineExamDetail->exam_id)->get();

        foreach ($sessions as $session) {
            $scoreData = $this->calculateSessionScore($session->load(['responses', 'exam.onlineExamDetail.booklets']));

            $session->update([
                'percent' => $scoreData['percent'],
                'score' => $scoreData['obtained_marks'],
                'started_at' => $session->started_at,
                'submitted_at' => $session->submitted_at,
            ]);
        }
    }

    public function getRankings(OnlineExamDetail $onlineExamDetail): array
    {
        $sessions = OnlineExamSession::where('exam_id', $onlineExamDetail->exam_id)
            ->where('status', 'submitted')
            ->orWhere('status', 'graded')
            ->with('student')
            ->get();

        $ranked = $sessions->map(function ($session) {
            return [
                'student_id' => $session->student_id,
                'student_name' => $session->student->full_name ?? 'Unknown',
                'percent' => $session->percent,
                'score' => $session->score,
                'started_at' => $session->started_at,
                'ended_at' => $session->submitted_at,
                'status' => $session->status,
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
