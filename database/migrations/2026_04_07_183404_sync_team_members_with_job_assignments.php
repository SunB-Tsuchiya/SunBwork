<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Sync project_team_members with project_job_assignments.
     * For each team member without a corresponding assignment, create one.
     */
    public function up(): void
    {
        $members = \Illuminate\Support\Facades\DB::table('project_team_members')
            ->select('project_job_id', 'user_id')
            ->distinct()
            ->orderBy('project_job_id')
            ->orderBy('user_id')
            ->get();

        foreach ($members as $member) {
            $exists = \Illuminate\Support\Facades\DB::table('project_job_assignments')
                ->where('project_job_id', $member->project_job_id)
                ->where('user_id', $member->user_id)
                ->exists();
            if (!$exists) {
                \Illuminate\Support\Facades\DB::table('project_job_assignments')->insert([
                    'project_job_id' => $member->project_job_id,
                    'user_id'        => $member->user_id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do not delete assignments created by this migration
        // as we cannot reliably distinguish them from manually created ones
    }
};
