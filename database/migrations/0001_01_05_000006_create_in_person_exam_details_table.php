<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_person_exam_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->unique()->constrained('exams')->cascadeOnDelete();

            // تاریخ و زمان برگزاری حضوری
            $table->date('held_at')->nullable();

            // آیا آزمون توصیفی است؟ (خیلی خوب، خوب، ...) یا عددی؟
            $table->boolean('is_descriptive')->default(false);

            // زمان نمایش نتایج به دانش‌آموزان (نتیجه پس از این زمان برای دانش‌آموز قابل مشاهده است)
            $table->timestamp('results_visible_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('held_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_person_exam_details');
    }
};
