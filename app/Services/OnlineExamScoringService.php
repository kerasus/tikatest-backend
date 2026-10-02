<?php

namespace App\Services;

use App\Models\OnlineExamDetail;
use App\Models\OnlineExamSession;
use Illuminate\Support\Collection;
use App\Models\OnlineExamAnswerKey;
use App\Models\OnlineExamSessionResult;

class OnlineExamScoringService
{
    /**
     * تصحیح کامل یک نشست آزمون و ثبت خروجی در online_exam_session_results
     */
    public function calculateSessionScore(OnlineExamSession $session): array
    {
        // ۱. کش کردن کلیدها
        $answerKeys = ($session->relationLoaded('exam') && $session->exam?->relationLoaded('answerKeys'))
            ? $session->exam->answerKeys->where('is_active', true)->keyBy('question_number')
            : OnlineExamAnswerKey::where('exam_id', $session->exam_id)
                ->where('is_active', true)
                ->get()
                ->keyBy('question_number');

        $responses = $session->responses->keyBy('question_number');
        $exam = $session->exam;
        $booklets = $exam->onlineExamDetail?->booklets ?? collect();

        // ۲. تصحیح و ذخیره دقیق وضعیت و نمره تک‌تک پاسخ‌ها (با لحاظ نمره منفی در هر پاسخ)
        foreach ($session->responses as $response) {
            $key = $answerKeys->get($response->question_number);
            if (! $key) {
                continue;
            }

            $submitted = $response->submitted_option;
            $hasOption = ! empty($submitted);
            $isCorrect = $hasOption && ((string) $submitted === (string) $key->correct_option);
            $weight = (float) ($key->weight ?? 1.0);

            $marksObtained = 0.0;

            if ($hasOption) {
                if ($isCorrect) {
                    $marksObtained = $weight;
                } elseif ($key->has_negative_mark) {
                    $choices = max((int) ($key->number_of_choices ?? 4), 2);
                    $marksObtained = - ($weight / ($choices - 1));
                }
            }

            $response->is_correct = $isCorrect;
            $response->marks_obtained = round($marksObtained, 4);
            $response->save();
        }

        // ۳. پاک‌سازی نتایج قبلی این سشن
        OnlineExamSessionResult::where('online_exam_session_id', $session->id)->delete();

        // ۴. محاسبه و ذخیره نتایج به تفکیک دفترچه‌ها
        $bookletScores = [];
        if ($booklets->isNotEmpty()) {
            foreach ($booklets as $booklet) {
                $filteredKeys = $answerKeys->filter(fn ($k) =>
                    $k->question_number >= $booklet->from_question &&
                    $k->question_number <= $booklet->to_question
                );

                $metrics = $this->calculateMetrics($filteredKeys, $responses);

                $lessonId = $booklet->lesson_id ?? $exam->lesson_id;
                $lessonTitle = $booklet->lesson?->title ?? $exam->lesson?->title ?? $booklet->title;

                OnlineExamSessionResult::create([
                    'online_exam_session_id' => $session->id,
                    'exam_id'                => $exam->id,
                    'student_id'             => $session->student_id,
                    'online_exam_booklet_id' => $booklet->id,
                    'lesson_id'              => $lessonId,
                    'lesson_title'           => $lessonTitle,
                    'scope'                  => 'booklet',
                    'raw_score'              => $metrics['raw_score'],
                    'max_score'              => $metrics['max_score'],
                    'scaled_score'           => null,
                    'percent'                => $metrics['percent'],
                    'question_count'         => $metrics['question_count'],
                    'answered_count'         => $metrics['answered_count'],
                    'correct_count'          => $metrics['correct_count'],
                    'wrong_count'            => $metrics['wrong_count'],
                    'unanswered_count'       => $metrics['unanswered_count'],
                ]);

                $bookletScores[] = array_merge(['id' => $booklet->id, 'title' => $booklet->title], $metrics);
            }
        }

        // ۵. محاسبه و ذخیره نتیجه کل آزمون
        $totalMetrics = $this->calculateMetrics($answerKeys, $responses);

        OnlineExamSessionResult::create([
            'online_exam_session_id' => $session->id,
            'exam_id'                => $exam->id,
            'student_id'             => $session->student_id,
            'online_exam_booklet_id' => null,
            'lesson_id'              => $exam->lesson_id,
            'lesson_title'           => $exam->lesson?->title ?? $exam->name,
            'scope'                  => 'exam',
            'raw_score'              => $totalMetrics['raw_score'],
            'max_score'              => $totalMetrics['max_score'],
            'scaled_score'           => null,
            'percent'                => $totalMetrics['percent'],
            'question_count'         => $totalMetrics['question_count'],
            'answered_count'         => $totalMetrics['answered_count'],
            'correct_count'          => $totalMetrics['correct_count'],
            'wrong_count'            => $totalMetrics['wrong_count'],
            'unanswered_count'       => $totalMetrics['unanswered_count'],
        ]);

        return [
            'percent'          => $totalMetrics['percent'],
            'total_marks'      => $totalMetrics['max_score'],
            'obtained_marks'   => $totalMetrics['raw_score'],
            'booklet_scores'   => $bookletScores,
            'summary'          => $totalMetrics,
        ];
    }

