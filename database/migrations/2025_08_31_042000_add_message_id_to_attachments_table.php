<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe add: only add the column if it doesn't exist (handles duplicate/merged migrations)
        if (!Schema::hasColumn('attachments', 'message_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->unsignedBigInteger('message_id')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attachments', 'message_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->dropColumn('message_id');
            });
        }
    }
};
