<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_fields');
    }
};
