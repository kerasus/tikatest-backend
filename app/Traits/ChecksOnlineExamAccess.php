<?php

namespace App\Traits;

use App\Enums\UserRoleType;
use App\Models\Exam;
use App\Models\OnlineExamSession;
use App\Models\TermEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ChecksOnlineExamAccess
{
    private function checkStudentExamAccess(Exam $exam, User $student): void
    {
        if (! $student->hasRole(UserRoleType::Student->value)) {
            throw new HttpException(403, 'این عملیات فقط برای دانش‌آموزان مجاز است.');
        }

        $classIds = DB::table('exam_classes')->where('exam_id', $exam->id)->pluck('class_id');

        if ($classIds->isNotEmpty() && ! TermEnrollment::query()
            ->where('user_id', $student->id)
            ->where('term_id', $exam->term_id)
            ->whereIn('class_id', $classIds)
            ->active()
            ->exists()) {
            throw new HttpException(403, 'شما دسترسی لازم برای شرکت در این آزمون را ندارید.');
        }
    }

    private function checkExamVisibility(Exam $exam): void
    {
        if (! $exam->onlineExamDetail) {
            throw new HttpException(404, 'تنظیمات آزمون آنلاین برای این آزمون یافت نشد.');
        }

        if ($exam->onlineExamDetail->visible_at && now()->lt($exam->onlineExamDetail->visible_at)) {
            throw new HttpException(403, 'آزمون هنوز برای شما در دسترس و قابل مشاهده نیست.');
        }

        if ($exam->onlineExamDetail->starts_at && now()->lt($exam->onlineExamDetail->starts_at)) {
            throw new HttpException(409, 'زمان برگزاری آزمون هنوز فرا نرسیده است.');
        }
    }

    private function checkExamOpen(Exam $exam): void
    {
        $this->checkExamVisibility($exam);

        if ($exam->onlineExamDetail->ends_at && now()->gt($exam->onlineExamDetail->ends_at)) {
            throw new HttpException(409, 'مهلت برگزاری این آزمون به پایان رسیده است.');
        }
    }

    private function checkSessionOwner(OnlineExamSession $session, User $student): void
    {
        if ((int) $session->student_id !== (int) $student->id) {
            throw new HttpException(403, 'دسترسی غیرمجاز به این نشست.');
        }
    }

    private function examHasEnded(Exam $exam): bool
    {
        return $exam->onlineExamDetail?->ends_at && now()->gt($exam->onlineExamDetail->ends_at);
    }

    private function canShowSolutions(Exam $exam, ?OnlineExamSession $session): bool
    {
        $detail = $exam->onlineExamDetail;

        if ($detail?->answers_visible_at && now()->lt($detail->answers_visible_at)) {
            return false;
        }

        if ($detail?->ends_at) {
            return $this->examHasEnded($exam) && ($session === null || $session->status !== 'in_progress');
        }

        return $session && in_array($session->status, ['submitted', 'graded', 'expired'], true);
    }
}
