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
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('submission_text')->nullable();
            $table->string('submission_file', 255)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('student_seen_at')->nullable(); // وقتی دانش‌آموز ارسال خود را مشاهده کرده است
            $table->dateTime('operator_seen_at')->nullable(); // وقتی معلم یا اپراتور ارسال دانش‌آموز را مشاهده کرده است
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
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
            $table->index('school_id');
            $table->index('student_id');
            $table->index('graded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
    }
};
