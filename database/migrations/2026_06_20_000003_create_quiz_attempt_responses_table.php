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
            $table->foreignId('user_id')->constrained();
            $table->foreignId('quiz_id')->constrained();
            $table->integer('question_number');
            $table->string('submitted_option', 10)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'user_id', 'quiz_attempt_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_responses');
    }
};
