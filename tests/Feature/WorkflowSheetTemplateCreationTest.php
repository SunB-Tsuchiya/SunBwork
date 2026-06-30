<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ProgressTemplate;
use App\Models\ProjectJob;
use App\Models\User;
use App\Models\WorkflowSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSheetTemplateCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private ProjectJob $projectJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['user_role' => 'coordinator']);
        $client = Client::create(['name' => 'Template Test Client']);
        $this->projectJob = ProjectJob::create([
            'jobcode' => 'TPL-001',
            'title' => 'Template Test Job',
            'user_id' => $this->owner->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_owner_can_create_management_sheet_from_owned_progress_template(): void
    {
        $columnConfig = [
            ['key' => 'custom-column', 'label' => '独自工程', 'type' => 'worker'],
        ];
        $template = ProgressTemplate::create([
            'name' => '管理用テンプレート',
            'column_config' => $columnConfig,
            'sheet_type' => 'management',
            'created_by' => $this->owner->id,
            'is_shared' => false,
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('coordinator.project_jobs.workflow_sheets.store', $this->projectJob),
            ['name' => 'テンプレート適用シート', 'template_id' => $template->id],
        );

        $sheet = WorkflowSheet::where('name', 'テンプレート適用シート')->firstOrFail();

        $response->assertRedirect(route('coordinator.workflow_sheets.show', $sheet));
        $this->assertEquals($columnConfig, $sheet->column_config);
        $this->assertNull($sheet->template_id);
    }

    public function test_owner_can_create_management_sheet_with_default_columns(): void
    {
        $this->actingAs($this->owner)->post(
            route('coordinator.project_jobs.workflow_sheets.store', $this->projectJob),
            ['name' => 'デフォルトシート'],
        )->assertRedirect();

        $sheet = WorkflowSheet::where('name', 'デフォルトシート')->firstOrFail();

        $this->assertSame('初校', $sheet->column_config[0]['label']);
        $this->assertNull($sheet->template_id);
    }

    public function test_owner_cannot_use_another_users_private_template(): void
    {
        $otherUser = User::factory()->create(['user_role' => 'coordinator']);
        $template = ProgressTemplate::create([
            'name' => '非公開テンプレート',
            'column_config' => [['key' => 'private', 'label' => '非公開', 'type' => 'worker']],
            'created_by' => $otherUser->id,
            'is_shared' => false,
        ]);

        $this->actingAs($this->owner)->post(
            route('coordinator.project_jobs.workflow_sheets.store', $this->projectJob),
            ['name' => '作成不可', 'template_id' => $template->id],
        )->assertForbidden();

        $this->assertDatabaseMissing('workflow_sheets', ['name' => '作成不可']);
    }

    public function test_registering_management_sheet_as_template_saves_sheet_type(): void
    {
        $sheet = WorkflowSheet::create([
            'project_job_id' => $this->projectJob->id,
            'template_id' => null,
            'name' => '登録元シート',
            'stage_config' => ['stages' => []],
            'column_config' => [['key' => 'source', 'label' => '元列', 'type' => 'worker']],
            'created_by' => $this->owner->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->owner)->postJson(
            route('coordinator.workflow_sheets.register_template', $sheet),
            ['name' => '登録済み管理テンプレート', 'is_shared' => false],
        )->assertOk();

        $this->assertDatabaseHas('progress_templates', [
            'name' => '登録済み管理テンプレート',
            'sheet_type' => 'management',
            'created_by' => $this->owner->id,
        ]);
    }
}
