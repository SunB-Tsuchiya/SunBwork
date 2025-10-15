<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // NOTE: This is a backup migration that adds the legacy `message_id` column
    // to the `attachments` table. The project is migrating to the polymorphic
    // `attachmentables` pivot table. Keep this file for history; do not rely on
    // it as part of the new migration flow. After full pivot migration and
    // verification, consider creating a safe migration to drop legacy columns.

    public function up()
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('attachments', 'message_id')) {
                $table->unsignedBigInteger('message_id')->nullable()->after('diary_id');
                $table->index('message_id');
                // Add FK if messages table exists
                if (Schema::hasTable('messages')) {
                    $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
                }
            }
        });
    }

    public function down()
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'message_id')) {
                // drop foreign if exists
                try {
                    $table->dropForeign(['message_id']);
                } catch (\Throwable $__e) {
                }
                $table->dropIndex(['message_id']);
                $table->dropColumn('message_id');
            }
        });
    }
};
