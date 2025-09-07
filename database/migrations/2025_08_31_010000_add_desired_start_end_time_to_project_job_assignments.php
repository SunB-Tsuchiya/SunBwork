<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        // Only attempt change if legacy timestamp columns still exist
        if (Schema::hasColumn('project_job_assignments', 'desired_start') || Schema::hasColumn('project_job_assignments', 'desired_end')) {
            try {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    if (Schema::hasColumn('project_job_assignments', 'desired_start')) {
                        $table->timestamp('desired_start')->nullable()->change();
                    }
                    if (Schema::hasColumn('project_job_assignments', 'desired_end')) {
                        $table->timestamp('desired_end')->nullable()->change();
                    }
                });
            } catch (\Exception $e) {
                // ignore change failures (safe for fresh installs)
            }
        }
    }

    public function down(): void
    {
        // revert not implemented
    }
};
