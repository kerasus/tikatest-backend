<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_person_exam_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('in_person_exam_id')
                ->constrained('in_person_exam_details')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('raw_score', 7, 2);
            // برای محاسبه از 20 نمره
            $table->decimal('scaled_score', 7, 2);

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // برای محاسبه تراز نمره
            $table->decimal('z_score', 10, 4)->nullable();

            $table->timestamps();

            $table->unique(['in_person_exam_id', 'user_id']);
            $table->index('user_id');
            $table->index('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_person_exam_results');
    }
};
