<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Attempt to drop common foreign key constraints by conventional names first.
        // This is defensive for MySQL which requires FK constraints to be removed before dropping columns.
        try {
            DB::statement('ALTER TABLE attachments DROP FOREIGN KEY attachments_diary_id_foreign');
        } catch (\Throwable $_) {
            // ignore if not present
        }
        try {
            DB::statement('ALTER TABLE attachments DROP FOREIGN KEY attachments_event_id_foreign');
        } catch (\Throwable $_) {
        }
        try {
            DB::statement('ALTER TABLE attachments DROP FOREIGN KEY attachments_message_id_foreign');
        } catch (\Throwable $_) {
        }
        try {
            DB::statement('ALTER TABLE attachments DROP FOREIGN KEY attachments_owner_id_foreign');
        } catch (\Throwable $_) {
        }

        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'owner_type')) $table->dropColumn('owner_type');
            if (Schema::hasColumn('attachments', 'owner_id')) $table->dropColumn('owner_id');
            if (Schema::hasColumn('attachments', 'diary_id')) $table->dropColumn('diary_id');
            if (Schema::hasColumn('attachments', 'event_id')) $table->dropColumn('event_id');
            if (Schema::hasColumn('attachments', 'message_id')) $table->dropColumn('message_id');
            if (Schema::hasColumn('attachments', 'filename')) $table->dropColumn('filename');
        });
    }

    public function down()
    {
        Schema::table('attachments', function (Blueprint $table) {
            // best-effort restore: add nullable columns
            if (! Schema::hasColumn('attachments', 'owner_type')) $table->string('owner_type')->nullable();
            if (! Schema::hasColumn('attachments', 'owner_id')) $table->unsignedBigInteger('owner_id')->nullable();
            if (! Schema::hasColumn('attachments', 'diary_id')) $table->unsignedBigInteger('diary_id')->nullable();
            if (! Schema::hasColumn('attachments', 'event_id')) $table->unsignedBigInteger('event_id')->nullable();
            if (! Schema::hasColumn('attachments', 'message_id')) $table->unsignedBigInteger('message_id')->nullable();
            if (! Schema::hasColumn('attachments', 'filename')) $table->string('filename')->nullable();
        });
    }
};
