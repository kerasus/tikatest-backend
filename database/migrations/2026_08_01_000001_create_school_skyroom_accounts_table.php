<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_skyroom_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();

            $table->string('title')->comment('عنوان برای تمایز، مثلاً: اکانت اصلی دبستان یا اکانت دبیرستان');
            $table->string('username')->comment('نام کاربری مدیریت اسکای‌روم');
            $table->text('api_key')->comment('توکن اختصاصی API اسکای‌روم برای این اکانت');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_skyroom_accounts');
    }
};
