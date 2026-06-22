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
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
