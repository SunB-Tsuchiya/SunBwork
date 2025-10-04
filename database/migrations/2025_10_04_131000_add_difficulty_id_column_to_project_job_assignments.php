<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add canonical difficulty_id column and FK if not present. Defensive checks
        if (Schema::hasTable('project_job_assignments')) {
            if (! Schema::hasColumn('project_job_assignments', 'difficulty_id')) {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    $table->unsignedBigInteger('difficulty_id')->nullable()->after('difficulty')->index();
                });
            }

            // add FK constraint if difficulties table exists and FK not present
            if (Schema::hasTable('difficulties') && Schema::hasColumn('project_job_assignments', 'difficulty_id')) {
                try {
                    Schema::table('project_job_assignments', function (Blueprint $table) {
                        $table->foreign('difficulty_id')->references('id')->on('difficulties')->onDelete('set null');
                    });
                } catch (\Throwable $e) {
                    // ignore if FK already exists or DB driver doesn't support
                }
            }
        }

        if (Schema::hasTable('project_job_assignment_by_myself')) {
            if (! Schema::hasColumn('project_job_assignment_by_myself', 'difficulty_id')) {
                Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                    $table->unsignedBigInteger('difficulty_id')->nullable()->after('difficulty')->index();
                });
            }

            if (Schema::hasTable('difficulties') && Schema::hasColumn('project_job_assignment_by_myself', 'difficulty_id')) {
                try {
                    Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                        $table->foreign('difficulty_id')->references('id')->on('difficulties')->onDelete('set null');
                    });
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'difficulty_id')) {
            try {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    $table->dropForeign(['difficulty_id']);
                });
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    $table->dropColumn('difficulty_id');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (Schema::hasTable('project_job_assignment_by_myself') && Schema::hasColumn('project_job_assignment_by_myself', 'difficulty_id')) {
            try {
                Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                    $table->dropForeign(['difficulty_id']);
                });
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                    $table->dropColumn('difficulty_id');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
