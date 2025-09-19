<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('project_job_assignments')) return;

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_job_assignments', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('accepted');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('project_job_assignments')) return;

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('project_job_assignments', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });
    }
};