    /**
     * فرمول دقیق نمره منفی و محاسبه آمار بر اساس سوالات انتخابی با ضرایب و گزینه‌های پویا
     */
    private function calculateMetrics(Collection $keys, Collection $responses): array
    {
        $questionCount = $keys->count();
        $correctCount = 0;
        $wrongCount = 0;
        $unansweredCount = 0;
        $rawScore = 0.0;
        $maxScore = 0.0;

        foreach ($keys as $key) {
            $weight = (float) ($key->weight ?? 1.0);
            $maxScore += $weight;

            $resp = $responses->get($key->question_number);
            $submitted = $resp?->submitted_option;

            if ($submitted === null || $submitted === '') {
                $unansweredCount++;
                continue;
            }

            if ((string) $submitted === (string) $key->correct_option) {
                $correctCount++;
                $rawScore += $weight;
            } else {
                $wrongCount++;
                if ($key->has_negative_mark) {
                    $choices = max((int) ($key->number_of_choices ?? 4), 2);
                    $rawScore -= ($weight / ($choices - 1));
                }
            }
        }

        $answeredCount = $correctCount + $wrongCount;
        $percent = $maxScore > 0 ? round(($rawScore / $maxScore) * 100, 2) : 0.0;

        return [
            'question_count'   => $questionCount,
            'answered_count'   => $answeredCount,
            'correct_count'    => $correctCount,
            'wrong_count'      => $wrongCount,
            'unanswered_count' => $unansweredCount,
            'raw_score'        => round($rawScore, 2),
            'max_score'        => round($maxScore, 2),
            'percent'          => $percent,
        ];
    }

    public function recalculateAllSessions(OnlineExamDetail $onlineExamDetail): void
    {
        $sessions = OnlineExamSession::where('exam_id', $onlineExamDetail->exam_id)
            ->with(['responses', 'exam.onlineExamDetail.booklets.lesson', 'exam.lesson'])
            ->get();

        foreach ($sessions as $session) {
            $scoreData = $this->calculateSessionScore($session);

            $session->update([
                'percent' => $scoreData['percent'],
                't_score' => $scoreData['obtained_marks'],
            ]);
        }
    }

    public function getRankings(OnlineExamDetail $onlineExamDetail): array
    {
        $sessions = OnlineExamSession::where('exam_id', $onlineExamDetail->exam_id)
            ->whereIn('status', ['submitted', 'graded'])
            ->with('student')
            ->get();

        return $sessions->map(function ($session) {
            return [
                'student_id'   => $session->student_id,
                'student_name' => $session->student->full_name ?? 'Unknown',
                'percent'      => $session->percent,
                't_score'      => $session->t_score,
                'started_at'   => $session->started_at,
                'ended_at'     => $session->submitted_at,
                'status'       => $session->status,
            ];
        })
            ->sortByDesc('percent')
            ->values()
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            })
            ->toArray();
    }
}
