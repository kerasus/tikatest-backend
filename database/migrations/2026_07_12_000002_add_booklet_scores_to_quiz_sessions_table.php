<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stores the per-booklet percentage for each quiz session so the
        // result of every booklet (دفترچه) can be shown separately.
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->json('booklet_scores')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('booklet_scores');
        });
    }
};
