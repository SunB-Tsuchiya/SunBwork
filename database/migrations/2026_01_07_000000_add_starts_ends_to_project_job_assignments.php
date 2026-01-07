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
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('project_job_assignments', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (!Schema::hasColumn('project_job_assignments', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('project_job_assignments', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
            if (Schema::hasColumn('project_job_assignments', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
        });
    }
};
