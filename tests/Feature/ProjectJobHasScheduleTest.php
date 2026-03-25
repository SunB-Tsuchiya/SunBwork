<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ProjectJob;
use App\Models\ProjectSchedule;

class ProjectJobHasScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function show_inertia_includes_hasSchedule_when_schedules_exist()
    {
        // Create user and authenticate
        $user = User::factory()->create();

        // Create a client and a project job owned by user.
        // clients table columns: name, notes (detail/fromSB do not exist).
        // project_jobs table columns: jobcode, title, user_id, client_id, detail.
        $client = \App\Models\Client::create([
            'name' => 'Test Client',
        ]);
        $job = ProjectJob::create([
            'jobcode' => 'TEST-123',
            'title' => 'Test Job',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        // Add a schedule for this project job
        ProjectSchedule::factory()->create([
            'project_job_id' => $job->id,
        ]);

        // Disable coordinator middleware for test to bypass role checks
        $this->withoutMiddleware(\App\Http\Middleware\CoordinatorMiddleware::class);

        $response = $this->actingAs($user)
            ->get(route('coordinator.project_jobs.show', ['projectJob' => $job->id]));

        $response->assertOk();

        // Simple content check: Inertia JSON payload should include hasSchedule: true
        $response->assertSee('"hasSchedule":true');
    }
}
