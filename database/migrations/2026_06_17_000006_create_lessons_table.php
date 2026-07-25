<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('name');
            $table->foreignId('field_id')->nullable()->constrained('academic_fields')->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('academic_levels')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->integer('order')->default(0);
            $table->decimal('coefficient', 5, 2)->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'field_id']);
            $table->index(['school_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
