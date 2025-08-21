<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            // store optional system prompt for conversation
            $table->text('system_prompt')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('role');
            $table->text('content');
            // optional meta JSON (file metadata etc.)
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // preset type: 'model' | 'instruction' | 'system'
            $table->string('type')->nullable();
            // optional system prompt preserved for backwards compat
            $table->text('system_prompt')->nullable();
            // seeder expects 'data' JSON for model/instruction/system payloads
            $table->json('data')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('ai_presets');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
