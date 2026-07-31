<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exam_sessions', function (Blueprint $table) {
            $table->id();

            // ارجاع به آزمون اصلی
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();

            // ارجاع به دانش‌آموز (User)
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            // وضعیت‌های بهبود یافته
            // ['not_started', 'in_progress', 'submitted', 'graded', 'expired']
            $table->enum('status', ['not_started', 'in_progress', 'submitted', 'graded', 'expired'])->default('not_started');

            // زمان‌بندی‌ها
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();

            // کل زمان مجاز (از تنظیمات آزمون کپی می‌شود تا اگر بعداً آزمون تغییر کرد، سابقه این دانش‌آموز ثابت بماند)
            $table->unsignedInteger('duration_limit_seconds')->nullable();
            // زمان صرف شده واقعی
            $table->unsignedInteger('time_used_seconds')->default(0);

            // نتایج نهایی (Cache شده برای سرعت در کارنامه)
            $table->decimal('score', 5, 2)->default(0);
            $table->decimal('percent', 5, 2)->default(0);

            // فیلدهای فنی
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);

            // برای قفل کردن آزمون (مثلاً توسط مراقب یا سیستم)
            $table->boolean('is_locked')->default(false);

            $table->timestamps();

            // جلوگیری از شرکت دوباره غیرمجاز
            $table->unique(['exam_id', 'student_id', 'attempt_number'], 'online_sess_student_attempt_unique');
            $table->index(['exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_sessions');
    }
};
