<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->json('content')->nullable();
            /*
                {
                  "type": "image",
                  "path": "/uploads/homeworks/quiz1.png"
                }
                {
                  "type": "pdf",
                  "path": "/uploads/homeworks/guide.pdf"
                }
                {
                  "type": "text",
                  "body": "<p>سؤالات...</p>"
                }
             */
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('homework_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_attachments');
    }
};
