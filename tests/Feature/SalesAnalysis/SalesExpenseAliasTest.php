<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseAlias;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;
use App\Services\SalesAnalysis\SalesExpenseAliasService;
use App\Services\SalesAnalysis\SalesExpenseQueryService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase24: 経費の名寄せ（科目名の別名→正式名の読み替え） */
class SalesExpenseAliasTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    /** @param  array<int, array{0:string,1:string,2:int,3:int,4:float}>  $items [科目, 支払先, 暦年, 暦月, 金額] */
    private function seedImport(int $fiscalYear, array $items, string $department = 'production'): void
    {
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'fiscal_year' => $fiscalYear,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "alias-seed-{$fiscalYear}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'category_count' => count(array_unique(array_column($items, 0))),
            'line_item_count' => count($items),
            'total_amount' => array_sum(array_column($items, 4)),
        ]);

        foreach ($items as $i => [$category, $vendor, $year, $month, $amount]) {
            SalesExpenseLineItem::create([
                'sales_expense_import_id' => $import->id,
                'department_key' => $department,
                'category' => $category,
                'category_order' => $i + 1,
                'vendor_name' => $vendor,
                'calendar_year' => $year,
                'calendar_month' => $month,
                'amount' => $amount,
                'is_shared_split' => false,
                'subtotal_group' => 1,
            ]);
        }
    }

    private function aliasService(): SalesExpenseAliasService
    {
        return (new SalesExpenseAliasService())->forCompany($this->salesTestCompanyId());
    }

    private function queryService(): SalesExpenseQueryService
    {
        return (new SalesExpenseQueryService())->forCompany($this->salesTestCompanyId());
    }

    public function test_without_alias_renamed_category_is_new_and_with_alias_it_is_compared()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2024, [['外注加工', 'A社', 2024, 4, 1000]]);
        $this->seedImport(2025, [['外注加工費', 'A社', 2025, 4, 1500]]);

        $before = $this->queryService()->departmentSheet(2025, 'production');
        $this->assertTrue($before['categories'][0]['is_new']);
        $this->assertTrue($before['categories'][0]['groups'][0]['vendors'][0]['is_new']);

        $this->aliasService()->saveGroup('production', '外注加工費', ['外注加工']);

        $after = $this->queryService()->departmentSheet(2025, 'production');
        $this->assertSame(['外注加工費'], array_column($after['categories'], 'category'));
        $this->assertFalse($after['categories'][0]['is_new']);
        $this->assertSame(50.0, $after['categories'][0]['diff_pct']);
        $this->assertSame(1000.0, $after['previous_total_amount']);
        $this->assertSame(50.0, $after['categories'][0]['groups'][0]['vendors'][0]['diff_pct']);
    }

    public function test_alias_applies_to_trend_vendors_and_monthly_totals()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2024, [['外注加工', 'A社', 2024, 4, 1000]]);
        $this->seedImport(2025, [['外注加工費', 'A社', 2025, 4, 1500], ['外注加工費', 'B社', 2025, 4, 500]]);

        // 名寄せ前: 旧名の年度は、正式名での検索に出てこない
        $this->assertSame(0.0, $this->queryService()->categoryMultiYearTrend('production', '外注加工費')['years'][0]['amount']);

        $this->aliasService()->saveGroup('production', '外注加工費', ['外注加工']);

        $trend = $this->queryService()->categoryMultiYearTrend('production', '外注加工費')['years'];
        $this->assertSame([1000.0, 2000.0], array_column($trend, 'amount'));

        $comparison = $this->queryService()->vendorComparison(2025, 'production', '外注加工費');
        $byVendor = collect($comparison['vendors'])->keyBy('vendor_name');
        $this->assertSame(1000.0, $byVendor['A社']['previous_amount']);
        $this->assertNull($byVendor['B社']['previous_amount']);

        $breakdown = $this->queryService()->vendorBreakdown(2024, 'production', '外注加工費');
        $this->assertTrue($breakdown['has_data']);
        $this->assertSame(1000.0, $breakdown['total_amount']);

        $totals = $this->queryService()->departmentMonthlyTotals(2024, 'production');
        $this->assertSame(['外注加工費'], array_column($totals['categories'], 'category'));

        $this->assertSame(['外注加工費'], $this->queryService()->categoriesForDepartment('production'));
    }

    public function test_alias_names_used_in_the_same_year_are_merged()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025, [['消耗品', 'A社', 2025, 4, 100], ['消耗品費', 'B社', 2025, 4, 200]]);
        $this->aliasService()->saveGroup('production', '消耗品費', ['消耗品']);

        $sheet = $this->queryService()->departmentSheet(2025, 'production');
        $this->assertCount(1, $sheet['categories']);
        $this->assertSame(300.0, $sheet['categories'][0]['months'][0]);

        $totals = $this->queryService()->departmentMonthlyTotals(2025, 'production');
        $this->assertCount(1, $totals['categories']);
        $this->assertSame(300.0, $totals['categories'][0]['total_amount']);
    }

    public function test_alias_is_scoped_to_department_and_company()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2025, [['消耗品', 'A社', 2025, 4, 100]], 'production');
        $this->seedImport(2024, [['消耗品', 'A社', 2024, 4, 100]], 'planning');

        $this->aliasService()->saveGroup('planning', '消耗品費', ['消耗品']);
        SalesExpenseAlias::create([
            'company_id' => $this->salesTestCompanyId() + 999,
            'kind' => 'category',
            'department_key' => 'production',
            'alias_name' => '消耗品',
            'canonical_name' => '他社名',
        ]);

        // 制作の「消耗品」は、企画の対応表や他社の対応表では読み替えられない
        $production = $this->queryService()->departmentSheet(2025, 'production');
        $this->assertSame(['消耗品'], array_column($production['categories'], 'category'));
        $planning = $this->queryService()->departmentSheet(2024, 'planning');
        $this->assertSame(['消耗品費'], array_column($planning['categories'], 'category'));
    }

    public function test_save_group_prevents_chains_and_merges_groups()
    {
        $service = $this->aliasService();
        $service->saveGroup('production', 'X', ['Y']);
        // X（正式名）を別の正式名Zの別名にすると、Yも一緒にZへ併合される（連鎖させない）
        $service->saveGroup('production', 'Z', ['X']);

        $map = $service->categoryMap('production');
        $this->assertSame(['X' => 'Z', 'Y' => 'Z'], $map);

        // 解除
        $service->saveGroup('production', 'Z', []);
        $this->assertSame([], $service->categoryMap('production'));
    }

    public function test_candidate_key_absorbs_only_safe_variants()
    {
        $key = fn ($n) => SalesExpenseAliasService::candidateKey($n);

        $this->assertSame($key('消耗品'), $key('消耗品費'));
        $this->assertSame($key('リース'), $key('リース料'));
        $this->assertSame($key('主材料費'), $key('主要材料'));
        $this->assertSame($key('主材料費'), $key('材料'));
        $this->assertSame($key('保守・修繕'), $key('修繕・保守'));
        // 意味が変わり得るものは候補にしない
        $this->assertNotSame($key('外注'), $key('外注加工費'));
        $this->assertNotSame($key('旅費交通費'), $key('運賃'));
    }

    public function test_overview_suggests_unmapped_variants_and_approve_registers_them()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2024, [['消耗品', 'A社', 2024, 4, 1], ['外注', 'A社', 2024, 4, 1]]);
        $this->seedImport(2025, [['消耗品費', 'A社', 2025, 4, 1], ['外注加工費', 'A社', 2025, 4, 1]]);

        $overview = $this->aliasService()->overview('production');

        $this->assertCount(1, $overview['suggestions']);
        // 正式名の既定は最新年度の名前
        $this->assertSame('消耗品費', $overview['suggestions'][0]['canonical']);
        $this->assertSame(['消耗品'], array_column($overview['suggestions'][0]['aliases'], 'name'));
        $this->assertSame([2025], $overview['suggestions'][0]['canonical_years']);
        $this->assertEqualsCanonicalizing(['外注', '外注加工費'], array_column($overview['unmapped'], 'name'));

        $this->assertSame(1, $this->aliasService()->approveSuggestions('production'));
        $after = $this->aliasService()->overview('production');
        $this->assertSame([], $after['suggestions']);
        $this->assertSame('消耗品費', $after['groups'][0]['canonical']);
    }

    public function test_endpoints_save_and_approve()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2024, [['消耗品', 'A社', 2024, 4, 1]]);
        $this->seedImport(2025, [['消耗品費', 'A社', 2025, 4, 1]]);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.expense_aliases'))->assertOk();

        $data = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_aliases.data', ['department_key' => 'production']));
        $data->assertOk();
        $data->assertJsonPath('suggestions.0.canonical', '消耗品費');

        $approve = $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_aliases.approve'), ['department_key' => 'production']);
        $approve->assertOk();
        $approve->assertJsonPath('approved_count', 1);
        $approve->assertJsonPath('groups.0.canonical', '消耗品費');

        $save = $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_aliases.save'), [
            'department_key' => 'production',
            'canonical' => '消耗品費',
            'aliases' => [],
        ]);
        $save->assertOk();
        $save->assertJsonPath('groups', []);
        $this->assertSame(0, SalesExpenseAlias::count()); // 解除後は0件
    }

    public function test_endpoints_forbidden_for_non_sunbrain_company()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_aliases.data', ['department_key' => 'production']))->assertForbidden();
        $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_aliases.save'), [
            'department_key' => 'production',
            'canonical' => '消耗品費',
            'aliases' => ['消耗品'],
        ])->assertForbidden();
        $this->assertSame(0, SalesExpenseAlias::count());
    }

    public function test_endpoints_validate_parameters()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_aliases.data', ['department_key' => 'unknown']))->assertStatus(422);
        $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_aliases.save'), [
            'department_key' => 'production',
            'canonical' => '',
            'aliases' => [],
        ])->assertStatus(422);
    }
}
