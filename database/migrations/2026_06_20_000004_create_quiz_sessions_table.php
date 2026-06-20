<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'submitted', 'graded', 'expired'])->default('not_started');
            $table->dateTime('session_started_at')->nullable();
            $table->dateTime('session_ended_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('time_used_seconds')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->integer('attempt_number')->default(1);
            $table->text('submission_data')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id', 'attempt_number']);
            $table->index(['quiz_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_sessions');
    }
};
