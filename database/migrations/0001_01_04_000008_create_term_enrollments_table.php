<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_enrollments', function (Blueprint $table) {
            $table->id();

            // دانش‌آموز (یوزر)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // کلاس مربوطه
            $table->foreignId('class_id')
                ->constrained('classes')
                ->cascadeOnDelete();

            // مدرسه مولفه (برای فیلتر/سرعت)
            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            // ترم مرتبط (null = عضویت عمومی غیرفصلی، مقداردار = ترم خاص)
            $table->foreignId('term_id')
                ->constrained('academic_terms')
                ->cascadeOnDelete();

            $table->datetime('enrolled_at')->nullable();
            $table->datetime('left_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'class_id']);
            $table->index('school_id');
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_enrollments');
    }
};
