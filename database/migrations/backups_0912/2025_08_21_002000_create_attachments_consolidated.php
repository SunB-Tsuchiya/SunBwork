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

            // Columns used by diary/event attachments (backup canonical schema)
            $table->unsignedBigInteger('diary_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();

            // Columns used by message/user attachments (current schema)
            // Keep nullable to avoid breaking either flow.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('filename')->nullable();
            $table->integer('status')->default(0);
            $table->bigInteger('size')->nullable();

            $table->timestamps();

            // Foreign keys where appropriate. Keep them simple and nullable to avoid
            // ordering problems during migrate:fresh when tables may not yet exist.
            // If FK creation fails in some environments, developers can add them later.
            try {
                if (Schema::hasTable('diaries')) {
                    $table->foreign('diary_id')->references('id')->on('diaries')->onDelete('cascade');
                }
                if (Schema::hasTable('events')) {
                    $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
                }
            } catch (\Throwable $e) {
                // Ignore FK creation errors during migration assembly; FKs can be added later.
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
