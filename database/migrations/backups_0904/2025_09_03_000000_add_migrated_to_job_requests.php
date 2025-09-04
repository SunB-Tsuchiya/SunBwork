<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('job_requests')) {
            return;
        }

        Schema::table('job_requests', function (Blueprint $table) {
            $table->boolean('migrated_to_messages')->default(false)->after('accepted_at');
            $table->unsignedBigInteger('migrated_message_id')->nullable()->after('migrated_to_messages');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('job_requests')) {
            return;
        }

        Schema::table('job_requests', function (Blueprint $table) {
            if (Schema::hasColumn('job_requests', 'migrated_to_messages')) {
                $table->dropColumn('migrated_to_messages');
            }
            if (Schema::hasColumn('job_requests', 'migrated_message_id')) {
                $table->dropColumn('migrated_message_id');
            }
        });
    }
};
