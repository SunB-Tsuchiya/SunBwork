<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dateTime('scheduled_at')->nullable()->after('desired_at');
            });
        }
        if (!Schema::hasColumn('project_job_assignments', 'scheduled')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->boolean('scheduled')->default(false)->after('scheduled_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('project_job_assignments', 'scheduled')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dropColumn('scheduled');
            });
        }
        if (Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->dropColumn('scheduled_at');
            });
        }
    }
};
