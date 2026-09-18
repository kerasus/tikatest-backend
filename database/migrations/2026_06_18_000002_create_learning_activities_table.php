<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('lesson_id')
                ->constrained('lessons')
                ->cascadeOnDelete();

            $table->foreignId('study_session_id')
                ->nullable()
                ->constrained('study_sessions')
                ->nullOnDelete();

            $table->string('type', 50);

            $table->dateTime('occurred_at');

            $table->unsignedInteger('duration_seconds')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'occurred_at']);
            $table->index(['student_id', 'lesson_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_activities');
    }
};
