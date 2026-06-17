<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('parent_username');
            $table->string('username');
            $table->string('password');
            $table->string('sms_id')->nullable();
            $table->timestamps();

            $table->index('username');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registrations');
    }
};
