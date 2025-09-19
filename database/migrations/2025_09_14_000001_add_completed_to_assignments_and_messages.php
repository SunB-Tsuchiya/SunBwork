<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_job_assignments', 'completed')) {
                $table->boolean('completed')->default(false)->after('assigned');
            }
        });

        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('job_assignment_messages', 'completed')) {
                $table->boolean('completed')->default(false)->after('scheduled');
            }
        });
    }

    public function down()
    {
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('project_job_assignments', 'completed')) {
                $table->dropColumn('completed');
            }
        });

        Schema::table('job_assignment_messages', function (Blueprint $table) {
            if (Schema::hasColumn('job_assignment_messages', 'completed')) {
                $table->dropColumn('completed');
            }
        });
    }
};
