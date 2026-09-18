<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->foreignId('term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->text('description')->nullable();

            $table->string('source', 30)->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'started_at']);
            $table->index(['student_id', 'lesson_id', 'started_at']);
            $table->index(['student_id', 'term_id', 'started_at']);
            $table->index(['lesson_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
