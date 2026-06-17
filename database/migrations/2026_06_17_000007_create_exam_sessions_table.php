<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->date('gregorian_date');
            $table->string('persian_date', 20)->nullable();
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
            $table->decimal('min_grade', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lesson_id', 'class_id', 'gregorian_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
