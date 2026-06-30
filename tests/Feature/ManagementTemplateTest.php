<?php

namespace Tests\Feature;

use App\Models\ProgressTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::factory()->create(['user_role' => 'coordinator']);
    }

    public function test_index_lists_accessible_management_templates_only(): void
    {
        $other = User::factory()->create(['user_role' => 'coordinator']);

        $owned = $this->template(['name' => '本人用', 'created_by' => $this->coordinator->id]);
        $shared = $this->template(['name' => '共有用', 'created_by' => $other->id, 'is_shared' => true]);
        $this->template(['name' => '他人の非公開', 'created_by' => $other->id]);
        $this->template(['name' => '進行管理用', 'created_by' => $this->coordinator->id, 'sheet_type' => 'progress']);

        $this->actingAs($this->coordinator)
            ->get(route('coordinator.management_templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Coordinator/ManagementTemplates/Index', false)
                ->has('templates', 2)
                ->where('templates', function ($templates) use ($owned, $shared) {
                    return collect($templates)->pluck('id')->sort()->values()->all()
                        === collect([$owned->id, $shared->id])->sort()->values()->all();
                }));
    }

    public function test_coordinator_can_create_update_and_delete_management_template(): void
    {
        $columns = [['key' => 'first', 'label' => '初校', 'type' => 'worker']];

        $this->actingAs($this->coordinator)->post(
            route('coordinator.management_templates.store'),
            [
                'name' => '標準管理表',
                'description' => '標準構成',
                'column_config' => $columns,
                'is_shared' => true,
            ],
        )->assertRedirect(route('coordinator.management_templates.index'));

        $template = ProgressTemplate::where('name', '標準管理表')->firstOrFail();
        $this->assertSame('management', $template->sheet_type);
        $this->assertEquals($columns, $template->column_config);
        $this->assertSame([], $template->row_config);

        $updatedColumns = [['key' => 'proof', 'label' => '校正', 'type' => 'proof_v2']];
        $this->actingAs($this->coordinator)->put(
            route('coordinator.management_templates.update', ['template' => $template->id]),
            [
                'name' => '更新済み管理表',
                'description' => null,
                'column_config' => $updatedColumns,
                'is_shared' => false,
            ],
        )->assertRedirect();

        $template->refresh();
        $this->assertSame('更新済み管理表', $template->name);
        $this->assertEquals($updatedColumns, $template->column_config);

        $this->actingAs($this->coordinator)->delete(
            route('coordinator.management_templates.destroy', ['template' => $template->id]),
        )->assertRedirect(route('coordinator.management_templates.index'));

        $this->assertDatabaseMissing('progress_templates', ['id' => $template->id]);
    }

    public function test_coordinator_cannot_edit_another_users_template(): void
    {
        $other = User::factory()->create(['user_role' => 'coordinator']);
        $template = $this->template([
            'created_by' => $other->id,
            'is_shared' => true,
        ]);

        $this->actingAs($this->coordinator)
            ->get(route('coordinator.management_templates.edit', ['template' => $template->id]))
            ->assertForbidden();
    }

    public function test_management_routes_reject_progress_template(): void
    {
        $template = $this->template([
            'created_by' => $this->coordinator->id,
            'sheet_type' => 'progress',
        ]);

        $this->actingAs($this->coordinator)
            ->get(route('coordinator.management_templates.edit', ['template' => $template->id]))
            ->assertNotFound();
    }

    private function template(array $attributes = []): ProgressTemplate
    {
        return ProgressTemplate::create(array_merge([
            'name' => '管理テンプレート',
            'description' => null,
            'column_config' => [['key' => 'default', 'label' => '初校', 'type' => 'worker']],
            'row_config' => [],
            'sheet_type' => 'management',
            'created_by' => $this->coordinator->id,
            'is_shared' => false,
        ], $attributes));
    }
}
