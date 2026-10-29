<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;
use App\Services\SalesAnalysis\SalesExpenseAliasService;
use App\Services\SalesAnalysis\SalesExpenseImportDiffService;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsExpenseWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase24-6: 経費の再取込時の差分表示（版どうしの比較。旧版は履歴として残っている） */
class SalesExpenseImportDiffTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsExpenseWorkbook;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    /** @param  array<int, array{0:string,1:string,2:string,3:int,4:int,5:float}>  $items [部署, 科目, 支払先, 暦年, 暦月, 金額] */
    private function seedVersion(int $fiscalYear, int $version, array $items, int $companyId = null): SalesExpenseImport
    {
        $import = SalesExpenseImport::create([
            'company_id' => $companyId ?? $this->salesTestCompanyId(),
            'fiscal_year' => $fiscalYear,
            'version' => $version,
            'original_filename' => "経費表_v{$version}.xlsx",
            'file_sha256' => hash('sha256', "diff-{$fiscalYear}-{$version}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'category_count' => count(array_unique(array_column($items, 1))),
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

        return $import;
    }

    private function service(): SalesExpenseImportDiffService
    {
        return (new SalesExpenseImportDiffService())->forCompany($this->salesTestCompanyId());
    }

    private function aliasService(): SalesExpenseAliasService
    {
        return (new SalesExpenseAliasService())->forCompany($this->salesTestCompanyId());
    }

    public function test_diff_detects_changed_added_and_removed_vendors_and_categories()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [
            ['planning', '外注加工費', 'A社', 2025, 4, 1000],
            ['planning', '外注加工費', 'A社', 2025, 5, 500],
            ['planning', '外注加工費', 'B社', 2025, 4, 300],     // v2で消える
            ['planning', '旧科目', 'Z社', 2025, 4, 200],          // 科目ごと v2で消える
            ['planning', '消耗品費', 'C社', 2025, 4, 100],        // 変更なし
        ]);
        $this->seedVersion(2025, 2, [
            ['planning', '外注加工費', 'A社', 2025, 4, 1500],     // 4月: 1000→1500
            ['planning', '外注加工費', 'A社', 2025, 5, 500],      // 5月は同じ
            ['planning', '外注加工費', 'D社', 2025, 6, 700],      // 新しい支払先
            ['planning', '新科目', 'E社', 2025, 4, 400],          // 科目ごと新規
            ['planning', '消耗品費', 'C社', 2025, 4, 100],
        ]);

        $d = $this->service()->diff(2025, 2);

        $this->assertTrue($d['has_previous']);
        $this->assertSame(2, $d['version']);
        $this->assertSame(1, $d['previous_version']);
        $this->assertTrue($d['has_changes']);
        $this->assertSame(2100.0, $d['total_old']);
        $this->assertSame(3200.0, $d['total_new']);
        $this->assertSame(1100.0, $d['diff']);
        $this->assertSame(
            ['changed_vendors' => 1, 'added_vendors' => 2, 'removed_vendors' => 2, 'added_categories' => 1, 'removed_categories' => 1],
            $d['summary']
        );

        $this->assertCount(1, $d['departments']);
        $planning = $d['departments'][0];
        $this->assertSame('planning', $planning['department_key']);

        $byCat = collect($planning['categories'])->keyBy('category');
        $this->assertFalse($byCat->has('消耗品費')); // 変更なしの科目は含まれない
        $this->assertSame('added', $byCat['新科目']['status']);
        $this->assertSame('removed', $byCat['旧科目']['status']);
        $this->assertSame('changed', $byCat['外注加工費']['status']);
        $this->assertSame(900.0, $byCat['外注加工費']['diff']); // +500(A) -300(B) +700(D)

        $vendors = collect($byCat['外注加工費']['vendors'])->keyBy('vendor_name');
        $this->assertSame('changed', $vendors['A社']['status']);
        $this->assertSame(500.0, $vendors['A社']['diff']);
        // 変わった月だけを持つ（5月は同じなので含まれない）
        $this->assertSame([['calendar_month' => 4, 'new' => 1500.0, 'old' => 1000.0]], $vendors['A社']['months']);
        $this->assertSame('removed', $vendors['B社']['status']);
        $this->assertSame('added', $vendors['D社']['status']);
        // 金額の変化が大きい順
        $this->assertSame(['D社', 'A社', 'B社'], $byCat['外注加工費']['vendors'] ? array_column($byCat['外注加工費']['vendors'], 'vendor_name') : []);
    }

    public function test_diff_compares_with_the_immediately_previous_version_and_defaults_to_latest()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [['planning', '外注加工費', 'A社', 2025, 4, 100]]);
        $this->seedVersion(2025, 2, [['planning', '外注加工費', 'A社', 2025, 4, 200]]);
        $this->seedVersion(2025, 3, [['planning', '外注加工費', 'A社', 2025, 4, 250]]);

        $latest = $this->service()->diff(2025); // 省略時は最新版（v3）とその直前（v2）
        $this->assertSame(3, $latest['version']);
        $this->assertSame(2, $latest['previous_version']);
        $this->assertSame(50.0, $latest['diff']);

        $v2 = $this->service()->diff(2025, 2);
        $this->assertSame(1, $v2['previous_version']);
        $this->assertSame(100.0, $v2['diff']);
    }

    public function test_diff_has_no_previous_for_first_version_or_unknown_year()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [['planning', '外注加工費', 'A社', 2025, 4, 100]]);

        $this->assertFalse($this->service()->diff(2025, 1)['has_previous']);
        $this->assertFalse($this->service()->diff(2030)['has_previous']);
    }

    public function test_diff_ignores_sub_yen_noise_and_identical_versions()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [['planning', '外注加工費', 'A社', 2025, 4, 1000.00]]);
        $this->seedVersion(2025, 2, [['planning', '外注加工費', 'A社', 2025, 4, 1000.30]]);

        $d = $this->service()->diff(2025, 2);
        $this->assertTrue($d['has_previous']);
        $this->assertFalse($d['has_changes']);
        $this->assertSame([], $d['departments']);
        $this->assertSame(0, $d['summary']['changed_vendors']);
    }

    public function test_diff_uses_alias_names_so_renamed_vendors_and_categories_are_not_add_or_remove()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [['planning', '外注加工', '西武プロセス', 2025, 4, 1000]]);
        $this->seedVersion(2025, 2, [['planning', '外注加工費', '㈱西武プロセス', 2025, 4, 1000]]);

        // 名寄せ前は、表記が変わっただけでも「消えた＋追加」に見える
        $before = $this->service()->diff(2025, 2);
        $this->assertSame(1, $before['summary']['added_vendors']);
        $this->assertSame(1, $before['summary']['removed_vendors']);

        $this->aliasService()->saveGroup('planning', '外注加工費', ['外注加工']);
        $this->aliasService()->saveVendorGroup('㈱西武プロセス', ['西武プロセス']);

        $after = $this->service()->diff(2025, 2);
        $this->assertFalse($after['has_changes']);
    }

    public function test_diff_lists_changed_months_in_fiscal_order_and_multiple_departments()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [
            ['planning', '外注加工費', 'A社', 2026, 2, 100],
            ['planning', '外注加工費', 'A社', 2025, 12, 100],
            ['planning', '外注加工費', 'A社', 2025, 4, 100],
            ['production', '外注加工費', 'B社', 2025, 4, 10],
        ]);
        $this->seedVersion(2025, 2, [
            ['planning', '外注加工費', 'A社', 2026, 2, 200],
            ['planning', '外注加工費', 'A社', 2025, 12, 200],
            ['planning', '外注加工費', 'A社', 2025, 4, 200],
            ['production', '外注加工費', 'B社', 2025, 4, 99],
        ]);

        $d = $this->service()->diff(2025, 2);
        $this->assertSame(['planning', 'production'], array_column($d['departments'], 'department_key'));
        $months = $d['departments'][0]['categories'][0]['vendors'][0]['months'];
        $this->assertSame([4, 12, 2], array_column($months, 'calendar_month')); // 4月始まり: 4月 → 12月 → 2月
    }

    public function test_history_lists_versions_newest_first_with_current_and_previous_flags()
    {
        $this->markCompanyAsSunbrain();
        $user = User::factory()->create(['name' => '取込者']);
        $v1 = $this->seedVersion(2024, 1, [['planning', '外注加工費', 'A社', 2024, 4, 100]]);
        $v2 = $this->seedVersion(2025, 1, [['planning', '外注加工費', 'A社', 2025, 4, 100]]);
        $v3 = $this->seedVersion(2025, 2, [['planning', '外注加工費', 'A社', 2025, 4, 200]]);
        $v3->update(['imported_by' => $user->id]);
        // 他社の取込は出ない
        $this->seedVersion(2025, 1, [['planning', '外注加工費', 'X社', 2025, 4, 1]], $this->salesTestCompanyId() + 999);

        $h = collect($this->service()->history());

        $this->assertSame([[2025, 2], [2025, 1], [2024, 1]], $h->map(fn ($i) => [$i['fiscal_year'], $i['version']])->all());
        $this->assertTrue($h[0]['is_current']);
        $this->assertFalse($h[1]['is_current']); // 2025年度のv1は旧版（履歴）
        $this->assertTrue($h[0]['has_previous']);
        $this->assertFalse($h[1]['has_previous']);
        $this->assertFalse($h[2]['has_previous']);
        $this->assertSame('取込者', $h[0]['imported_by_name']);
        $this->assertSame('経費表_v2.xlsx', $h[0]['original_filename']);
    }

    public function test_endpoints_return_history_and_diff_and_validate()
    {
        $this->markCompanyAsSunbrain();
        $this->seedVersion(2025, 1, [['planning', '外注加工費', 'A社', 2025, 4, 100]]);
        $this->seedVersion(2025, 2, [['planning', '外注加工費', 'A社', 2025, 4, 300]]);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $history = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_import.history'));
        $history->assertOk();
        $history->assertJsonPath('imports.0.version', 2);
        $history->assertJsonPath('imports.0.has_previous', true);

        $diff = $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_import.diff', ['fiscal_year' => 2025, 'version' => 2]));
        $diff->assertOk();
        $diff->assertJsonPath('has_previous', true);
        $diff->assertJsonPath('diff', 200);
        $diff->assertJsonPath('departments.0.categories.0.vendors.0.vendor_name', 'A社');

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_import.diff'))->assertStatus(422);
        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_import.diff', ['fiscal_year' => 2025, 'version' => 0]))->assertStatus(422);
    }

    public function test_endpoints_forbidden_for_non_sunbrain_company()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_import.history'))->assertForbidden();
        $this->actingAs($superadmin)->getJson(route('superadmin.sales_analysis.expense_import.diff', ['fiscal_year' => 2025]))->assertForbidden();
    }

    private function workbookPath(float $amount): string
    {
        return $this->makeExpenseWorkbook([
            '情報出版' => [
                ['category' => '主材料費', 'vendors' => [['name' => 'A社', 'amounts' => [4 => $amount]]], 'total_label' => '合計'],
            ],
            '製版' => [
                ['category' => '外注加工費', 'vendors' => [['name' => 'B社', 'amounts' => [5 => 5000]]], 'total_label' => '合計'],
            ],
            'オンデマンド' => [
                ['category' => '消耗品費', 'vendors' => [['name' => 'C社', 'amounts' => [6 => 3000]]], 'total_label' => '合計'],
            ],
            '共有' => [
                ['category' => '通信費', 'vendors' => [['name' => 'D社', 'amounts' => [7 => 2000]]], 'total_label' => '合計(SAと折半)'],
            ],
        ]);
    }

    public function test_reimport_response_includes_diff_summary_only_from_second_version()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $first = $this->workbookPath(10000);
        $second = $this->workbookPath(12000);
        try {
            $r1 = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => new UploadedFile($first, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'fiscal_year' => 2025,
            ]);
            $r1->assertOk();
            $r1->assertJsonPath('version', 1);
            $r1->assertJsonPath('diff_summary', null);

            $r2 = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => new UploadedFile($second, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'fiscal_year' => 2025,
            ]);
            $r2->assertOk();
            $r2->assertJsonPath('version', 2);
            $r2->assertJsonPath('diff_summary.previous_version', 1);
            $r2->assertJsonPath('diff_summary.changed_vendors', 1);
            $r2->assertJsonPath('diff_summary.added_vendors', 0);
            $r2->assertJsonPath('diff_summary.diff', 2000);
            $r2->assertJsonPath('diff_summary.has_changes', true);
        } finally {
            @unlink($first);
            @unlink($second);
        }
    }
}
