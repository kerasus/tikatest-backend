<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_owner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('read_status')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->string('submission_file', 255)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'user_id']);
            $table->index(['user_id', 'read_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_owner');
    }
};