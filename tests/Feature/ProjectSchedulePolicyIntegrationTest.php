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
        // Create users
        $super = User::factory()->create(['user_role' => 'superadmin']);
        $admin = User::factory()->create(['user_role' => 'admin']);
        $leader = User::factory()->create(['user_role' => 'leader']);
        $coord = User::factory()->create(['user_role' => 'coordinator']);
        $user = User::factory()->create(['user_role' => 'user']);

        // Create a project job id and schedule
        $schedule = ProjectSchedule::factory()->create();

        // Assign project_team_member (user) to schedule's project_job
        ProjectTeamMember::factory()->create(['project_job_id' => $schedule->project_job_id, 'user_id' => $user->id]);

        // Also assign a schedule assignment to user
        ProjectScheduleAssignment::factory()->create(['project_schedule_id' => $schedule->id, 'user_id' => $user->id]);

        // Now assert each role can update via policy (simulate via actingAs and PATCH endpoint)
        $payload = ['start_date' => now()->toDateString(), 'end_date' => now()->addDays(1)->toDateString()];

        foreach ([$super, $admin, $leader, $coord, $user] as $u) {
            $this->actingAs($u)
                ->patchJson(route('coordinator.project_schedules.calendar.update', ['project_schedule' => $schedule->id]), $payload)
                ->assertStatus(200);
        }
    }
}
