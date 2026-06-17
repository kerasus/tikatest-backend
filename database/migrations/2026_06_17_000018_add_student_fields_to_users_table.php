<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('melli_code', 20)->nullable()->after('email');
            $table->string('student_code', 50)->nullable()->after('melli_code');
            $table->date('birth_date')->nullable()->after('student_code');
            $table->string('student_email', 255)->nullable()->after('birth_date');
            $table->string('student_phone', 20)->nullable()->after('student_email');
            $table->text('address')->nullable()->after('student_phone');
            $table->text('additional_info')->nullable()->after('address');
            $table->unsignedInteger('xp')->default(0)->nullable()->after('additional_info');
            $table->string('picture', 255)->nullable()->after('xp');
            $table->foreignId('school_id')->nullable()->after('picture')->constrained('schools')->nullOnDelete();
            $table->string('father_name', 255)->nullable()->after('school_id');
            $table->string('father_phone', 20)->nullable()->after('father_name');
            $table->string('father_email', 255)->nullable()->after('father_phone');
            $table->string('father_job', 255)->nullable()->after('father_email');
            $table->string('father_melli_code', 20)->nullable()->after('father_job');
            $table->string('father_password', 255)->nullable()->after('father_melli_code');
            $table->string('mother_name', 255)->nullable()->after('father_password');
            $table->string('mother_lastname', 255)->nullable()->after('mother_name');
            $table->string('mother_phone', 20)->nullable()->after('mother_lastname');
            $table->string('mother_email', 255)->nullable()->after('mother_phone');
            $table->string('mother_job', 255)->nullable()->after('mother_email');
            $table->string('mother_melli_code', 20)->nullable()->after('mother_job');
            $table->string('mother_password', 255)->nullable()->after('mother_melli_code');

            $table->index(['school_id']);
            $table->index(['student_phone']);
            $table->index(['melli_code']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['school_id']);
            $table->dropIndex(['student_phone']);
            $table->dropIndex(['melli_code']);

            $table->dropColumn([
                'melli_code',
                'student_code',
                'birth_date',
                'student_email',
                'student_phone',
                'address',
                'additional_info',
                'xp',
                'picture',
                'school_id',
                'father_name',
                'father_phone',
                'father_email',
                'father_job',
                'father_melli_code',
                'father_password',
                'mother_name',
                'mother_lastname',
                'mother_phone',
                'mother_email',
                'mother_job',
                'mother_melli_code',
                'mother_password',
            ]);
        });
    }
};
