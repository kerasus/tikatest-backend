<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['academic_level_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
