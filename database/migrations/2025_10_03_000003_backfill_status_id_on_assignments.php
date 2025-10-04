<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Only proceed if statuses table exists and has the keys we need
        if (!Schema::hasTable('statuses')) {
            return;
        }

        $ids = DB::table('statuses')->whereIn('key', ['completed', 'scheduled', 'confirmed', 'received'])->pluck('id', 'key')->toArray();

        // Backfill project_job_assignments
        if (Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'status_id')) {
            try {
                // completed takes highest precedence
                if (isset($ids['completed'])) {
                    DB::table('project_job_assignments')->where('completed', true)->update(['status_id' => $ids['completed']]);
                }

                // scheduled (either boolean flag or scheduled_at present)
                if (isset($ids['scheduled'])) {
                    DB::table('project_job_assignments')
                        ->where(function ($q) {
                            $q->where('scheduled', true)->orWhereNotNull('scheduled_at');
                        })
                        ->whereNull('status_id')
                        ->update(['status_id' => $ids['scheduled']]);
                }

                // read_at -> confirmed
                if (isset($ids['confirmed'])) {
                    DB::table('project_job_assignments')
                        ->whereNotNull('read_at')
                        ->whereNull('status_id')
                        ->update(['status_id' => $ids['confirmed']]);
                }

                // accepted but not read -> received
                if (isset($ids['received'])) {
                    DB::table('project_job_assignments')
                        ->where('accepted', true)
                        ->whereNull('read_at')
                        ->whereNull('status_id')
                        ->update(['status_id' => $ids['received']]);
                }
            } catch (\Exception $e) {
                // non-fatal: don't block migrations if update fails
            }
        }

        // Backfill project_job_assignment_by_myself similarly
        if (Schema::hasTable('project_job_assignment_by_myself') && Schema::hasColumn('project_job_assignment_by_myself', 'status_id')) {
            try {
                if (isset($ids['completed'])) {
                    DB::table('project_job_assignment_by_myself')->where('completed', true)->update(['status_id' => $ids['completed']]);
                }

                if (isset($ids['scheduled'])) {
                    DB::table('project_job_assignment_by_myself')
                        ->where(function ($q) {
                            $q->where('scheduled', true)->orWhereNotNull('scheduled_at');
                        })
                        ->whereNull('status_id')
                        ->update(['status_id' => $ids['scheduled']]);
                }

                if (isset($ids['confirmed'])) {
                    DB::table('project_job_assignment_by_myself')
                        ->whereNotNull('read_at')
                        ->whereNull('status_id')
                        ->update(['status_id' => $ids['confirmed']]);
                }

                if (isset($ids['received'])) {
                    DB::table('project_job_assignment_by_myself')
                        ->where('accepted', true)
                        ->whereNull('read_at')
                        ->whereNull('status_id')
                        ->update(['status_id' => $ids['received']]);
                }
            } catch (\Exception $e) {
                // non-fatal
            }
        }
    }

    public function down()
    {
        // No destructive down; just null the status_id for these tables if present
        if (Schema::hasTable('project_job_assignment_by_myself') && Schema::hasColumn('project_job_assignment_by_myself', 'status_id')) {
            try {
                DB::table('project_job_assignment_by_myself')->update(['status_id' => null]);
            } catch (\Exception $e) {
            }
        }

        if (Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'status_id')) {
            try {
                DB::table('project_job_assignments')->update(['status_id' => null]);
            } catch (\Exception $e) {
            }
        }
    }
};
