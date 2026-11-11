<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesAuditLog;
use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/**
 * サン・ブレーン売上分析の「注目ポイント」（SBATTN_PLAN1.md）。ダミーデータの数値を手計算と照合する。
 *
 * 企画（planning）:
 *   A: 2024/9=500 ／ B: 2025/3=300 ／ C: 2024/9=40・2025/9=100・2025/12=100 ／ D: 2024/1=200 ／ E: 2025/8=50
 *   G: 2024/3=100・2025/3=400 ／ H: 2024/12=900・2025/12=100 ／ F1: 2024/8=100 ／ F2: 2024/9=100 ／ Z2: 2025/5=30
 *   登録月: 2024={1,3,8,9,12} 2025={3,5,8,9,12}（最新=2025/12）
 * 制作（production）: Y: 2025/6=50 ／ Z2: 2025/1=60 ／ X: 2024/1=100。登録月: 2024={1} 2025={1,6}（最新=2025/6）
 *
 * 2025年（企画。登録済みの最後の月=12 → 1〜12月を前年の同じ月と比べる）:
 *   増加: G +300, C +160 ／ 減少: H -800 ／ 新規: B 300, E 50, Z2 30
 * 休眠（企画）:
 *   3か月（止まり=2025/10〜12／前=2024/10〜2025/9）: G 400, B 300, E 50, Z2 30
 *   6か月（止まり=2025/7〜12／前=2024/7〜2025/6）:  A 500, G 400, B 300, F1 100, F2 100, Z2 30
 *   12か月（止まり=2025/1〜12／前=2024/1〜12）:       A 500, D 200, F1 100, F2 100
 * 休眠（制作。最新=2025/6）: 3か月（止まり=2025/4〜6／前=2024/4〜2025/3）: Z2 60（企画で売れていても、制作では休眠）
 * 休眠（全部署。基準月=3部署のうち登録がある部署すべての最新 = 2025/6）: 3か月: H 900, A 500, G 400, B 300, F1 100, F2 100, C 40
 *   （Z2 は、止まり期間の企画 2025/5 に売上があるので休眠ではない）
 */
class SalesAttentionTest extends TestCase
{
    use RefreshesSalesDatabase {
        RefreshesSalesDatabase::setUp as private setUpSalesDatabase;
    }

    protected function setUp(): void
    {
        $this->setUpSalesDatabase();
        $this->seedData();
    }

