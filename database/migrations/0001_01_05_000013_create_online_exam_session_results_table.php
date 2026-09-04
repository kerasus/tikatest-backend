<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_exam_session_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('online_exam_session_id')
                ->constrained('online_exam_sessions')
                ->cascadeOnDelete();

            $table->foreignId('exam_id')
                ->constrained('exams')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('online_exam_booklet_id')
                ->nullable()
                ->constrained('online_exam_booklets')
                ->nullOnDelete();

            $table->foreignId('lesson_id')
                ->nullable()
                ->constrained('lessons')
                ->nullOnDelete();

            $table->string('lesson_title')->nullable();

            $table->enum('scope', ['exam', 'booklet'])->default('booklet');

            $table->decimal('raw_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('scaled_score', 8, 2)->nullable();
            $table->decimal('percent', 8, 2)->nullable();

            $table->unsignedInteger('question_count')->default(0);
            $table->unsignedInteger('answered_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('unanswered_count')->default(0);

            $table->decimal('z_score', 10, 4)->nullable();

            $table->timestamps();

            $table->index(['student_id', 'lesson_id']);
            $table->index(['exam_id', 'scope']);
            $table->index(['online_exam_session_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_session_results');
    }
};
