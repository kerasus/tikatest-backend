<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taraz_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('zaribe_z', 5, 2)->default(10);
            $table->decimal('sabet_eafzoodani', 5, 2)->default(50);
            $table->boolean('selected_model')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taraz_settings');
    }
};
