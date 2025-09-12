<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_job_assignments')) {
            return;
        }

        Schema::table('project_job_assignments', function (Blueprint $table) {
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
            // estimated_hours migration may already exist; guard
            if (!Schema::hasColumn('project_job_assignments', 'estimated_hours')) {
                $table->decimal('estimated_hours', 5, 2)->nullable()->after('desired_time');
            }
        });

        // Backfill date fields from existing timestamp columns if present
        try {
            if (Schema::hasColumn('project_job_assignments', 'desired_start')) {
                DB::table('project_job_assignments')
                    ->whereNotNull('desired_start')
                    ->update(['desired_start_date' => DB::raw('DATE(desired_start)')]);
            }
            if (Schema::hasColumn('project_job_assignments', 'desired_end')) {
                DB::table('project_job_assignments')
                    ->whereNotNull('desired_end')
                    ->update(['desired_end_date' => DB::raw('DATE(desired_end)')]);
            }
        } catch (\Exception $e) {
            // ignore backfill errors
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_job_assignments')) return;

        Schema::table('project_job_assignments', function (Blueprint $table) {
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
        });
    }
};
