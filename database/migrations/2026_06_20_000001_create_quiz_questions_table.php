<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->integer('question_number')->default(1);
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'true_false', 'fill_blank', 'essay'])->default('multiple_choice');
            $table->decimal('points', 5, 2)->default(1);
            $table->boolean('has_negative_marking')->default(false);
            $table->decimal('negative_marks', 5, 2)->nullable();
            $table->text('question_image_url')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
