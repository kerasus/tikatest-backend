<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('url')->nullable();
            $table->string('keyword')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index('provider');
            $table->index('name');
        });

        Schema::create('place_tag', function (Blueprint $table) {
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['place_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_tag');
        Schema::dropIfExists('places');
    }
};
