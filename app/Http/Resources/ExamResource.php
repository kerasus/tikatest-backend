<?php

namespace App\Http\Resources;

use App\Enums\UserRoleType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isStudent = $user && $user->role === UserRoleType::Student->value; // یا $user->hasRole(...) بر اساس پیاده‌سازی پروژه

        // بررسی اینکه آیا زمان انتشار پاسخنامه برای دانش‌آموز فرارسیده یا نه
        $answersVisibleAt = $this->onlineExamDetail?->answers_visible_at;
        $canStudentSeeSolutions = $answersVisibleAt && Carbon::now()->greaterThanOrEqualTo($answersVisibleAt);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'delivery_mode' => $this->delivery_mode,
            'min_passing_score' => $this->min_passing_score,
            'max_score' => $this->max_score,
            'occurrence' => $this->occurrence,
            'created_at' => $this->created_at,

            // رابطه‌ها
            'lesson' => $this->whenLoaded('lesson'),
            'category' => $this->whenLoaded('category'),
            'classes' => $this->whenLoaded('classes'),
            'academic_levels' => $this->whenLoaded('academicLevels'),
            'term' => $this->whenLoaded('term'),

            // اطلاعات جزییات آنلاین با ماسک کردن فایل پاسخ تشریحی در صورت نیاز
            'online_exam_detail' => $this->whenLoaded('onlineExamDetail', function () use ($isStudent, $canStudentSeeSolutions) {
                $detail = $this->onlineExamDetail->toArray();
                if ($isStudent && ! $canStudentSeeSolutions) {
                    unset($detail['solution']); // جلوگیری از لو رفتن پاسخ تشریحی قبل از زمان موعود
                }
                return $detail;
            }),

            'in_person_exam_detail' => $this->whenLoaded('inPersonExamDetail'),

            // ⚠️ پاسخ‌نامه‌ها (Answer Keys): فقط برای غیر دانش‌آموز، یا پس از زمان مجاز
            'answer_keys' => $this->when(
                ! $isStudent || $canStudentSeeSolutions,
                fn () => $this->whenLoaded('answerKeys')
            ),

            // نتایج آزمون حضوری: دانش‌آموز نباید لیست بقیه بچه‌ها رو ببینه
            'in_person_exam_results' => $this->when(
                ! $isStudent,
                fn () => $this->whenLoaded('inPersonExamResults')
            ),

            'created_by' => $this->when(! $isStudent, fn () => $this->whenLoaded('createdBy')),
        ];
    }
}
