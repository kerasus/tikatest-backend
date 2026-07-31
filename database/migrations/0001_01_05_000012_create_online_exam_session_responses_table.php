<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exam_session_responses', function (Blueprint $table) {
            $table->id();

            // اتصال به جلسه (Session) مربوطه
            $table->foreignId('online_exam_session_id')
                ->constrained('online_exam_sessions')
                ->cascadeOnDelete()
                ->name('fk_online_responses_session_id'); // نام کوتاه برای جلوگیری از خطای طولانی بودن نام در برخی دیتابیس‌ها

            // تکرار این دو فیلد برای کوئری‌های سریع گزارش‌گیری (Denormalization آگاهانه)
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // اطلاعات سوال و پاسخ
            $table->unsignedInteger('question_number'); // شماره سوال در آزمون
            $table->string('submitted_option', 10)->nullable(); // گزینه انتخاب شده (مثلا: 1 یا A)
            $table->text('answer_text')->nullable(); // برای سوالات تشریحی آنلاین اگر داشتی

            // نتایج تصحیح (می‌تواند توسط سیستم یا معلم پر شود)
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_obtained', 5, 2)->nullable(); // نمره کسب شده برای این سوال

            $table->timestamp('answered_at')->nullable(); // زمان دقیق پاسخ به این سوال
            $table->timestamps();

            // ۱. ایندکس جهت پیدا کردن سریع "آخرین پاسخ دانش‌آموز به یک سوال خاص"
            $table->index(
                ['online_exam_session_id', 'question_number', 'id'],
                'idx_session_question_latest'
            );

            // ۲. ایندکس جهت آنالیز و گزارش‌گیری روی سوالات
            $table->index(
                ['exam_id', 'question_number', 'is_correct'],
                'idx_exam_question_analytics'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_session_responses');
    }
};
