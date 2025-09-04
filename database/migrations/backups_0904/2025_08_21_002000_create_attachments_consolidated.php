<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('diary_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->foreign('diary_id')->references('id')->on('diaries')->onDelete('cascade');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });

        // diary_attachments removed - handled by attachments table
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
