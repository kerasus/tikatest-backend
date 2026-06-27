<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->foreignId('quiz_question_option_id')->nullable()->constrained('quiz_question_options')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('quiz_id')->constrained();
            $table->text('answer_text')->nullable();
            $table->integer('question_number');
            $table->string('submitted_option', 10)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_obtained', 5, 2)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'user_id', 'quiz_attempt_id', 'quiz_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_responses');
    }
};
