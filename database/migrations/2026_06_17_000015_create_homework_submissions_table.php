<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('student_seen_at')->nullable(); // وقتی دانش‌آموز ارسال خود را مشاهده کرده است
            $table->dateTime('operator_seen_at')->nullable(); // وقتی معلم یا اپراتور ارسال دانش‌آموز را مشاهده کرده است
            $table->text('feedback')->nullable();
            $table->json('content')->nullable();
            /*
                {
                  "type": "image",
                  "path": "/uploads/homeworks/submission1.png"
                }
                {
                  "type": "pdf",
                  "path": "/uploads/homeworks/submission.pdf"
                }
                {
                  "type": "text",
                  "body": "<p>پاسخ‌های من...</p>"
                }
             */
            $table->timestamps();

            $table->unique(['homework_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
    }
};
