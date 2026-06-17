<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_class_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('academic_levels')->nullOnDelete();
            $table->timestamps();

            $table->index(['quiz_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_class_assignments');
    }
};
