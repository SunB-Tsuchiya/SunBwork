<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                if (!Schema::hasColumn('project_job_assignment_by_myself', 'start_time')) {
                    // desired_date does not exist in this table; place start_time after desired_start_date instead
                    $table->time('start_time')->nullable()->after('desired_start_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'start_time')) {
                    $table->dropColumn('start_time');
                }
            });
        }
    }
};
