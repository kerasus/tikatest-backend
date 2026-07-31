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
        Schema::create('online_exam_booklets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_exam_id')
                ->constrained('online_exam_details')
                ->cascadeOnDelete();

            $table->foreignId('lesson_id')
                ->nullable()
                ->constrained('lessons')
                ->nullOnDelete();

            $table->string('title');

            $table->unsignedInteger('from_question');
            $table->unsignedInteger('to_question');
            $table->json('booklet_scores')->nullable();
            $table->timestamps();

            $table->index(['online_exam_id']);
            $table->index(['lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_booklets');
    }
};
