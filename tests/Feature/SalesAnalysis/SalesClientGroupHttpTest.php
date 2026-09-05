<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesAuditLog;
use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesClientGroupHttpTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedMonth(string $dept, int $year, int $month, string $clientName, float $amount): void
    {
        $this->seedMonthOrders($dept, $year, $month, [[$clientName, $amount]]);
    }

    /** @param  array<int, array{0: string, 1: float}>  $clients  [client_name, amount]の配列 */
    private function seedMonthOrders(string $dept, int $year, int $month, array $clients): void
    {
        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx",
            'file_sha256' => hash('sha256', "cgh-test-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => count($clients),
            'detail_count' => count($clients),
            'total_amount' => array_sum(array_column($clients, 1)),
        ]);

        foreach ($clients as $i => [$clientName, $amount]) {
            SalesOrder::create([
                'sales_import_id' => $import->id,
                'order_number' => "CGH-{$dept}-{$year}-{$month}-{$i}",
                'client_name' => $clientName,
                'product_name' => '商品A',
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year,
                'sales_month' => $month,
                'order_amount' => $amount,
            ]);
        }

        SalesActiveMonth::updateOrCreate(
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    public function test_index_renders()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.client_groups.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SalesAnalysis/ClientGroups', false));
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.client_groups.index'))
            ->assertForbidden();
    }

    public function test_data_endpoint_returns_candidates_groups_and_unassigned()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonthOrders('planning', 2026, 1, [
            ['株式会社サンプル', 1000.0],
            ['株式会社 サンプル', 500.0],
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.api.client_groups'));

        $response->assertOk();
        $this->assertCount(1, $response->json('candidates'));
        $this->assertCount(2, $response->json('unassigned_clients'));
    }

    public function test_store_creates_group_and_members_without_leaking_client_names_into_audit_log()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2026, 1, 'A社', 1000.0);
        $this->seedMonth('planning', 2026, 1, 'A商事', 500.0);

        $response = $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.client_groups.store'), [
            'name' => 'A社グループ',
            'client_names' => ['A社', 'A商事'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sales_client_groups', ['name' => 'A社グループ'], 'sales');
        $this->assertDatabaseHas('sales_client_group_members', ['client_name' => 'A社'], 'sales');

        $log = SalesAuditLog::where('action', 'client_group_create')->first();
        $this->assertNotNull($log);
        $context = json_encode($log->context);
        $this->assertStringNotContainsString('A社', $context ?: '');
    }

    public function test_store_rejects_when_client_already_belongs_to_another_group()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '既存グループ', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => 'B社', 'normalized_name' => 'B社']);

        $response = $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.client_groups.store'), [
            'name' => '新グループ',
            'client_names' => ['B社'],
        ]);

        $response->assertStatus(422);
    }

    public function test_update_renames_group()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '旧名称', 'created_by' => 1, 'updated_by' => 1]);

        $this->actingAs($superadmin)
            ->patchJson(route('superadmin.sales_analysis.api.client_groups.update', ['group' => $group->id]), ['name' => '新名称'])
            ->assertOk();

        $this->assertDatabaseHas('sales_client_groups', ['id' => $group->id, 'name' => '新名称'], 'sales');
    }

    public function test_destroy_deletes_group_and_cascades_members()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '削除対象', 'created_by' => 1, 'updated_by' => 1]);
        $member = SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => 'C社', 'normalized_name' => 'C社']);

        $this->actingAs($superadmin)
            ->deleteJson(route('superadmin.sales_analysis.api.client_groups.destroy', ['group' => $group->id]))
            ->assertOk();

        $this->assertDatabaseMissing('sales_client_groups', ['id' => $group->id], 'sales');
        $this->assertDatabaseMissing('sales_client_group_members', ['id' => $member->id], 'sales');
    }

    public function test_add_member_rejects_client_already_in_another_group()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $groupA = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => 'グループA', 'created_by' => 1, 'updated_by' => 1]);
        $groupB = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => 'グループB', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $groupA->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => 'D社', 'normalized_name' => 'D社']);

        $this->actingAs($superadmin)
            ->postJson(route('superadmin.sales_analysis.api.client_groups.members.store', ['group' => $groupB->id]), ['client_name' => 'D社'])
            ->assertStatus(422);
    }

    public function test_remove_member_returns_404_when_member_does_not_belong_to_group()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $groupA = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => 'グループA', 'created_by' => 1, 'updated_by' => 1]);
        $groupB = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => 'グループB', 'created_by' => 1, 'updated_by' => 1]);
        $member = SalesClientGroupMember::create(['sales_client_group_id' => $groupA->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => 'E社', 'normalized_name' => 'E社']);

        $this->actingAs($superadmin)
            ->deleteJson(route('superadmin.sales_analysis.api.client_groups.members.destroy', ['group' => $groupB->id, 'member' => $member->id]))
            ->assertStatus(404);
    }

    public function test_preview_does_not_persist_anything()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2026, 2, 'F社', 1000.0);

        $response = $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.client_groups.preview'), [
            'client_names' => ['F社'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('total_amount', 1000);
        $this->assertDatabaseCount('sales_client_groups', 0, 'sales');
    }

    public function test_store_requires_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->postJson(route('superadmin.sales_analysis.api.client_groups.store'), ['name' => 'x', 'client_names' => ['A']])
            ->assertForbidden();
    }
}
