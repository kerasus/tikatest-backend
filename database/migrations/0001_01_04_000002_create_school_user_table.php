<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('school_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('personnel_code', 50)->nullable(); // کد پرسنلی اختصاصی این مدرسه
            $table->boolean('is_active')->default(true); // برای فعال/غیرفعال کردن دسترسی به مدرسه خاص
            $table->date('joined_at')->nullable(); // تاریخ شروع همکاری
            $table->date('left_at')->nullable();   // تاریخ پایان همکاری

            $table->timestamps();

            // جلوگیری از ثبت تکراری یک کاربر در یک مدرسه
            $table->unique(['school_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_user');
    }
};
