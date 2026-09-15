<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('slug', 50)->unique()->index();
            $table->string('name');
            $table->string('phone_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->enum('type', ['school', 'institute'])->default('school');
            $table->string('account_url', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
