<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skyroom_room_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skyroom_room_id')->constrained('skyroom_rooms')->cascadeOnDelete();

            $table->string('title')->nullable()->comment('عنوان جلسه، مثلاً: جلسه حل تمرین یا درس اصلی');

            // اگر به صورت هفتگی و تکرارشونده باشد (۰: شنبه، ۱: یکشنبه، ... ۶: جمعه)
            $table->unsignedTinyInteger('day_of_week')->nullable()->comment('0: Saturday ... 6: Friday');

            // اگر جلسه در یک تاریخ مشخص و موردی باشد
            $table->date('held_date')->nullable()->comment('تاریخ برگزاری در صورت موردی بودن');

            // ساعت شروع و پایان جلسه
            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['skyroom_room_id', 'day_of_week']);
            $table->index(['skyroom_room_id', 'held_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skyroom_room_schedules');
    }
};
