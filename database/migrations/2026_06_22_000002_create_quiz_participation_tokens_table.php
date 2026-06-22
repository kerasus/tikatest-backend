<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_participation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index(['token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_participation_tokens');
    }
};
