<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('work_items') && ! Schema::hasColumn('work_items', 'project_job_assignment_id')) {
            Schema::table('work_items', function (Blueprint $table) {
                $table->unsignedBigInteger('project_job_assignment_id')->nullable()->after('id');
                // keep FK optional to avoid migration ordering issues in existing DBs
                $table->foreign('project_job_assignment_id')->references('id')->on('project_job_assignments')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('work_items') && Schema::hasColumn('work_items', 'project_job_assignment_id')) {
            Schema::table('work_items', function (Blueprint $table) {
                $table->dropForeign(['project_job_assignment_id']);
                $table->dropColumn('project_job_assignment_id');
            });
        }
    }
};
