<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('message_recipients')) return;

        // Use raw ALTER to avoid requiring doctrine/dbal for change()
        try {
            // Make recipient_id nullable
            if (Schema::hasColumn('message_recipients', 'recipient_id')) {
                DB::statement("ALTER TABLE `message_recipients` MODIFY `recipient_id` BIGINT UNSIGNED NULL;");
            }
        } catch (\Exception $e) {
            // swallow to avoid blocking migrations; log if you want
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('message_recipients')) return;

        try {
            if (Schema::hasColumn('message_recipients', 'recipient_id')) {
                // revert to NOT NULL (no default) — ensure app handles not-null when reverting
                DB::statement("ALTER TABLE `message_recipients` MODIFY `recipient_id` BIGINT UNSIGNED NOT NULL;");
            }
        } catch (\Exception $e) {
            // ignore
        }
    }
};
