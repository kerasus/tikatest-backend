<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Equivalent of the old `exam_category_questions` table: allows defining
        // multiple booklets (دفترچه) per quiz. A quiz may have zero booklets.
        Schema::create('quiz_booklets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('from_question');
            $table->unsignedInteger('to_question');
            $table->timestamps();

            $table->index(['quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_booklets');
    }
};
