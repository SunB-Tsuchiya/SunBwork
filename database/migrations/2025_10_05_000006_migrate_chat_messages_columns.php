<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // add chat_room_id/body/type/read_at if missing, copy existing data
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('chat_messages', 'chat_room_id')) {
                    $table->unsignedBigInteger('chat_room_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('chat_messages', 'body')) {
                    $table->text('body')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('chat_messages', 'type')) {
                    $table->string('type')->nullable()->after('body');
                }
                if (!Schema::hasColumn('chat_messages', 'read_at')) {
                    $table->timestamp('read_at')->nullable()->after('type');
                }
            });

            // Copy legacy columns into new ones where appropriate
            try {
                if (Schema::hasColumn('chat_messages', 'chat_id')) {
                    DB::statement('UPDATE chat_messages SET chat_room_id = chat_id WHERE chat_room_id IS NULL');
                }
                if (Schema::hasColumn('chat_messages', 'message')) {
                    DB::statement('UPDATE chat_messages SET body = message WHERE (body IS NULL OR body = "") AND message IS NOT NULL');
                }
            } catch (\Throwable $e) {
                // best-effort copy; log via stderr
                fwrite(STDERR, "Chat messages migration: copy failed: " . $e->getMessage() . PHP_EOL);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (Schema::hasColumn('chat_messages', 'chat_room_id')) {
                    $table->dropColumn('chat_room_id');
                }
                if (Schema::hasColumn('chat_messages', 'body')) {
                    $table->dropColumn('body');
                }
                if (Schema::hasColumn('chat_messages', 'type')) {
                    $table->dropColumn('type');
                }
                if (Schema::hasColumn('chat_messages', 'read_at')) {
                    $table->dropColumn('read_at');
                }
            });
        }
    }
};
