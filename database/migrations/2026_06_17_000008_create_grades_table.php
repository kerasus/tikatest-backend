<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->decimal('raw_grade', 5, 2)->nullable();
            $table->decimal('calculated_grade', 5, 2)->nullable();
            $table->decimal('min_grade', 5, 2)->nullable();
            $table->string('grade_type', 50);
            $table->string('grade_name_for_other_type', 255)->nullable();
            $table->boolean('is_report_card')->default(false);
            $table->boolean('is_descriptive')->default(false);
            $table->string('descriptive_value', 255)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->decimal('z_score', 8, 4)->nullable();
            $table->date('gregorian_date');
            $table->string('persian_date', 20)->nullable();
            $table->text('explanation')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['student_id', 'lesson_id', 'exam_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
