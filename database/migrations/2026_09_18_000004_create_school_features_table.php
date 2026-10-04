<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('feature_key')->comment('مثلاً: skyroom, sms_gateway, online_exam');
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable()->comment('تنظیمات اختصاصی فیچر برای این مدرسه در صورت نیاز');
            $table->timestamp('expires_at')->nullable()->comment('برای پلن‌های زمان‌دار/اشتراکی');
            $table->timestamps();

            $table->unique(['school_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_features');
    }
};
