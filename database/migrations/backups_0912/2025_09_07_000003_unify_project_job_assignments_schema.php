<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This migration attempts to unify project_job_assignments schema across
     * environments. It will:
     *  - ensure canonical columns exist: title, detail, difficulty,
     *    desired_start_date (date), desired_end_date (date), desired_time (time),
     *    estimated_hours (decimal), assigned (bool), accepted (bool)
     *  - backfill date fields from legacy timestamp columns (desired_start / desired_end)
     *  - optionally drop legacy columns if env UNIFY_DROP_OLD_COLUMNS=true
     *
     * Run notes:
     *  - Run php artisan migrate to apply.
     *  - To remove legacy columns after verifying backfill, set
     *    UNIFY_DROP_OLD_COLUMNS=true in your environment and run this migration
     *    (or a follow-up migration) again.
     */
    public function up(): void
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            // canonical fields
            if (!Schema::hasColumn('project_job_assignments', 'title')) {
                $table->string('title')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('project_job_assignments', 'detail')) {
                $table->text('detail')->nullable()->after('title');
            }
            if (!Schema::hasColumn('project_job_assignments', 'difficulty')) {
                $table->string('difficulty')->default('normal')->after('detail');
            }
            if (!Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                $table->date('desired_start_date')->nullable()->after('difficulty');
            }
            if (!Schema::hasColumn('project_job_assignments', 'desired_end_date')) {
                $table->date('desired_end_date')->nullable()->after('desired_start_date');
            }
            if (!Schema::hasColumn('project_job_assignments', 'desired_time')) {
                $table->time('desired_time')->nullable()->after('desired_end_date');
            }
            if (!Schema::hasColumn('project_job_assignments', 'estimated_hours')) {
                $table->decimal('estimated_hours', 6, 2)->nullable()->after('desired_time');
            }
            if (!Schema::hasColumn('project_job_assignments', 'assigned')) {
                $table->boolean('assigned')->default(false)->after('estimated_hours');
            }
            if (!Schema::hasColumn('project_job_assignments', 'accepted')) {
                $table->boolean('accepted')->default(false)->after('assigned');
            }
        });

        // Backfill date fields from legacy timestamp columns if present
        try {
            if (Schema::hasColumn('project_job_assignments', 'desired_start') && Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                DB::table('project_job_assignments')
                    ->whereNotNull('desired_start')
                    ->update(['desired_start_date' => DB::raw('DATE(desired_start)')]);
            }
            if (Schema::hasColumn('project_job_assignments', 'desired_end') && Schema::hasColumn('project_job_assignments', 'desired_end_date')) {
                DB::table('project_job_assignments')
                    ->whereNotNull('desired_end')
                    ->update(['desired_end_date' => DB::raw('DATE(desired_end)')]);
            }

            // If legacy columns like 'name' exist, try to backfill title
            if (Schema::hasColumn('project_job_assignments', 'name') && Schema::hasColumn('project_job_assignments', 'title')) {
                DB::table('project_job_assignments')
                    ->whereNull('title')
                    ->update(['title' => DB::raw('name')]);
            }
        } catch (\Exception $e) {
            // Log if you want; avoid failing the migration on backfill issues
        }

        // Optionally drop legacy columns only when explicitly enabled via env
        $dropOld = env('UNIFY_DROP_OLD_COLUMNS', false);
        if ($dropOld) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                // common legacy columns — only drop if present
                if (Schema::hasColumn('project_job_assignments', 'desired_start')) {
                    $table->dropColumn('desired_start');
                }
                if (Schema::hasColumn('project_job_assignments', 'desired_end')) {
                    $table->dropColumn('desired_end');
                }
                if (Schema::hasColumn('project_job_assignments', 'name')) {
                    $table->dropColumn('name');
                }
                // other legacy columns may be dropped here as needed
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
            // Do not attempt to recreate dropped legacy columns automatically.
            // Remove canonical columns only if they exist.
            if (Schema::hasColumn('project_job_assignments', 'estimated_hours')) {
                $table->dropColumn('estimated_hours');
            }
            if (Schema::hasColumn('project_job_assignments', 'desired_time')) {
                $table->dropColumn('desired_time');
            }
            if (Schema::hasColumn('project_job_assignments', 'desired_end_date')) {
                $table->dropColumn('desired_end_date');
            }
            if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                $table->dropColumn('desired_start_date');
            }
            if (Schema::hasColumn('project_job_assignments', 'difficulty')) {
                $table->dropColumn('difficulty');
            }
            if (Schema::hasColumn('project_job_assignments', 'detail')) {
                $table->dropColumn('detail');
            }
            if (Schema::hasColumn('project_job_assignments', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('project_job_assignments', 'assigned')) {
                $table->dropColumn('assigned');
            }
            if (Schema::hasColumn('project_job_assignments', 'accepted')) {
                $table->dropColumn('accepted');
            }
        });
    }
};
