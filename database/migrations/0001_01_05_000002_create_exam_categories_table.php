<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_categories', function (Blueprint $table) {
            $table->id();
            // بعضی categoryها عمومی و برای همه مدارس مشترک هستند و باید seed اولیه سراسری داشته باشند
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();

            $table->string('title'); // آزمون کلاسی، آزمون ماهانه، ...
            $table->unsignedTinyInteger('term_number')->nullable(); // 1, 2
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false); // اگر نخواهی بعضی موارد حذف شوند
            $table->timestamps();

            $table->unique(['school_id', 'title', 'term_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_categories');
    }
};
