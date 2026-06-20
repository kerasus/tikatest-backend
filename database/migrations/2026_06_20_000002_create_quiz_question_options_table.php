<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->integer('option_number')->default(1);
            $table->text('option_text');
            $table->text('option_image_url')->nullable();
            $table->boolean('is_correct_answer')->default(false);
            $table->timestamps();

            $table->unique(['quiz_question_id', 'option_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_question_options');
    }
};
