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
            $table->foreignId('exam_session_id')->nullable()->constrained('exam_sessions')->nullOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->decimal('raw_grade', 5, 2)->nullable();
            $table->decimal('calculated_grade', 5, 2)->nullable();
            $table->decimal('min_grade', 5, 2)->nullable();
            $table->enum('grade_type', [
                'class_quiz',
                'monthly_quiz',
                'mid_term_1',
                'continuous_1',
                'final_1',
                'mid_term_2',
                'continuous_2',
                'final_2',
                'other'
            ]);
            $table->string('grade_name_for_other_type', 255)->nullable();
            $table->boolean('is_report_card')->default(false);
            $table->boolean('is_descriptive')->default(false);
            $table->integer('descriptive_value')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->decimal('z_score', 8, 4)->nullable();
            $table->date('grade_date');
            $table->text('explanation')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['student_id', 'lesson_id', 'grade_date']);
            $table->index(['class_id', 'grade_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};