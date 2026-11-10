<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\ClientGroupService;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\SunaCompanyContext;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * 部門比較・左右比較（SUNASALES_PLAN1.md §18）。ダミーデータの数値を手計算と照合する。
 *
 * データ（受注件数は各行1。得意先 X/Y/Z/W/V/N、名前は「ダミー商事X」など）:
 *   2023: X 12月=5（部1）                                  … 登録のある月 {12}
 *   2024: X 1月100・2月200・3月300（部1）／Y 1月50（部2）／Z 2月400（第三部 第一課）／W 3月30（第三部 直轄）  … {1,2,3}
 *   2025: X 1月150・2月250（部1）／Y 1月80（部2）／Z 1月60・2月20（第一課）／V 2月10（第三部 第二課）／N 1月500（部1）  … {1,2}（途中の年）
 */
class DepartmentCompareTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
        $this->seedData($this->saneiCompanyId);
    }

    protected function tearDown(): void
    {
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function seedData(int $companyId): void
    {
        SunaCompanyContext::run($companyId, function () {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => 'ダミー.xlsx', 'file_sha256' => hash('sha256', uniqid()), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            $rows = [
                ['X', 2023, 12, 5, 'dept1', null],
                ['X', 2024, 1, 100, 'dept1', null], ['X', 2024, 2, 200, 'dept1', null], ['X', 2024, 3, 300, 'dept1', null],
                ['Y', 2024, 1, 50, 'dept2', null],
                ['Z', 2024, 2, 400, 'dept3', 'dept3_sec1'],
                ['W', 2024, 3, 30, 'dept3', 'dept3_direct'],
                ['X', 2025, 1, 150, 'dept1', null], ['X', 2025, 2, 250, 'dept1', null],
                ['Y', 2025, 1, 80, 'dept2', null],
                ['Z', 2025, 1, 60, 'dept3', 'dept3_sec1'], ['Z', 2025, 2, 20, 'dept3', 'dept3_sec1'],
                ['V', 2025, 2, 10, 'dept3', 'dept3_sec2'],
                ['N', 2025, 1, 500, 'dept1', null],
            ];
            foreach ($rows as [$c, $year, $month, $amount, $dept, $section]) {
                Fact::create(['client_key' => $c, 'client_name' => "ダミー商事{$c}", 'sales_year' => $year, 'sales_month' => $month, 'department_key' => $dept, 'section_key' => $section, 'amount' => $amount, 'order_count' => 1, 'import_id' => $import->id]);
            }
        });
    }

    private function viewer(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);

        return $user;
    }

    private function api(string $name, array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->viewer())->getJson(route("suna.sales.report.api.{$name}", $query))->assertOk()->json();
    }

    private function dept(array $s): \Illuminate\Support\Collection
    {
        return collect($s['rows'])->keyBy('key');
    }

    // ------------------------------------------------------------------ 部門比較

    public function test_department_comparison_aligns_every_year_to_the_latest_registered_months()
    {
        $s = $this->api('department.summary');                  // 既定 = 同じ月まで（最新年 2025 の登録済みの月 {1,2}）
        $rows = $this->dept($s);

        $this->assertSame('same_month', $s['basis']);
        $this->assertSame([1, 2], $s['months']);
        $this->assertSame('各年 1〜2月累計', $s['label']);
        $this->assertSame([2023, 2024, 2025], array_column($s['years'], 'year'));

        // 2024年の1〜2月: 第一部=300、第二部=50、第三部（全体）=400（第一課）。3月の W=30 は含めない
        $this->assertEquals(300, $rows['dept1']['amounts'][2024]);
        $this->assertEquals(50, $rows['dept2']['amounts'][2024]);
        $this->assertEquals(400, $rows['dept3']['amounts'][2024]);
        $this->assertEquals(400, $rows['dept3_sec1']['amounts'][2024]);
        $this->assertEquals(0, $rows['dept3_direct']['amounts'][2024]);
        $this->assertEquals(750, $rows['total']['amounts'][2024]);

        // 2025年: 第一部=900、第二部=80、第三部（全体）=90（第一課 80＋第二課 10）
        $this->assertEquals(900, $rows['dept1']['amounts'][2025]);
        $this->assertEquals(90, $rows['dept3']['amounts'][2025]);
        $this->assertEquals(80, $rows['dept3_sec1']['amounts'][2025]);
        $this->assertEquals(10, $rows['dept3_sec2']['amounts'][2025]);
        $this->assertEquals(1070, $rows['total']['amounts'][2025]);
        $this->assertEquals(
            $rows['dept3']['amounts'][2025],
            $rows['dept3_sec1']['amounts'][2025] + $rows['dept3_sec2']['amounts'][2025] + $rows['dept3_direct']['amounts'][2025],
        ); // 第三部（全体）= 課・直轄の合計

        // 構成比（その年の合計に対する%）と前年比
        $this->assertEquals(84.1, $rows['dept1']['shares'][2025]);
        $this->assertEquals(8.4, $rows['dept3']['shares'][2025]);
        $this->assertEquals(42.7, $rows['total']['yoy'][2025]);        // (1070-750)/750
        $this->assertEquals(-77.5, $rows['dept3']['yoy'][2025]);       // (90-400)/400
        $this->assertNull($rows['total']['yoy'][2024]);                 // 前の年（2023）にこの月の登録が無い

        // 第三部の行は、直後に課・直轄が並ぶ（画面で開閉）
        $order = array_column($s['rows'], 'key');
        $this->assertSame(['total', 'dept1', 'dept2', 'dept3', 'dept3_sec1', 'dept3_sec2', 'dept3_direct'], $order);
        $this->assertTrue($rows['dept3']['has_children']);
        $this->assertSame('dept3', $rows['dept3_sec1']['parent']);
        $this->assertSame(1, $rows['dept3_sec1']['level']);
    }

    public function test_a_year_with_no_registered_month_in_the_basis_is_null_not_zero()
    {
        $rows = $this->dept($this->api('department.summary'));
        $years = collect($this->api('department.summary')['years'])->keyBy('year');

        $this->assertFalse($years[2023]['has_data']);                  // 2023年は12月だけ。1〜2月は未登録
        $this->assertNull($rows['dept1']['amounts'][2023]);            // 0円ではなく null（未登録）
        $this->assertNull($rows['total']['shares'][2023]);
        $this->assertTrue($years[2025]['has_data']);
        $this->assertFalse($years[2025]['is_partial']);                // 基準の月（1,2）はそろっている
    }

    public function test_year_basis_shows_partial_years_with_a_mark()
    {
        $s = $this->api('department.summary', ['basis' => 'year']);
        $rows = $this->dept($s);
        $years = collect($s['years'])->keyBy('year');

        $this->assertSame('通年（1〜12月）', $s['label']);
        $this->assertEquals(430, $rows['dept3']['amounts'][2024]);     // 第一課400 + 直轄30（3月も含む）
        $this->assertEquals(1080, $rows['total']['amounts'][2024]);
        $this->assertTrue($years[2024]['is_partial']);                 // 3か月分しか登録がない（通年ではない）
        $this->assertSame('1〜3月累計', $years[2024]['partial_label']);
        $this->assertTrue($years[2025]['is_partial']);
        $this->assertSame('1〜2月累計', $years[2025]['partial_label']);
        $this->assertSame([3, 4, 5, 6, 7, 8, 9, 10, 11, 12], $years[2025]['missing_months']);
    }

    public function test_department_comparison_rejects_an_unknown_basis()
    {
        $this->actingAs($this->viewer())->getJson(route('suna.sales.report.api.department.summary', ['basis' => 'nope']))->assertStatus(422);
    }

    // ------------------------------------------------------------------ 左右比較

    private function compare(array $q, ?User $user = null)
    {
        return $this->api('compare.summary', $q, $user);
    }

    public function test_year_vs_year_compares_only_the_months_registered_in_both_years()
    {
        $s = $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025]);

        $this->assertTrue($s['has_data']);
        $this->assertSame([1, 2], $s['a']['months']);                   // 両方に登録のある月 = 1・2月（2024年の3月は除く）
        $this->assertSame('2025年 1〜2月累計', $s['b']['label']);
        $this->assertEquals(750, $s['kpi']['amount']['a']);
        $this->assertEquals(1070, $s['kpi']['amount']['b']);
        $this->assertEquals(320, $s['kpi']['amount']['diff']);
        $this->assertEquals(42.7, $s['kpi']['amount']['rate']);
        $this->assertSame(4, $s['kpi']['order_count']['a']);
        $this->assertSame(7, $s['kpi']['order_count']['b']);
        $this->assertEquals(188, $s['kpi']['avg_order_price']['a']);    // 750÷4=187.5
        $this->assertEquals(153, $s['kpi']['avg_order_price']['b']);    // 1070÷7
        $this->assertSame(3, $s['kpi']['client_count']['a']);           // X, Y, Z
        $this->assertSame(5, $s['kpi']['client_count']['b']);           // X, Y, Z, V, N
    }

    public function test_year_vs_year_without_a_common_month_is_not_compared()
    {
        $s = $this->compare(['mode' => 'year', 'year_a' => 2023, 'year_b' => 2025]); // 2023=12月だけ、2025=1・2月
        $this->assertFalse($s['has_data']);
        $this->assertStringContainsString('両方に登録のある月がない', $s['reason']);
    }

    public function test_month_vs_month_and_unregistered_month()
    {
        $s = $this->compare(['mode' => 'month', 'year_a' => 2024, 'month_a' => 3, 'year_b' => 2025, 'month_b' => 2]);
        $this->assertSame('2024年3月', $s['a']['label']);
        $this->assertEquals(330, $s['kpi']['amount']['a']);            // X300 + W30
        $this->assertEquals(280, $s['kpi']['amount']['b']);            // X250 + Z20 + V10
        $this->assertEquals(-50, $s['kpi']['amount']['diff']);
        $this->assertSame(['dept3_direct'], collect($s['by_department'])->where('a', 30)->where('level', 1)->pluck('key')->all());

        $none = $this->compare(['mode' => 'month', 'year_a' => 2025, 'month_a' => 3, 'year_b' => 2025, 'month_b' => 2]); // 2025年3月は未登録
        $this->assertFalse($none['has_data']);
        $this->assertStringContainsString('期間A', $none['reason']);
        $this->assertStringContainsString('登録されていない', $none['reason']);
    }

    public function test_compare_departments_have_a_total_row_for_the_third_department_and_its_sections()
    {
        $s = $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025]);
        $rows = collect($s['by_department'])->keyBy('key');

        $this->assertEquals(400, $rows['dept3']['a']);
        $this->assertEquals(90, $rows['dept3']['b']);
        $this->assertEquals(-310, $rows['dept3']['diff']);
        $this->assertEquals(400, $rows['dept3_sec1']['a']);
        $this->assertEquals(80, $rows['dept3_sec1']['b']);
        $this->assertEquals(10, $rows['dept3_sec2']['b']);
        $this->assertEquals(750, $rows['total']['a']);
        $this->assertTrue($rows['dept3']['has_children']);

        // 部門の絞り込みは、部門別の内訳には影響しない（全部門のまま）。KPI・得意先別には効く
        $filtered = $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025, 'dept' => 'dept3']);
        $this->assertEquals(750, collect($filtered['by_department'])->firstWhere('key', 'total')['a']);
        $this->assertEquals(400, $filtered['kpi']['amount']['a']);
        $this->assertEquals(90, $filtered['kpi']['amount']['b']);
    }

    public function test_compare_clients_top_others_new_and_ended()
    {
        $s = $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025]);
        $c = $s['clients'];
        $top = collect($c['top']);

        $this->assertSame(['ダミー商事N', 'ダミー商事X', 'ダミー商事Y', 'ダミー商事Z', 'ダミー商事V'], $top->pluck('label')->all()); // Bの金額順（Y,Zは同額で名前順）
        $this->assertSame('new', $top->firstWhere('label', 'ダミー商事N')['status']);
        $this->assertSame('new', $top->firstWhere('label', 'ダミー商事V')['status']);
        $this->assertSame('continuing', $top->firstWhere('label', 'ダミー商事X')['status']);
        $this->assertEquals(100, $top->firstWhere('label', 'ダミー商事X')['diff']);   // 400 - 300
        $this->assertEquals(33.3, $top->firstWhere('label', 'ダミー商事X')['rate']);
        $this->assertNull($top->firstWhere('label', 'ダミー商事N')['rate']);         // 前が0円
        $this->assertNull($c['others']);                                               // 5社なので「その他」は無い
        $this->assertSame(5, $c['total_clients']);
        $this->assertSame([], $c['ended']);                                            // 1・2月に限れば、終了した得意先は無い

        // 月と月: W は 2024年3月にあり、2025年2月には無い → 終了
        $m = $this->compare(['mode' => 'month', 'year_a' => 2024, 'month_a' => 3, 'year_b' => 2025, 'month_b' => 2])['clients'];
        $this->assertSame(['ダミー商事W'], collect($m['ended'])->pluck('label')->all());
        $this->assertEquals(30, $m['ended'][0]['a']);
        $this->assertSame(1, $m['ended_total']);
    }

    public function test_compare_clients_beyond_the_top_fifteen_are_rolled_into_others()
    {
        SunaCompanyContext::run($this->saneiCompanyId, function () {
            $import = Import::query()->first();
            for ($i = 1; $i <= 14; $i++) {
                Fact::create(['client_key' => "追加{$i}", 'client_name' => "追加得意先{$i}", 'sales_year' => 2025, 'sales_month' => 1, 'department_key' => 'dept2', 'amount' => 1, 'order_count' => 1, 'import_id' => $import->id]);
            }
        });
        $c = $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025])['clients'];

        $this->assertCount(15, $c['top']);
        $this->assertSame(19, $c['total_clients']);              // 5 + 14
        $this->assertSame(4, $c['others']['count']);             // 19 - 15
        $this->assertEquals(4, $c['others']['b']);               // 売上1円の4社
    }

    public function test_compare_counts_grouped_clients_as_one()
    {
        SunaCompanyContext::run($this->saneiCompanyId, fn () => app(ClientGroupService::class)->create('XY', ['X', 'Y'], 1));

        $s = $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025]);
        $this->assertSame(2, $s['kpi']['client_count']['a']);   // 名寄せ後: XY, Z
        $this->assertSame(4, $s['kpi']['client_count']['b']);   // XY, Z, V, N
        $top = collect($s['clients']['top']);
        $this->assertEquals(480, $top->firstWhere('label', 'XY')['b']);   // X(150+250) + Y(80)
        $this->assertEquals(350, $top->firstWhere('label', 'XY')['a']);   // X(100+200) + Y(50)
        $this->assertEquals(750, $s['kpi']['amount']['a']);                // 売上の合計は変わらない
    }

    public function test_validation_permission_and_company_isolation()
    {
        $viewer = $this->viewer();
        $this->actingAs($viewer)->getJson(route('suna.sales.report.api.compare.summary', ['mode' => 'month', 'year_a' => 2024, 'year_b' => 2025]))->assertStatus(422); // 月モードで月が無い
        $this->actingAs($viewer)->getJson(route('suna.sales.report.api.compare.summary', ['mode' => 'bogus', 'year_a' => 2024, 'year_b' => 2025]))->assertStatus(422);
        $this->actingAs($viewer)->getJson(route('suna.sales.report.api.compare.summary', ['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025, 'dept' => 'nope']))->assertStatus(422);

        foreach (['department' => 'SunA/Sales/Department', 'compare' => 'SunA/Sales/Compare'] as $route => $component) {
            $this->actingAs($viewer)->get(route("suna.sales.report.{$route}"))->assertOk()->assertInertia(fn ($p) => $p->component($component, false));
        }
        $other = $this->saneiUser('user');
        $this->actingAs($other)->get(route('suna.sales.report.department'))->assertForbidden();
        $this->actingAs($other)->get(route('suna.sales.report.compare'))->assertForbidden();
        $this->actingAs($other)->getJson(route('suna.sales.report.api.department.summary'))->assertForbidden();
        $this->actingAs($other)->getJson(route('suna.sales.report.api.compare.summary', ['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025]))->assertForbidden();

        // 別会社に同じ内容を入れても、倍にならない
        $this->seedData($this->otherSaneiCompanyId);
        $this->assertEquals(1070, $this->dept($this->api('department.summary'))['total']['amounts'][2025]);
        $this->assertEquals(1070, $this->compare(['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025])['kpi']['amount']['b']);
    }
}
