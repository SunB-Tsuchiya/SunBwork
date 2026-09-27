<?php

namespace Tests\Feature;

use App\Models\ProjectJob;
use App\Models\ProjectTeamMember;
use App\Models\Subcontractor;
use App\Models\User;
use App\Services\ProjectJobAssigneeOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectJobAssigneeOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_project_members_and_registered_subcontractors_are_returned(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $registeredGhost = User::factory()->create([
            'is_ghost' => true, 'ghost_owner_id' => $leader->id, 'ghost_expires_at' => now()->addDay(),
        ]);
        User::factory()->create([
            'is_ghost' => true, 'ghost_owner_id' => $leader->id, 'ghost_expires_at' => now()->addDay(),
        ]);
        $projectJob = ProjectJob::create(['title' => '銀本案件', 'user_id' => $leader->id]);
        ProjectTeamMember::create(['project_job_id' => $projectJob->id, 'user_id' => $member->id]);
        ProjectTeamMember::create(['project_job_id' => $projectJob->id, 'user_id' => $registeredGhost->id]);

        $registeredVendor = Subcontractor::create(['name' => '案件登録外注先']);
        $unregisteredVendor = Subcontractor::create(['name' => '案件未登録外注先']);
        $projectJob->subcontractors()->attach($registeredVendor->id, ['created_by' => $leader->id]);

        $options = app(ProjectJobAssigneeOptions::class)->for($projectJob, $leader);

        $this->assertEqualsCanonicalizing(
            [$leader->id, $member->id, $registeredGhost->id],
            $options['users']->pluck('id')->all()
        );
        $this->assertSame([$registeredVendor->id], $options['subcontractors']->pluck('id')->all());
        $this->assertFalse($options['subcontractors']->contains('id', $unregisteredVendor->id));
    }
}