    /** 1部署・1か月分の登録（取込1件＋その月の注文）。$clients = [得意先名 => 金額] */
    private function seedMonth(string $dept, int $year, int $month, array $clients): void
    {
        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'source_type' => 'monthly',
            'source_year' => $year, 'source_month' => $month, 'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx", 'file_sha256' => hash('sha256', "att-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed', 'imported_by' => 1, 'imported_at' => now(),
            'order_count' => count($clients), 'detail_count' => count($clients), 'total_amount' => array_sum($clients),
        ]);
        $i = 0;
        foreach ($clients as $name => $amount) {
            SalesOrder::create([
                'sales_import_id' => $import->id, 'order_number' => "ATT-{$dept}-{$year}-{$month}-" . ++$i,
                'client_name' => $name, 'product_name' => '商品', 'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year, 'sales_month' => $month, 'order_amount' => $amount,
            ]);
        }
        SalesActiveMonth::updateOrCreate(
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    private function seedData(): void
    {
        $p = 'planning';
        $this->seedMonth($p, 2024, 1, ['D社' => 200]);
        $this->seedMonth($p, 2024, 3, ['G社' => 100]);
        $this->seedMonth($p, 2024, 8, ['F1社' => 100]);
        $this->seedMonth($p, 2024, 9, ['A社' => 500, 'C社' => 40, 'F2社' => 100]);
        $this->seedMonth($p, 2024, 12, ['H社' => 900]);
        $this->seedMonth($p, 2025, 3, ['B社' => 300, 'G社' => 400]);
        $this->seedMonth($p, 2025, 5, ['Z2社' => 30]);
        $this->seedMonth($p, 2025, 8, ['E社' => 50]);
        $this->seedMonth($p, 2025, 9, ['C社' => 100]);
        $this->seedMonth($p, 2025, 12, ['C社' => 100, 'H社' => 100]);

        $this->seedMonth('production', 2024, 1, ['X社' => 100]);
        $this->seedMonth('production', 2025, 1, ['Z2社' => 60]);
        $this->seedMonth('production', 2025, 6, ['Y社' => 50]);
    }

    private function viewer(): User
    {
        return User::factory()->create(['user_role' => 'superadmin']);
    }

    private function api(array $query = [], ?User $user = null)
    {
        return ($user ? $this->actingAs($user) : $this->actingAs($this->viewer()))
            ->getJson(route('superadmin.sales_analysis.api.attention', $query + ['department_key' => 'planning', 'year' => 2025]));
    }

    /** @return array<int, string> 得意先名（「社」を除いた記号）の並び */
    private function names(array $rows): array
    {
        return array_map(fn ($r) => str_replace('社', '', $r['label']), $rows);
    }

    public function test_increase_decrease_and_new_follow_the_annual_comparison()
    {
        $s = $this->api()->assertOk()->json();

        $this->assertTrue($s['has_data']);
        $this->assertSame(12, $s['last_month']);
        $this->assertTrue($s['comparison_available']);
        $this->assertSame(['G', 'C'], $this->names($s['increase']['rows']));
        $this->assertEquals([300, 160], array_column($s['increase']['rows'], 'diff'));
        $this->assertSame(['H'], $this->names($s['decrease']['rows']));
        $this->assertEquals(-800, $s['decrease']['rows'][0]['diff']);
        $this->assertSame(['B', 'E', 'Z2'], $this->names($s['new']['rows']));
        $this->assertEquals([300, 50, 30], array_column($s['new']['rows'], 'amount'));
        $this->assertSame(3, $s['new']['total_count']);
    }

    public function test_increase_and_decrease_are_limited_to_the_top_ten()
    {
        $prior = $current = [];
        foreach (range(1, 12) as $i) {
            $prior["上げ商事{$i}"] = 100;
            $current["上げ商事{$i}"] = 100 + $i;
        }
        $this->seedMonth('planning', 2024, 11, $prior);
        $this->seedMonth('planning', 2025, 11, $current);
        $s = $this->api()->assertOk()->json();

        $this->assertCount(10, $s['increase']['rows']);
        $this->assertSame(14, $s['increase']['total_count']);   // 12社 ＋ G, C
        $this->assertSame(['G社', 'C社', '上げ商事12'], array_slice(array_column($s['increase']['rows'], 'label'), 0, 3));
    }

    public function test_dormant_for_three_six_and_twelve_months()
    {
        $three = $this->api(['dormant_months' => 3])->json()['dormant'];
        $this->assertTrue($three['available']);
        $this->assertSame(['G', 'B', 'E', 'Z2'], $this->names($three['rows']));
        $this->assertSame('2025年12月', $three['latest_label']);
        $this->assertSame('2025年10月〜2025年12月', $three['silent_label']);
        $this->assertSame('2024年10月〜2025年9月', $three['prior_label']);

        $six = $this->api()->json()['dormant'];   // 既定は6か月
        $this->assertSame(6, $six['months']);
        $this->assertSame(['A', 'G', 'B', 'F1', 'F2', 'Z2'], $this->names($six['rows']));
        $this->assertSame('2024年9月', $six['rows'][0]['last_label']);
        $this->assertSame(15, $six['rows'][0]['silent_months']);   // 2025/12 - 2024/9

        $twelve = $this->api(['dormant_months' => 12])->json()['dormant'];
        $this->assertSame(['A', 'D', 'F1', 'F2'], $this->names($twelve['rows']));
    }

    public function test_dormant_is_decided_by_the_selected_departments_sales_only()
    {
        // 制作: 最新の登録月は 2025/6。Z2 は、企画で売っていても、制作の売上は止まっている → 休眠
        $production = $this->api(['department_key' => 'production', 'dormant_months' => 3])->json()['dormant'];
        $this->assertTrue($production['available']);
        $this->assertSame('2025年6月', $production['latest_label']);
        $this->assertSame(['Z2'], $this->names($production['rows']));
        $this->assertEquals(60, $production['rows'][0]['prior_amount']);

        // 全部署: 基準月は、登録のある部署すべての最新（2025/6）。Z2 は止まり期間（2025/4〜6）の企画に売上があるので休眠ではない
        $all = $this->api(['department_key' => 'all', 'dormant_months' => 3])->json()['dormant'];
        $this->assertSame('2025年6月', $all['latest_label']);
        $this->assertSame(['H', 'A', 'G', 'B', 'F1', 'F2', 'C'], $this->names($all['rows']));
        $this->assertEquals([900, 500, 400, 300, 100, 100, 40], array_column($all['rows'], 'prior_amount'));
    }

    public function test_dormant_says_data_is_missing_when_the_range_is_too_short()
    {
        // 制作の登録は 2024/1 から。12か月（2023/7 以降の登録が必要）には足りない
        $res = $this->api(['department_key' => 'production', 'dormant_months' => 12])->json()['dormant'];
        $this->assertFalse($res['available']);
        $this->assertStringContainsString('データが足りません', $res['message']);
        $this->assertSame([], $res['rows']);
        $this->assertFalse($this->api(['department_key' => 'all', 'dormant_months' => 12])->json()['dormant']['available']);

        // 6か月なら判定できる
        $this->assertTrue($this->api(['department_key' => 'production', 'dormant_months' => 6])->json()['dormant']['available']);
    }

    public function test_client_consolidation_toggle_groups_clients_into_one_row()
    {
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => 'F統合グループ', 'created_by' => 1, 'updated_by' => 1]);
        foreach (['F1社', 'F2社'] as $name) {
            SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => $name, 'normalized_name' => $name]);
        }

        // しない（初期値）: 元の名前のまま 2 行
        $off = $this->api()->json()['dormant']['rows'];
        $this->assertContains('F1社', array_column($off, 'label'));
        $this->assertContains('F2社', array_column($off, 'label'));

        // する: 1 社にまとめた数字（100 + 100 = 200）
        $on = $this->api(['consolidate_clients' => 1])->json()['dormant']['rows'];
        $this->assertSame(['A社', 'G社', 'B社', 'F統合グループ', 'Z2社'], array_column($on, 'label'));
        $this->assertEquals(200, $on[3]['prior_amount']);
    }

    public function test_first_year_has_no_comparison_but_still_shows_dormant()
    {
        $s = $this->api(['year' => 2024])->json();

        $this->assertTrue($s['has_data']);
        $this->assertFalse($s['comparison_available']);
        $this->assertSame([], $s['increase']['rows']);
        $this->assertSame([], $s['new']['rows']);
        $this->assertTrue($s['dormant']['available']);

        $this->assertFalse($this->api(['year' => 2030])->json()['has_data']);
    }

    public function test_validation_permission_and_page()
    {
        $this->api(['dormant_months' => 5])->assertStatus(422);
        $this->api(['department_key' => 'unknown'])->assertStatus(422);

        $leader = User::factory()->create(['user_role' => 'leader']);
        $this->actingAs($leader)->getJson(route('superadmin.sales_analysis.api.attention', ['department_key' => 'planning', 'year' => 2025]))->assertForbidden();
        $this->actingAs($leader)->get(route('superadmin.sales_analysis.attention'))->assertForbidden();

        $this->actingAs($this->viewer())->get(route('superadmin.sales_analysis.attention'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('SalesAnalysis/Attention', false)->where('hasAnyData', true)->where('initialYear', 2025));
    }

    public function test_excel_export_has_all_rows_and_records_only_the_row_count()
    {
        $res = $this->actingAs($this->viewer())->get(route('superadmin.sales_analysis.attention.export', ['department_key' => 'planning', 'year' => 2025, 'dormant_months' => 6]));
        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'att') . '.xlsx';
        file_put_contents($path, $res->getContent());
        try {
            $book = IOFactory::load($path);
        } finally {
            @unlink($path);
        }
        $this->assertSame(['条件', '大きく増えた得意先', '大きく減った得意先', '新規の得意先', '休眠の得意先'], $book->getSheetNames());
        $sheet = fn (string $name) => array_column($book->getSheetByName($name)->toArray(null, false, false, false), 0);
        $this->assertSame(['得意先', 'G社', 'C社'], $sheet('大きく増えた得意先'));
        $this->assertSame(['得意先', 'B社', 'E社', 'Z2社'], $sheet('新規の得意先'));
        $this->assertSame(['得意先', 'A社', 'G社', 'B社', 'F1社', 'F2社', 'Z2社'], $sheet('休眠の得意先'));

        $log = SalesAuditLog::where('action', 'export')->where('target_type', 'attention')->first();
        $this->assertNotNull($log);
        $this->assertSame(['rows'], array_keys($log->context));
        $this->assertSame(2 + 1 + 3 + 6, $log->context['rows']);   // 増加2＋減少1＋新規3＋休眠6
        $this->assertStringNotContainsString('社', json_encode($log->context, JSON_UNESCAPED_UNICODE));
    }
}
