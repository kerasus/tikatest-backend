<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('field_id')->constrained('academic_fields')->cascadeOnDelete();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['field_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_levels');
    }
};
