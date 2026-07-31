<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_academic_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'academic_level_id']);
            $table->index('academic_level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_academic_levels');
    }
};
