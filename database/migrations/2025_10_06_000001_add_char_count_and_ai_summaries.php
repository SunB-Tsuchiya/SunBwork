<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('ai_messages', 'char_count')) {
            Schema::table('ai_messages', function (Blueprint $table) {
                $table->integer('char_count')->default(0)->after('content');
            });
        }

        if (!Schema::hasTable('ai_summaries')) {
            Schema::create('ai_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
                $table->text('summary');
                $table->unsignedBigInteger('summarized_until_message_id')->nullable();
                $table->integer('char_count')->default(0);
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('ai_summaries')) {
            Schema::dropIfExists('ai_summaries');
        }
        if (Schema::hasColumn('ai_messages', 'char_count')) {
            Schema::table('ai_messages', function (Blueprint $table) {
                $table->dropColumn('char_count');
            });
        }
    }
};
