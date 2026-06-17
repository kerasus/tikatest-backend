<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('name');
            $table->text('correct_answers');
            $table->time('timer')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->text('explanation')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('quiz_type', 50)->nullable();
            $table->text('question_url')->nullable();
            $table->text('answer_explanation')->nullable();
            $table->boolean('false_negative_grading')->default(false);
            $table->text('questions_text')->nullable();
            $table->text('answers_text')->nullable();
            $table->string('picture_id', 255)->nullable();
            $table->dateTime('show_answer_date')->nullable();
            $table->text('no_score_questions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
