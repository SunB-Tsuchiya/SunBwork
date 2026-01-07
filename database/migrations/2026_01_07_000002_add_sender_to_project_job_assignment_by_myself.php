<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('project_job_assignment_by_myself')) {
            return;
        }

        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('start_time');
                if (Schema::hasTable('users')) {
                    $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
                }
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('project_job_assignment_by_myself')) {
            return;
        }

        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            if (Schema::hasColumn('project_job_assignment_by_myself', 'sender_id')) {
                try {
                    $table->dropForeign(['sender_id']);
                } catch (\Exception $e) {
                }
                $table->dropColumn('sender_id');
            }
        });
    }
};
