<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->string('attachment', 255)->nullable();
            $table->boolean('is_sms')->default(false);
            $table->string('message_type', 50)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'sender_id']);
        });

        Schema::create('message_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_student')->default(false);
            $table->boolean('is_father')->default(false);
            $table->boolean('is_mother')->default(false);
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_owners');
        Schema::dropIfExists('messages');
    }
};