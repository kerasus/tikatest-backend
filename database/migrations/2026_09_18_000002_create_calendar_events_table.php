<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('calendar_id')
                ->constrained('calendars')
                ->cascadeOnDelete();

            $table->string('title');

            $table->text('description')->nullable();

            $table->dateTime('starts_at');

            $table->dateTime('ends_at')->nullable();

            $table->boolean('all_day')->default(false);

            $table->string('type', 30)->default('general');

            $table->string('status', 20)->default('active');

            $table->string('location')->nullable();

            $table->string('color', 20)->nullable();

            $table->string('source', 30)->default('manual');

            $table->boolean('is_recurring')->default(false);

            $table->text('recurrence_rule')->nullable();

            $table->dateTime('recurrence_until')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['calendar_id', 'starts_at']);
            $table->index(['calendar_id', 'ends_at']);
            $table->index(['calendar_id', 'starts_at', 'ends_at']);
            $table->index(['type', 'starts_at']);
            $table->index(['source', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
