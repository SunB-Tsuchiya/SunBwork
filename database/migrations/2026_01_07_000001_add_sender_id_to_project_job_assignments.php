<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('project_job_assignments', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('ends_at');
                // add foreign key if users table exists
                if (Schema::hasTable('users')) {
                    $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
                }
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('project_job_assignments', 'sender_id')) {
                // drop foreign if exists
                try {
                    $table->dropForeign(['sender_id']);
                } catch (\Exception $e) {
                    // ignore
                }
                $table->dropColumn('sender_id');
            }
        });
    }
};
