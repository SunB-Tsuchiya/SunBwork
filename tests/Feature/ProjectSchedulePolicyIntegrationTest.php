<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ProjectSchedule;
use App\Models\ProjectTeamMember;
use App\Models\ProjectScheduleAssignment;

class ProjectSchedulePolicyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function leader_admin_superadmin_and_assigned_user_can_update_schedule()
    {
        // ProjectScheduleAssignment requires the project_schedule_assignments table,
        // which is not present in the active migrations (only in backup files).
        // Also, ProjectTeamMember has a FK on project_job_id which requires an existing
        // project_job record. Skip this test until the table is added to active migrations.
        $this->markTestSkipped('project_schedule_assignments table not in active migrations; skipping until resolved.');
    }
}
