<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('employee_code')->nullable();
            $table->unique('employee_code');
            $table->string('mobile')->unique();
            $table->string('email')->unique()->nullable();
            $table->string('national_id', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('mobile_verification_code')->nullable();
            $table->text('description')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('student_code', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('student_email', 255)->nullable();
            $table->string('student_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->text('additional_info')->nullable();
            $table->unsignedInteger('xp')->default(0)->nullable();
            $table->string('picture', 255)->nullable();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('father_name', 255)->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_email', 255)->nullable();
            $table->string('father_job', 255)->nullable();
            $table->string('father_national_id', 20)->nullable();
            $table->string('father_password', 255)->nullable();
            $table->string('mother_name', 255)->nullable();
            $table->string('mother_last_name', 255)->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_email', 255)->nullable();
            $table->string('mother_job', 255)->nullable();
            $table->string('mother_national_id', 20)->nullable();
            $table->string('mother_password', 255)->nullable();
            $table->timestamps();

            $table->index(['student_phone']);
            $table->index(['national_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
