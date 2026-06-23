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
            $table->integer('time_limit'); // minutes
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('quiz_type', 50)->nullable();
            $table->json('content')->nullable();
            $table->json('solution')->nullable();
            /*
                {
                  "type": "image",
                  "path": "/uploads/exams/quiz1.png"
                }
                {
                  "type": "text",
                  "body": "<p>سوالات...</p>"
                }
             */
            $table->dateTime('show_answer_date')->nullable();
            $table->text('no_score_questions')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_answer_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->integer('question_number');
            $table->string('correct_option', 10);
            $table->decimal('weight', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['quiz_id', 'question_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answer_keys');
        Schema::dropIfExists('quizzes');
    }
};
