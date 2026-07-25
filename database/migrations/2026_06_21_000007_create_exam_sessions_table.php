<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    // alternate `resultOfDars`
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->date('exam_date');
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
            $table->boolean('is_descriptive')->default(false);
            $table->boolean('is_report_card')->default(false);
            $table->foreignId('quiz_session_id')->nullable()->constrained('quiz_sessions')->nullOnDelete();
            $table->decimal('min_passing_score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lesson_id', 'class_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
