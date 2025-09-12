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
            if (!Schema::hasColumn('project_job_assignments', 'estimated_hours')) {
                // decimal with 4,2 allows up to 99.99 hours; adjust precision if needed
                $table->decimal('estimated_hours', 5, 2)->nullable()->after('user_id')->comment('Estimated hours (fractional), e.g. 1.5 == 1 hour 30 minutes');
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
            if (Schema::hasColumn('project_job_assignments', 'estimated_hours')) {
                $table->dropColumn('estimated_hours');
            }
        });
    }
};
