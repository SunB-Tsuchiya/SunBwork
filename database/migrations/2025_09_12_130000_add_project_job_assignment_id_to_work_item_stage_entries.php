<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'work_item_stage_entries';

        if (! Schema::hasTable($tableName)) {
            // nothing to do
            return;
        }

        // add nullable project_job_assignment_id column if missing
        if (! Schema::hasColumn($tableName, 'project_job_assignment_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unsignedBigInteger('project_job_assignment_id')->nullable()->after('work_item_id')->index();
                // add foreign key if assignments table exists
                if (Schema::hasTable('project_job_assignments')) {
                    $table->foreign('project_job_assignment_id')->references('id')->on('project_job_assignments')->nullOnDelete();
                }
            });
        }

        // Best-effort data migration: try several strategies to map existing work_item_id -> project_job_assignment_id
        if (Schema::hasColumn($tableName, 'work_item_id')) {
            $entries = DB::table($tableName)->whereNotNull('work_item_id')->get();
            foreach ($entries as $entry) {
                $mappedAssignmentId = null;

                // Strategy A: if work_items table exists and has project_job_assignment_id, use it
                if (Schema::hasTable('work_items')) {
                    $wi = DB::table('work_items')->where('id', $entry->work_item_id)->first();
                    if ($wi) {
                        if (isset($wi->project_job_assignment_id) && $wi->project_job_assignment_id) {
                            $mappedAssignmentId = $wi->project_job_assignment_id;
                        }
                    }
                }

                // Strategy B: if project_job_assignments has work_item_id FK, try to find assignment by work_item_id
                if (! $mappedAssignmentId && Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'work_item_id')) {
                    $ass = DB::table('project_job_assignments')->where('work_item_id', $entry->work_item_id)->first();
                    if ($ass) {
                        $mappedAssignmentId = $ass->id;
                    }
                }

                // Strategy C: if work_items has project_job_id and title, attempt to match by project_job_id + title
                if (! $mappedAssignmentId && Schema::hasTable('work_items')) {
                    $wi = $wi ?? DB::table('work_items')->where('id', $entry->work_item_id)->first();
                    if ($wi && isset($wi->project_job_id) && $wi->project_job_id) {
                        if (Schema::hasTable('project_job_assignments')) {
                            $ass = DB::table('project_job_assignments')
                                ->where('project_job_id', $wi->project_job_id)
                                ->where('title', $wi->title)
                                ->first();
                            if ($ass) $mappedAssignmentId = $ass->id;
                        }
                    }
                }

                if ($mappedAssignmentId) {
                    try {
                        DB::table($tableName)->where('id', $entry->id)->update(['project_job_assignment_id' => $mappedAssignmentId]);
                    } catch (\Exception $e) {
                        // best-effort: ignore individual update failures
                    }
                }
            }
        }

        // Note: we intentionally keep work_item_id column for backward compatibility.
    }

    public function down(): void
    {
        $tableName = 'work_item_stage_entries';
        if (! Schema::hasTable($tableName)) {
            return;
        }

        // remove column and FK if present (best-effort)
        try {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'project_job_assignment_id')) {
                    // attempt to drop foreign key first
                    try {
                        $table->dropForeign(['project_job_assignment_id']);
                    } catch (\Exception $e) {
                        // ignore if FK does not exist
                    }
                    $table->dropIndex([$tableName . '_project_job_assignment_id_index']);
                    $table->dropColumn('project_job_assignment_id');
                }
            });
        } catch (\Exception $e) {
            // ignore errors on down - best-effort rollback
        }
    }
};
