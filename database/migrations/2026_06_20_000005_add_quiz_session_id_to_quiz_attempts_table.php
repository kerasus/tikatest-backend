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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_attempts', 'quiz_session_id')) {
                $table->unsignedBigInteger('quiz_session_id')->nullable()->after('quiz_id');
                $table->foreign('quiz_session_id')
                    ->references('id')
                    ->on('quiz_sessions')
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeignIdFor('QuizSession');
            $table->dropColumn('quiz_session_id');
        });
    }
};
