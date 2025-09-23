<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * We add foreign key constraints for difficulty_id on both assignment tables.
     * The migration is defensive: if the column doesn't exist we skip, and we
     * wrap the alter in a try/catch to avoid failing on environments where the
     * constraint already exists.
     */
    public function up(): void
    {
        if (Schema::hasColumn('project_job_assignments', 'difficulty_id')) {
            try {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    $table->foreign('difficulty_id')->references('id')->on('difficulties')->onDelete('set null');
                });
            } catch (\Throwable $e) {
                // ignore - constraint may already exist or DB driver may differ
            }
        }

        if (Schema::hasColumn('project_job_assignment_by_myself', 'difficulty_id')) {
            try {
                Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                    $table->foreign('difficulty_id')->references('id')->on('difficulties')->onDelete('set null');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('project_job_assignments', 'difficulty_id')) {
            try {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    $table->dropForeign([$table->getTable() . '_difficulty_id_foreign']);
                });
            } catch (\Throwable $e) {
                try {
                    Schema::table('project_job_assignments', function (Blueprint $table) {
                        $table->dropForeign(['difficulty_id']);
                    });
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        if (Schema::hasColumn('project_job_assignment_by_myself', 'difficulty_id')) {
            try {
                Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                    $table->dropForeign([$table->getTable() . '_difficulty_id_foreign']);
                });
            } catch (\Throwable $e) {
                try {
                    Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
                        $table->dropForeign(['difficulty_id']);
                    });
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }
};
