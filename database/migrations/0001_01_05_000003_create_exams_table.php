<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();

            $table->string('name'); // نام آزمون (مثلا: ریاضی مستمر، یا کوئیز آنلاین فصل اول)
            $table->text('description')->nullable();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();

            $table->decimal('min_passing_score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2)->nullable();

            $table->enum('delivery_mode', ['online', 'in_person'])->default('in_person');
            $table->foreignId('exam_category_id')->constrained('exam_categories')->restrictOnDelete();

            // ترمی که این نگهداری (instance) آزمون در آن برگزار شده
            $table->foreignId('term_id')
                ->nullable()
                ->constrained('academic_terms')
                ->nullOnDelete();

            // چندمین بار برگزاری این آزمون در ترم است
            $table->unsignedInteger('occurrence')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('exam_category_id');
            $table->index('delivery_mode');
            $table->index('lesson_id');
            $table->index('created_at'); // برای سرعت در ORDER BY
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
