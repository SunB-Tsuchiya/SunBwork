<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('message_recipients')) {
            return;
        }

        Schema::table('message_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('message_recipients', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('message_id');
            }
            if (!Schema::hasColumn('message_recipients', 'type')) {
                $table->enum('type', ['to', 'cc', 'bcc'])->default('to')->after('user_id');
            }
            if (!Schema::hasColumn('message_recipients', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('type');
            }
            // add indexes for common queries
            if (!Schema::hasColumn('message_recipients', 'user_id')) {
                // index added above implicitly if column created; ensure index exists
                $table->index(['user_id', 'type']);
            } else {
                // ensure index exists if not
                try {
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                } catch (\Exception $e) {
                    // ignore
                }
            }
        });

        // Backfill user_id from legacy recipient_id if present and user_id is null
        try {
            if (Schema::hasColumn('message_recipients', 'recipient_id') && Schema::hasColumn('message_recipients', 'user_id')) {
                DB::table('message_recipients')
                    ->whereNull('user_id')
                    ->whereNotNull('recipient_id')
                    ->update(['user_id' => DB::raw('recipient_id')]);
            }
        } catch (\Exception $e) {
            // swallow backfill errors
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('message_recipients')) return;

        Schema::table('message_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('message_recipients', 'read_at')) {
                $table->dropColumn('read_at');
            }
            if (Schema::hasColumn('message_recipients', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('message_recipients', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};
