<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skyroom_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();

            // شناسه بازگشتی اتاق از وب‌سرویس اسکای‌روم (createRoom)
            $table->unsignedBigInteger('skyroom_id')->unique()->nullable()->comment('شناسه اتاق در اسکای‌روم');

            $table->string('name')->comment('نام لاتین و یکتای اتاق در اسکای‌روم');
            $table->string('title')->comment('عنوان نمایشی اتاق/کلاس');
            $table->text('description')->nullable();

            // تنظیمات اختصاصی اسکای‌روم
            $table->unsignedSmallInteger('max_users')->default(20)->comment('سقف تعداد کاربر آنلاین');
            $table->boolean('guest_login')->default(false)->comment('امکان ورود میهمان');
            $table->boolean('op_login_first')->default(true)->comment('الزام ورود اپراتور قبل از سایرین');
            $table->boolean('status')->default(true)->comment('0: غیرفعال, 1: فعال');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skyroom_rooms');
    }
};
