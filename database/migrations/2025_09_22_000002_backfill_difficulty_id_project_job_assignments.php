<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill project_job_assignments.difficulty_id from legacy difficulty string
     * by matching difficulties.name (and slug when available).
     *
     * This is idempotent: it only updates rows where difficulty_id IS NULL and difficulty IS NOT NULL.
     */
    public function up(): void
    {
        if (!Schema::hasTable('project_job_assignments') || !Schema::hasTable('difficulties')) {
            return;
        }

        // require both columns
        if (!Schema::hasColumn('project_job_assignments', 'difficulty_id') || !Schema::hasColumn('project_job_assignments', 'difficulty')) {
            return;
        }

        // If difficulties has slug, match by slug OR name; else match by name only.
        if (Schema::hasColumn('difficulties', 'slug')) {
            DB::statement(
                <<<'SQL'
                UPDATE project_job_assignments p
                JOIN difficulties d ON (d.slug = p.difficulty OR d.name = p.difficulty)
                SET p.difficulty_id = d.id
                WHERE p.difficulty_id IS NULL AND p.difficulty IS NOT NULL
            SQL
            );
        } else {
            DB::statement(
                <<<'SQL'
                UPDATE project_job_assignments p
                JOIN difficulties d ON (d.name = p.difficulty)
                SET p.difficulty_id = d.id
                WHERE p.difficulty_id IS NULL AND p.difficulty IS NOT NULL
            SQL
            );
        }
    }

    /**
     * Reverse the migrations.
     * We intentionally do not revert the backfill because original legacy strings remain
     * and reversing could clobber legitimate difficulty_id values set after this migration.
     */
    public function down(): void
    {
        // no-op
    }
};
