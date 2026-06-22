<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_anti_cheat_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->json('event_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['quiz_session_id']);
            $table->index(['student_id']);
            $table->index(['event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_anti_cheat_events');
    }
};
