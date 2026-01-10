<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Drop columns from canonical assignments table if they exist
        if (Schema::hasTable('project_job_assignments')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                    $table->dropColumn('desired_start_date');
                }
                if (Schema::hasColumn('project_job_assignments', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('project_job_assignments', 'starts_at')) {
                    $table->dropColumn('starts_at');
                }
                if (Schema::hasColumn('project_job_assignments', 'ends_at')) {
                    $table->dropColumn('ends_at');
                }
            });
        }

        // Drop columns from by-myself table if they exist
        if (Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                    $table->dropColumn('desired_start_date');
                }
                if (Schema::hasColumn('project_job_assignment_by_myself', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('project_job_assignment_by_myself', 'starts_at')) {
                    $table->dropColumn('starts_at');
                }
                if (Schema::hasColumn('project_job_assignment_by_myself', 'ends_at')) {
                    $table->dropColumn('ends_at');
                }
            });
        }
    }

    public function down()
    {
        // Reverse is intentionally left conservative: only add nullable columns
        if (Schema::hasTable('project_job_assignments')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                    $table->date('desired_start_date')->nullable();
                }
                if (!Schema::hasColumn('project_job_assignments', 'start_time')) {
                    $table->string('start_time')->nullable();
                }
                if (!Schema::hasColumn('project_job_assignments', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable();
                }
                if (!Schema::hasColumn('project_job_assignments', 'ends_at')) {
                    $table->timestamp('ends_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                if (!Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                    $table->date('desired_start_date')->nullable();
                }
                if (!Schema::hasColumn('project_job_assignment_by_myself', 'start_time')) {
                    $table->string('start_time')->nullable();
                }
                if (!Schema::hasColumn('project_job_assignment_by_myself', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable();
                }
                if (!Schema::hasColumn('project_job_assignment_by_myself', 'ends_at')) {
                    $table->timestamp('ends_at')->nullable();
                }
            });
        }
    }
};
