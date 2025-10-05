<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (Schema::hasColumn('chat_messages', 'chat_id')) {
                    $table->unsignedBigInteger('chat_id')->nullable()->change();
                }
                if (Schema::hasColumn('chat_messages', 'message')) {
                    $table->text('message')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (Schema::hasColumn('chat_messages', 'chat_id')) {
                    $table->unsignedBigInteger('chat_id')->nullable(false)->change();
                }
                if (Schema::hasColumn('chat_messages', 'message')) {
                    $table->text('message')->nullable(false)->change();
                }
            });
        }
    }
};
