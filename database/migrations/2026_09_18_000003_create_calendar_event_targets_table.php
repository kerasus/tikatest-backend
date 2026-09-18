<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_targets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('calendar_event_id')
                ->constrained('calendar_events')
                ->cascadeOnDelete();

            $table->string('target_type');

            $table->unsignedBigInteger('target_id');

            $table->timestamps();

            $table->unique([
                'calendar_event_id',
                'target_type',
                'target_id',
            ],
                'cet_target_unique'
            );

            $table->index([
                'target_type',
                'target_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_targets');
    }
};
