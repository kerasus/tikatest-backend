<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exam_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->unique()->constrained('exams')->cascadeOnDelete();

            $table->dateTime('starts_at')->nullable(); // شروع بازه آنلاین
            $table->dateTime('ends_at')->nullable(); // پایان بازه آنلاین
            $table->unsignedInteger('time_limit_minutes')->nullable(); // مدت زمان پاسخگویی به دقیقه (مختص آنلاین)
            $table->dateTime('visible_at')->nullable(); // زمان انتشار آزمون برای دانش‌آموزان
            $table->dateTime('answers_visible_at')->nullable(); // زمان قابل مشاهده شدن پاسخنامه

            $table->json('content')->nullable();
            $table->json('solution')->nullable();
            /*
                {
                  "type": "image",
                  "path": "/uploads/exams/quiz1.png"
                }
                {
                  "type": "pdf",
                  "path": "/uploads/exams/quiz1.png"
                }
                {
                  "type": "text",
                  "body": "<p>سوالات...</p>"
                }
             */

            // تنظیمات دسترسی غایبین پس از پایان زمان آزمون
            $table->boolean('show_content_to_absentees_after_end')
                ->default(true)
                ->comment('آیا محتوای سؤالات پس از پایان آزمون برای دانش‌آموزانی که شرکت نکرده‌اند نمایش داده شود؟');

            $table->boolean('show_solution_to_absentees_after_end')
                ->default(true)
                ->comment('آیا پاسخ تشریحی پس از پایان آزمون برای دانش‌آموزانی که شرکت نکرده‌اند نمایش داده شود؟');

            $table->boolean('show_answer_key_to_absentees_after_end')
                ->default(true)
                ->comment('آیا پاسخ‌نامه کلیدی پس از پایان آزمون برای دانش‌آموزانی که شرکت نکرده‌اند نمایش داده شود؟');


            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index('visible_at'); // برای چک کردن اینکه آیا دانش‌آموز اجازه دیدن آزمون را دارد یا نه
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_details');
    }
};
