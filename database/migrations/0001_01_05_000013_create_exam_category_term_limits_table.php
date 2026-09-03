<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_category_term_limits', function (Blueprint $table) {
            $table->id();

            // دسته‌بندی آزمون
            $table->foreignId('exam_category_id')
                ->constrained('exam_categories')
                ->cascadeOnDelete();

            // ترم (یا زیرترم) مرتبط
            $table->foreignId('term_id')
                ->constrained('academic_terms')
                ->cascadeOnDelete();

            // حداکثر تعداد بار برگزاری در این ترم/زیرترم
            // null = تعداد نامحدود ، 0 = غیرقابل برگزاری
            $table->unsignedInteger('max_occurrences')->nullable();

            $table->timestamps();

            $table->unique(['exam_category_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_category_term_limits');
    }
};
