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
        // 🛡️ تشخیص نقش دانش‌آموز به صورت کامپتیبل (هم با Spatie و هم فیلد مستقیم)
        $isStudent = $user && (
                (method_exists($user, 'hasRole') && $user->hasRole(UserRoleType::Student->value))
                || ($user->role ?? null) === UserRoleType::Student->value
            );

        $onlineDetail = $this->relationLoaded('onlineExamDetail') ? $this->onlineExamDetail : null;

        /*
         * اولویت بررسی دسترسی به پاسخ‌ها:
         * ۱. اگر کنترلر متغیر sensitive_data_available را ست کرده بود (کنترل دقیق سشن و زمان)
         * ۲. در غیر این صورت، بر اساس فیلد answers_visible_at
         */
        $canStudentSeeSolutions = false;
        if ($this->sensitive_data_available !== null) {
            $canStudentSeeSolutions = (bool) $this->sensitive_data_available;
        } elseif ($onlineDetail?->answers_visible_at) {
            $canStudentSeeSolutions = Carbon::now()->greaterThanOrEqualTo($onlineDetail->answers_visible_at);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'delivery_mode' => $this->delivery_mode,
            'min_passing_score' => $this->min_passing_score,
            'max_score' => $this->max_score,
            'occurrence' => $this->occurrence,
            'created_at' => $this->created_at,

            // فیلدهای وضعیت دانش‌آموز (ارسال‌شده از studentShow)
            'participation_status' => $this->whenNotNull($this->participation_status),
            'student_online_exam_session' => $this->whenNotNull($this->student_online_exam_session),
            'sensitive_data_available' => $this->when(
                $isStudent,
                fn () => (bool) ($this->sensitive_data_available ?? $canStudentSeeSolutions)
            ),

            // رابطه‌های عمومی
            'lesson' => $this->whenLoaded('lesson'),
            'category' => $this->whenLoaded('category'),
            'classes' => $this->whenLoaded('classes'),
            'academic_levels' => $this->whenLoaded('academicLevels'),
            'term' => $this->whenLoaded('term'),

            // 🎯 مدیریت امن جزئیات آزمون آنلاین
            'online_exam_detail' => $this->whenLoaded('onlineExamDetail', function () use ($onlineDetail, $isStudent, $canStudentSeeSolutions) {
                if (! $onlineDetail) {
                    return null;
                }

                // اطلاعات عمومی onlineExamDetail
                $payload = [
                    'id' => $onlineDetail->id,
                    'exam_id' => $onlineDetail->exam_id,
                    'starts_at' => $onlineDetail->starts_at,
                    'ends_at' => $onlineDetail->ends_at,
                    'visible_at' => $onlineDetail->visible_at,
                    'answers_visible_at' => $onlineDetail->answers_visible_at,
                    'time_limit_minutes' => $onlineDetail->time_limit_minutes,
                ];

                // نمایش فایل‌ها و متون پاسخ تشریحی فقط در صورت مجاز بودن
                if (! $isStudent || $canStudentSeeSolutions) {
                    $payload['solution'] = $onlineDetail->solution;
                    $payload['solution_file'] = $onlineDetail->solution_file;
                    $payload['solution_path'] = $onlineDetail->solution_path;
                    $payload['solution_descriptive'] = $onlineDetail->solution_descriptive;
                }

                // دفترچه‌ها (Booklets)
                if ($onlineDetail->relationLoaded('booklets')) {
                    $payload['booklets'] = $onlineDetail->booklets;
                }

                // کلید پاسخ‌ها (AnswerKeys) متصل به onlineExamDetail
                if ($onlineDetail->relationLoaded('answerKeys')) {
                    if (! $isStudent || $canStudentSeeSolutions) {
                        $payload['answer_keys'] = $onlineDetail->answerKeys;
                    } else {
                        // در حین آزمون، کلیدها بدون correct_option برگردانده می‌شوند (یا کلاً حذف می‌شوند)
                        $payload['answer_keys'] = $onlineDetail->answerKeys->map(fn ($key) => [
                            'id' => $key->id,
                            'exam_id' => $key->exam_id,
                            'question_number' => $key->question_number,
                            'number_of_choices' => $key->number_of_choices,
                            'weight' => $key->weight,
                            'has_negative_mark' => $key->has_negative_mark,
                            'is_active' => $key->is_active,
                        ]);
                    }
                }

                return $payload;
            }),

            'in_person_exam_detail' => $this->whenLoaded('inPersonExamDetail'),

            // نتایج آزمون حضوری: دانش‌آموز نباید لیست بقیه بچه‌ها رو ببینه
            'in_person_exam_results' => $this->when(
                ! $isStudent,
                fn () => $this->whenLoaded('inPersonExamResults')
            ),

            'created_by' => $this->when(! $isStudent, fn () => $this->whenLoaded('createdBy')),
        ];
    }
}
