<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_session_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->integer('question_number');
            $table->string('submitted_option', 10)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_obtained', 5, 2)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_session_id', 'question_number']);
            $table->index(['quiz_id', 'user_id', 'quiz_session_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_session_responses');
    }
};
