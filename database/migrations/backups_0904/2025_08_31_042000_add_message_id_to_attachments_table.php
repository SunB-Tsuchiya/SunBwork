<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
