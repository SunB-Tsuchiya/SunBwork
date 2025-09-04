<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // Accept both legacy/backup column names to be tolerant during migrate:fresh
            // Some code/seeders reference `from_user_id`, others `sender_id`.
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();

            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('draft'); // draft, sent
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index('from_user_id');
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
