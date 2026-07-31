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
                  "type": "text",
                  "body": "<p>سوالات...</p>"
                }
             */

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
