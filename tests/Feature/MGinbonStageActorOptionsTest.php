<?php

namespace Tests\Feature;

use App\Models\MGinbon\MGinbonProject;
use App\Models\ProjectJob;
use App\Models\User;
use App\Services\MGinbon\MGinbonStageActorOptions;
use App\Services\ProjectJobAssigneeOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MGinbonStageActorOptionsTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'mginbon_actor_options_');
        config()->set('database.connections.mginbon', [
            'driver' => 'sqlite', 'database' => $this->databasePath, 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mginbon');
        Artisan::call('migrate', [
            '--database' => 'mginbon', '--path' => 'database/migrations/mginbon',
            '--realpath' => false, '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('mginbon');
        @unlink($this->databasePath);
        parent::tearDown();
    }

    public function test_unconfigured_stage_falls_back_to_all_project_members(): void
    {
        [$service, $project, $job, $actor] = $this->fixture();

        $options = $service->forStage($project, $job, $actor, 'initial_check');

        $this->assertFalse($options['restricted']);
        $this->assertSame([10, 20], $options['users']->pluck('id')->all());
        $this->assertSame([30], $options['subcontractors']->pluck('id')->all());
    }

    public function test_configured_stage_uses_only_active_links_that_belong_to_project(): void
    {
        [$service, $project, $job, $actor] = $this->fixture();
        $db = DB::connection('mginbon');
        $listId = $db->table('mginbon_value_lists')->insertGetId([
            'mginbon_project_id' => $project->id, 'code' => 'checker', 'name' => 'チェック担当者',
            'sort_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([
            ['value' => '社員', 'linked_user_id' => 20, 'is_active' => true],
            ['value' => '案件外社員', 'linked_user_id' => 99, 'is_active' => true],
            ['value' => '停止外注', 'linked_subcontractor_id' => 30, 'is_active' => false],
        ] as $index => $row) {
            $db->table('mginbon_value_list_items')->insert([
                'mginbon_value_list_id' => $listId, 'value' => $row['value'],
                'sort_order' => ($index + 1) * 10, 'is_active' => $row['is_active'],
                'linked_user_id' => $row['linked_user_id'] ?? null,
                'linked_subcontractor_id' => $row['linked_subcontractor_id'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $options = $service->forStage($project, $job, $actor, 'initial_check');

        $this->assertTrue($options['restricted']);
        $this->assertSame([20], $options['users']->pluck('id')->all());
        $this->assertSame('社員', $options['users']->first()['name']);
        $this->assertSame('B', $options['users']->first()['actual_name']);
        $this->assertEmpty($options['subcontractors']);
        $this->assertTrue($service->allows($project, $job, $actor, 'initial_check', 'user', 20));
        $this->assertFalse($service->allows($project, $job, $actor, 'initial_check', 'user', 10));
    }

    private function fixture(): array
    {
        $project = MGinbonProject::create(['project_job_id' => 1, 'year' => 2094, 'name' => '候補テスト', 'status' => 'draft']);
        $job = new ProjectJob();
        $job->id = 1;
        $actor = new User();
        $actor->id = 1;
        $base = [
            'users' => collect([['id' => 10, 'name' => 'A'], ['id' => 20, 'name' => 'B']]),
            'subcontractors' => collect([['id' => 30, 'name' => 'C']]),
        ];
        $projectOptions = Mockery::mock(ProjectJobAssigneeOptions::class);
        $projectOptions->shouldReceive('for')->andReturn($base);

        return [new MGinbonStageActorOptions($projectOptions), $project, $job, $actor];
    }
}
