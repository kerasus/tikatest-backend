<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_lesson', function (Blueprint $table) {
            $table->id();

            // ارتباط با کلاس
            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();

            // ارتباط با درس
            $table->foreignId('lesson_id')
                ->constrained('lessons')
                ->cascadeOnDelete();

            // اگر بخوای برای یک درس در یک کلاس خاص ضریب یا معلم متفاوتی داشته باشی (اختیاری)
            // $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            // $table->decimal('coefficient', 5, 2)->nullable();

            $table->timestamps();

            // جلوگیری از انتساب تکراری یک درس به یک کلاس
            $table->unique(['class_id', 'lesson_id'], 'unique_class_lesson');

            // ایندکس جهت جستجوی سریع در زمان فیلتر دروس بر اساس کلاس
            $table->index(['class_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_lesson');
    }
};
