<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_academic_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['homework_id', 'academic_level_id']);
            $table->index('academic_level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_academic_levels');
    }
};
