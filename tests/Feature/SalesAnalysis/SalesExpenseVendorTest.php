<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;
use App\Services\SalesAnalysis\SalesExpenseAliasService;
use App\Services\SalesAnalysis\SalesExpenseVendorService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase24-3: 経費「支払先カルテ」（全部署・全科目・全年度を支払先で横断） */
class SalesExpenseVendorTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    /** @param  array<int, array{0:string,1:string,2:string,3:int,4:int,5:float}>  $items [部署, 科目, 支払先(nullで未記入), 暦年, 暦月, 金額] */
    private function seedImport(int $fiscalYear, array $items): void
    {
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'fiscal_year' => $fiscalYear,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "vendor-seed-{$fiscalYear}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'category_count' => 1,
            'line_item_count' => count($items),
            'total_amount' => array_sum(array_column($items, 5)),
        ]);

        foreach ($items as $i => [$dept, $category, $vendor, $year, $month, $amount]) {
            SalesExpenseLineItem::create([
                'sales_expense_import_id' => $import->id,
                'department_key' => $dept,
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

    private function service(): SalesExpenseVendorService
    {
        return (new SalesExpenseVendorService())->forCompany($this->salesTestCompanyId());
    }

    private function aliasService(): SalesExpenseAliasService
    {
        return (new SalesExpenseAliasService())->forCompany($this->salesTestCompanyId());
    }

    private function seedTwoYears(): void
    {
        $this->seedImport(2024, [
            ['planning', '外注加工', 'A社', 2024, 4, 1000],
            ['production', '外注加工', 'A社', 2024, 5, 500],
            ['planning', '外注加工', 'B社', 2024, 4, 200],
        ]);
        $this->seedImport(2025, [
            ['planning', '外注加工費', 'A社', 2025, 4, 3000],
            ['planning', '外注加工費', 'A社', 2026, 3, 1000],
            ['production', '外注加工費', 'C社', 2025, 6, 2000],
            ['planning', '雑費', null, 2025, 4, 9999], // 内訳未記入は支払先ではないので対象外
        ]);
    }

    public function test_ranking_sorts_by_amount_with_share_and_previous_year()
    {
        $this->markCompanyAsSunbrain();
        $this->seedTwoYears();

        $r = $this->service()->ranking(2025);

        $this->assertTrue($r['has_data']);
        $this->assertTrue($r['previous_has_data']);
        $this->assertSame(6000.0, $r['total_amount']); // 内訳未記入（9999）は含まない
        $this->assertSame(['A社', 'C社'], array_column($r['vendors'], 'vendor_name'));

        $a = $r['vendors'][0];
        $this->assertSame(1, $a['rank']);
        $this->assertSame(4000.0, $a['amount']);
        $this->assertSame(66.7, $a['share_pct']);
        $this->assertSame(1500.0, $a['previous_amount']);
        $this->assertSame(166.7, $a['diff_pct']);
        $this->assertSame(['planning'], $a['departments']);
        $this->assertSame(2, $a['years_active']);
        $this->assertSame(2024, $a['first_fiscal_year']);

        $c = $r['vendors'][1];
        $this->assertTrue($c['is_new']);
        $this->assertSame(['production'], $c['departments']);
    }

    public function test_ranking_filters_by_department_and_searches_including_zero_year_vendors()
    {
        $this->markCompanyAsSunbrain();
        $this->seedTwoYears();

        $production = $this->service()->ranking(2025, 'production');
        $this->assertSame(['C社'], array_column($production['vendors'], 'vendor_name'));
        $this->assertSame(2000.0, $production['total_amount']);

        // 検索では、その年度に支払いが無い支払先（B社）も出る。順位は付かない
        $search = $this->service()->ranking(2025, null, 'ｂ社');
        $this->assertSame(['B社'], array_column($search['vendors'], 'vendor_name'));
        $this->assertNull($search['vendors'][0]['rank']);
        $this->assertSame(0.0, $search['vendors'][0]['amount']);
    }

    public function test_ranking_merges_vendor_aliases_and_searches_alias_names()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2024, [['planning', '外注加工', '西武プロセス', 2024, 4, 1000]]);
        $this->seedImport(2025, [['planning', '外注加工費', '㈱西武プロセス', 2025, 4, 1500]]);
        $this->aliasService()->saveVendorGroup('㈱西武プロセス', ['西武プロセス']);

        $r = $this->service()->ranking(2025);
        $this->assertSame(['㈱西武プロセス'], array_column($r['vendors'], 'vendor_name'));
        $this->assertSame(1000.0, $r['vendors'][0]['previous_amount']);
        $this->assertFalse($r['vendors'][0]['is_new']);

        // 別名（旧名）でも検索できる
        $byAlias = $this->service()->ranking(2024, null, '西武プロセス');
        $this->assertSame(['㈱西武プロセス'], array_column($byAlias['vendors'], 'vendor_name'));
        $this->assertSame(1000.0, $byAlias['vendors'][0]['amount']);
    }

    public function test_detail_returns_yearly_totals_breakdown_and_monthly_series()
    {
        $this->markCompanyAsSunbrain();
        $this->seedTwoYears();

        $d = $this->service()->detail('A社', 2025);

        $this->assertTrue($d['has_data']);
        $this->assertSame('A社', $d['vendor_name']);
        $this->assertSame(5500.0, $d['cumulative_total']);
        $this->assertSame(2024, $d['first_fiscal_year']);
        $this->assertSame(2, $d['years_active']);

        $years = collect($d['years'])->keyBy('fiscal_year');
        $this->assertSame(1500.0, $years[2024]['total_amount']);
        $this->assertSame(['planning' => 1000.0, 'production' => 500.0], $years[2024]['by_department']);
        $this->assertSame(4000.0, $years[2025]['total_amount']);
        $this->assertSame(166.7, $years[2025]['diff_pct']);
        $this->assertSame(66.67, $years[2025]['share_pct']); // 2025年度の全支払先（A社4000+C社2000）に占める割合

        // 基準年度の内訳（部署×科目）
        $this->assertSame([['planning', '外注加工費', 4000.0, 100.0]], array_map(fn ($b) => [$b['department_key'], $b['category'], $b['amount'], $b['share_pct']], $d['breakdown']));

        // 月別（会計月の添字: 4月=0、3月=11）
        $monthly = collect($d['monthly'])->keyBy('fiscal_year');
        $this->assertSame(3000.0, $monthly[2025]['months'][0]);
        $this->assertSame(1000.0, $monthly[2025]['months'][11]);
        $this->assertSame(500.0, $monthly[2024]['months'][1]);
    }

    public function test_detail_resolves_alias_name_and_lists_aliases()
    {
        $this->markCompanyAsSunbrain();
        $this->seedImport(2024, [['planning', '外注加工', '西武プロセス', 2024, 4, 1000]]);
        $this->seedImport(2025, [['planning', '外注加工費', '㈱西武プロセス', 2025, 4, 1500]]);
        $this->aliasService()->saveVendorGroup('㈱西武プロセス', ['西武プロセス']);

        $d = $this->service()->detail('西武プロセス', 2025); // 別名で開いても正式名のカルテ
        $this->assertSame('㈱西武プロセス', $d['vendor_name']);
        $this->assertSame(['西武プロセス'], $d['aliases']);
        $this->assertSame(2500.0, $d['cumulative_total']);
    }

    public function test_detail_for_unknown_vendor_has_no_data()
    {
        $this->markCompanyAsSunbrain();
        $this->seedTwoYears();

        $d = $this->service()->detail('存在しない社', 2025);
        $this->assertFalse($d['has_data']);
        $this->assertSame(0.0, $d['cumulative_total']);
        $this->assertNull($d['first_fiscal_year']);
    }

    public function test_ranking_without_imports_has_no_data()
    {
        $this->markCompanyAsSunbrain();

        $r = $this->service()->ranking(2025);
        $this->assertFalse($r['has_data']);
        $this->assertSame([], $r['vendors']);
    }

    public function test_endpoints_forbidden_for_non_sunbrain_company()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.ranking', ['fiscal_year' => 2025]))->assertForbidden();
        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.detail', ['fiscal_year' => 2025, 'vendor' => 'A社']))->assertForbidden();
    }

    public function test_endpoints_return_ranking_and_detail_and_validate()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->markCompanyAsSunbrain();
        $this->seedTwoYears();

        $ranking = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.ranking', ['fiscal_year' => 2025, 'department_key' => 'planning']));
        $ranking->assertOk();
        $ranking->assertJsonPath('vendors.0.vendor_name', 'A社');

        $detail = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.detail', ['fiscal_year' => 2025, 'vendor' => 'A社']));
        $detail->assertOk();
        $detail->assertJsonPath('vendor_name', 'A社');
        $detail->assertJsonPath('cumulative_total', 5500);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.ranking', ['fiscal_year' => 2025, 'department_key' => 'unknown']))->assertStatus(422);
        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.ranking'))->assertStatus(422);
        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.api.expense_vendors.detail', ['fiscal_year' => 2025]))->assertStatus(422);
    }
}
