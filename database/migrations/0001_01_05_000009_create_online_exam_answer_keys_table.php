<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exam_answer_keys', function (Blueprint $table) {
            $table->id();

            // اتصال به جدول اصلی آزمون
            // نام foreignId را هم می‌توانیم برای شفافیت بیشتر به 'exam_id' بگذاریم.
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();

            // شماره سوال (مثلاً ۱، ۲، ۳...)
            $table->unsignedInteger('question_number');

            // تعداد گزینه‌های سوال
            $table->unsignedInteger('number_of_choices')->default(4);

            // گزینه صحیح
            $table->string('correct_option', 10);

            // ضریب یا وزن سوال
            $table->decimal('weight', 5, 2)->default(1.0);

            // آیا این سوال نمره منفی دارد؟ (پیش‌فرض: خیر)
            $table->boolean('has_negative_mark')->default(false);

            // آیا این سوال در حال حاضر در محاسبه نمره لحاظ شود؟
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // ایندکس ترکیبی یونیک برای جلوگیری از تکرار کلید برای یک سوال خاص در یک آزمون
            $table->unique(['exam_id', 'question_number']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_answer_keys');
    }
};
