<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();

            // ترم متعلق به کدام مدرسه/آموزشگاه است
            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            // نام ترم: مثلاً "سال تحصیلی 1404-1405"، "ترم اول"، "بهار"
            $table->string('name');

            // نوع ترم
            $table->enum('type', ['school_year', 'seasonal', 'sub_term'])
                ->default('sub_term');

            // سال تحصیلی (برای مدارس): مثال 1404-1405
            $table->string('academic_year', 9)->nullable();

            // فصل (برای آموزشگاه‌ها): spring / fall / summer
            $table->string('season', 20)->nullable();

            // شماره/ترتیب زیرترم داخل یک ترم والد
            $table->unsignedTinyInteger('period')->nullable();

            // بازهٔ زمانی ترم
            $table->datetime('starts_at')->nullable();
            $table->datetime('ends_at')->nullable();

            // ترم فعال است؟
            $table->boolean('is_active')->default(false);

            // ارجاع به ترم والد (برای ساختار درختی ترم/زیرترم)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('academic_terms')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
