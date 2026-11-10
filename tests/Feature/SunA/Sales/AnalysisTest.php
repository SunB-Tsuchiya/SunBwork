<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\SunaCompanyContext;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * 売上レポートの分析（年次分析・同月比較・得意先分析）。ダミーデータの数値を手計算と照合する（SUNASALES_PLAN1.md §15）。
 *
 * データ（各行の受注件数は1）:
 *   2023: X 1月=10（部1）                           … データの最初の年
 *   2024: X 1月100・2月200・3月300（部1）／Y 1月50（部2）／Z 2月400（部1）
 *   2025: X 1月150・2月250（部1）／Y 1月80・2月20（第三部 第一課）／W 1月500（部1）
 *   登録のある月: 2023={1} 2024={1,2,3} 2025={1,2}（2025は途中の年）
 */
class AnalysisTest extends TestCase
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
                ['X', 2023, 1, 10, 'dept1', null],
                ['X', 2024, 1, 100, 'dept1', null], ['X', 2024, 2, 200, 'dept1', null], ['X', 2024, 3, 300, 'dept1', null],
                ['Y', 2024, 1, 50, 'dept2', null],
                ['Z', 2024, 2, 400, 'dept1', null],
                ['X', 2025, 1, 150, 'dept1', null], ['X', 2025, 2, 250, 'dept1', null],
                ['Y', 2025, 1, 80, 'dept3', 'dept3_sec1'], ['Y', 2025, 2, 20, 'dept3', 'dept3_sec1'],
                ['W', 2025, 1, 500, 'dept1', null],
            ];
            foreach ($rows as [$client, $year, $month, $amount, $dept, $section]) {
                Fact::create(['client_key' => $client, 'client_name' => "ダミー商事{$client}", 'sales_year' => $year, 'sales_month' => $month, 'department_key' => $dept, 'section_key' => $section, 'amount' => $amount, 'order_count' => 1, 'import_id' => $import->id]);
            }
        });
    }

    private function viewer(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);
        $this->inSalesArea($user);

        return $user;
    }

    private function api(string $name, array $query)
    {
        return $this->actingAs($this->viewer())->getJson(route("suna.sales.report.api.{$name}", $query))->assertOk()->json();
    }

    public function test_annual_kpi_compares_registered_months_with_the_same_months_of_the_prior_year()
    {
        $s = $this->api('annual.summary', ['year' => 2025]);

        $this->assertSame([1, 2], $s['period']['months']);
        $this->assertTrue($s['period']['is_partial']);
        $this->assertSame('1〜2月累計', $s['period']['label']);
        // 当年 1〜2月 = 150+250+80+20+500 = 1000／前年の同じ月 = 100+200+50+400 = 750（3月の300は含めない）
        $this->assertEquals(1000, $s['kpi']['amount']['value']);
        $this->assertEquals(750, $s['kpi']['amount']['prior']);
        $this->assertEquals(250, $s['kpi']['amount']['diff']);
        $this->assertSame(33.3, $s['kpi']['amount']['rate']);
        $this->assertSame(5, $s['kpi']['order_count']['value']);
        $this->assertSame(4, $s['kpi']['order_count']['prior']);
        $this->assertEquals(200, $s['kpi']['avg_order_price']['value']);   // 1000 ÷ 5
        $this->assertEquals(188, $s['kpi']['avg_order_price']['prior']);   // 750 ÷ 4 = 187.5 → 188
        $this->assertSame(3, $s['kpi']['client_count']['value']);          // X, Y, W
        $this->assertSame(3, $s['kpi']['client_count']['prior']);          // X, Y, Z
    }

    public function test_first_year_has_no_comparison()
    {
        $s = $this->api('annual.summary', ['year' => 2023]);

        $this->assertFalse($s['prior']['available']);
        $this->assertEquals(10, $s['kpi']['amount']['value']);
        $this->assertNull($s['kpi']['amount']['prior']);
        $this->assertNull($s['kpi']['amount']['diff']);
        $this->assertNull($s['kpi']['amount']['rate']);

        // 新規・終了・増減の判定は出さない
        foreach (['new', 'ended', 'increase', 'decrease'] as $mode) {
            $this->assertSame(0, $this->api('annual.clients', ['year' => 2023, 'mode' => $mode])['total_count'], $mode);
        }
        $all = $this->api('annual.clients', ['year' => 2023]);
        $this->assertFalse($all['comparison_available']);
        $this->assertSame(1, $all['total_count']);
        $this->assertNull($all['rows'][0]['diff']);
    }

    public function test_client_ranking_modes_new_ended_increase()
    {
        $names = fn (string $mode) => collect($this->api('annual.clients', ['year' => 2025, 'mode' => $mode])['rows'])->pluck('label')->all();

        $this->assertSame(['ダミー商事W', 'ダミー商事X', 'ダミー商事Y'], $names('all'));   // 金額順 500, 400, 100
        $this->assertSame(['ダミー商事W'], $names('new'));
        $this->assertSame(['ダミー商事Z'], $names('ended'));
        $this->assertSame(['ダミー商事X', 'ダミー商事Y'], $names('increase'));            // +100, +50（金額順の並び）
        $this->assertSame([], $names('decrease'));

        $rows = collect($this->api('annual.clients', ['year' => 2025, 'mode' => 'all'])['rows'])->keyBy('client_key');
        $this->assertEquals(100, $rows['X']['diff']);
        $this->assertSame(33.3, $rows['X']['rate']);
        $this->assertNull($rows['W']['rate']);                    // 前年が0円なので増減率は出さない
        $this->assertEquals(50, $rows['W']['share_pct']);         // 500 ÷ 1000
        $this->assertSame('new', $rows['W']['status']);
        $this->assertEquals(1000, $this->api('annual.clients', ['year' => 2025])['total_amount']);
    }

    public function test_client_ranking_sort_keyword_and_paging()
    {
        $byDiff = collect($this->api('annual.clients', ['year' => 2025, 'mode' => 'all', 'sort' => 'diff', 'direction' => 'asc'])['rows'])->pluck('client_key')->all();
        $this->assertSame(['Y', 'X', 'W'], $byDiff); // 差額 +50, +100, +500

        $this->assertSame(['X'], collect($this->api('annual.clients', ['year' => 2025, 'keyword' => '商事X'])['rows'])->pluck('client_key')->all());

        $page2 = $this->api('annual.clients', ['year' => 2025, 'limit' => 2, 'page' => 2]);
        $this->assertSame(3, $page2['total_count']);
        $this->assertCount(1, $page2['rows']);
    }

    public function test_department_filter_uses_the_department_of_each_year()
    {
        // 第一部のみ: 当年 X400+W500=900／前年 X300+Z400=700
        $dept1 = $this->api('annual.summary', ['year' => 2025, 'dept' => 'dept1']);
        $this->assertEquals(900, $dept1['kpi']['amount']['value']);
        $this->assertEquals(700, $dept1['kpi']['amount']['prior']);

        // 第三部（課を含む）: 当年 Y=100／前年は 0（Y は前年は第二部だった）→ 第三部では「新規」
        $dept3 = $this->api('annual.summary', ['year' => 2025, 'dept' => 'dept3']);
        $this->assertEquals(100, $dept3['kpi']['amount']['value']);
        $this->assertEquals(0, $dept3['kpi']['amount']['prior']);
        $this->assertSame(['ダミー商事Y'], collect($this->api('annual.clients', ['year' => 2025, 'dept' => 'dept3', 'mode' => 'new'])['rows'])->pluck('label')->all());

        // 第三部の第一課も同じ。第二課・直轄は 0 件
        $this->assertEquals(100, $this->api('annual.summary', ['year' => 2025, 'dept' => 'dept3_sec1'])['kpi']['amount']['value']);
        $this->assertEquals(0, $this->api('annual.summary', ['year' => 2025, 'dept' => 'dept3_sec2'])['kpi']['amount']['value']);
        $this->assertEquals(0, $this->api('annual.summary', ['year' => 2025, 'dept' => 'dept3_direct'])['kpi']['amount']['value']);

        // 前年は第二部だった Y は、第二部の「終了」
        $this->assertSame(['ダミー商事Y'], collect($this->api('annual.clients', ['year' => 2025, 'dept' => 'dept2', 'mode' => 'ended'])['rows'])->pluck('label')->all());
    }

    public function test_unknown_department_is_rejected()
    {
        $this->actingAs($this->viewer())->getJson(route('suna.sales.report.api.annual.summary', ['year' => 2025, 'dept' => 'nope']))->assertStatus(422);
        $this->actingAs($this->viewer())->getJson(route('suna.sales.report.api.annual.clients', ['year' => 2025, 'mode' => 'bogus']))->assertStatus(422);
    }

    public function test_monthly_series_marks_unregistered_months_as_null()
    {
        $monthly = collect($this->api('annual.summary', ['year' => 2025, 'years' => 2])['monthly'])->keyBy('year');

        $this->assertSame([2024, 2025], $monthly->keys()->all());
        $this->assertEquals([1 => 150, 2 => 600, 3 => 300], array_filter($monthly[2024]['months'], fn ($v) => $v !== null));
        $this->assertEquals([1 => 730, 2 => 270], array_filter($monthly[2025]['months'], fn ($v) => $v !== null));
        $this->assertNull($monthly[2025]['months'][3]);   // 2025年の3月は未登録（0円ではない）
        // 3年分: データのある年だけ（2023年も）
        $this->assertCount(3, $this->api('annual.summary', ['year' => 2025, 'years' => 3])['monthly']);
    }

    public function test_department_breakdown_is_not_affected_by_the_filter()
    {
        $rows = collect($this->api('annual.summary', ['year' => 2025, 'dept' => 'dept2'])['by_department'])->keyBy('key');

        $this->assertEquals(900, $rows['dept1']['amount']);
        $this->assertEquals(700, $rows['dept1']['prior_amount']);
        // 営業第三部は「全体」の行を持ち（課・直轄の合計）、その下に課・直轄の行が並ぶ
        $this->assertEquals(100, $rows['dept3']['amount']);
        $this->assertTrue($rows['dept3']['has_children']);
        $this->assertSame(0, $rows['dept3']['level']);
        $this->assertEquals(100, $rows['dept3_sec1']['amount']);
        $this->assertSame('第一課', $rows['dept3_sec1']['label']);
        $this->assertSame(1, $rows['dept3_sec1']['level']);
        $this->assertSame('dept3', $rows['dept3_sec1']['parent']);
        $this->assertSame(['dept1', 'dept2', 'dept3', 'dept3_sec1'], $rows->keys()->all());     // 部の直後に課。売上の無い課・直轄は出ない
        $this->assertSame(1, $rows['dept3']['client_count']);
        $this->assertEquals(10, $rows['dept3']['share_pct']);                                     // 100 ÷ 1000（課は部に含まれるので、二重に数えない）
        $this->assertEquals(0, $rows['dept2']['amount']);       // 前年だけある部門も並ぶ
        $this->assertEquals(50, $rows['dept2']['prior_amount']);
        $this->assertEquals(90, $rows['dept1']['share_pct']);
    }

    public function test_same_month_compares_years_and_excludes_unregistered_years()
    {
        $jan = $this->api('same_month.summary', ['month' => 1]);
        $years = collect($jan['years'])->keyBy('year');
        $this->assertSame([2023, 2024, 2025], $years->keys()->all());
        $this->assertEquals(10, $years[2023]['amount']);
        $this->assertNull($years[2023]['diff']);               // 最初の年は前年比なし
        $this->assertEquals(150, $years[2024]['amount']);
        $this->assertEquals(140, $years[2024]['diff']);
        $this->assertEquals(1400, $years[2024]['rate']);
        $this->assertEquals(730, $years[2025]['amount']);
        $this->assertSame(3, $years[2025]['client_count']);    // X, Y, W

        // 3月は2024年だけに登録 → 2023・2025年は除外（0円として並べない）
        $mar = $this->api('same_month.summary', ['month' => 3]);
        $this->assertSame([2024], collect($mar['years'])->pluck('year')->all());
        $this->assertNull($mar['years'][0]['diff']);

        // 部門別 × 年（1月）
        $matrix = collect($jan['by_department']['rows'])->keyBy('key');
        $this->assertEquals([2023 => 10, 2024 => 100, 2025 => 650], $matrix['dept1']['amounts']);
        $this->assertEquals([2023 => 0, 2024 => 50, 2025 => 0], $matrix['dept2']['amounts']);
        // 第三部は「全体」の行と、課の行（2025年1月は第一課 80）
        $this->assertEquals([2023 => 0, 2024 => 0, 2025 => 80], $matrix['dept3']['amounts']);
        $this->assertEquals([2023 => 0, 2024 => 0, 2025 => 80], $matrix['dept3_sec1']['amounts']);
        $this->assertTrue($matrix['dept3']['has_children']);
        $this->assertSame('dept3', $matrix['dept3_sec1']['parent']);

        // 登録の無い月
        $none = $this->api('same_month.summary', ['month' => 12]);
        $this->assertFalse($none['has_data']);
    }

    public function test_same_month_department_filter()
    {
        $years = collect($this->api('same_month.summary', ['month' => 1, 'dept' => 'dept3'])['years'])->keyBy('year');
        $this->assertEquals(80, $years[2025]['amount']);
        $this->assertEquals(0, $years[2024]['amount']);
    }

    public function test_client_search_and_detail()
    {
        $found = $this->api('client.search', ['keyword' => '商事'])['clients'];
        $this->assertSame(['X', 'W', 'Z', 'Y'], array_column($found, 'client_key')); // 売上の多い順（X=1010, W=500, Z=400, Y=250）
        $this->assertSame([], $this->api('client.search', ['keyword' => '存在しない'])['clients']);
        $this->assertSame(['Y'], array_column($this->api('client.search', ['keyword' => '商事Y'])['clients'], 'client_key'));

        $x = $this->api('client.detail', ['client_key' => 'X']);
        $this->assertSame('ダミー商事X', $x['name']);
        $this->assertSame([2023, 2024, 2025], array_column($x['years'], 'year'));
        $y2025 = collect($x['years'])->keyBy('year')[2025];
        $this->assertEquals(400, $y2025['amount']);
        $this->assertEquals(300, $y2025['prior_amount']);      // 2024年の1〜2月（その年の登録済みの月）
        $this->assertEquals(100, $y2025['diff']);
        $this->assertSame(33.3, $y2025['rate']);
        $this->assertNull($y2025['months'][3]);                // 未登録の月は null
        $this->assertEquals(150, $y2025['months'][1]['amount']);
        $this->assertSame([false, false, false], array_column($x['department_history'], 'changed'));

        // 担当部門が年で変わった得意先
        $y = $this->api('client.detail', ['client_key' => 'Y']);
        $this->assertSame(['営業第二部', '営業第三部 第一課'], array_column($y['department_history'], 'label'));
        $this->assertSame([false, true], array_column($y['department_history'], 'changed'));

        $this->actingAs($this->viewer())->getJson(route('suna.sales.report.api.client.detail', ['client_key' => 'nope']))->assertNotFound();
    }

    public function test_other_company_data_never_appears()
    {
        $this->seedData($this->otherSaneiCompanyId); // 同じ内容をもう1社に入れても、倍にならない
        $s = $this->api('annual.summary', ['year' => 2025]);
        $this->assertEquals(1000, $s['kpi']['amount']['value']);
        $this->assertCount(3, $this->api('annual.clients', ['year' => 2025])['rows']);
        $this->assertCount(4, $this->api('client.search', [])['clients']);
    }

    public function test_pages_render_and_require_permission()
    {
        $viewer = $this->viewer();
        foreach (['annual' => 'SunA/Sales/Annual', 'same_month' => 'SunA/Sales/SameMonth', 'client' => 'SunA/Sales/Client'] as $route => $component) {
            $this->actingAs($viewer)->get(route("suna.sales.report.{$route}"))->assertOk()
                ->assertInertia(fn ($p) => $p->component($component, false));
        }
        $this->actingAs($viewer)->get(route('suna.sales.report.annual'))
            ->assertInertia(fn ($p) => $p->where('initialYear', 2025)->where('years', [2023, 2024, 2025]));

        $other = $this->saneiUser('user');
        foreach (['annual', 'same_month', 'client'] as $route) {
            $this->actingAs($other)->get(route("suna.sales.report.{$route}"))->assertForbidden();
        }
        $this->actingAs($other)->getJson(route('suna.sales.report.api.annual.summary', ['year' => 2025]))->assertForbidden();
        $this->actingAs($other)->getJson(route('suna.sales.report.api.client.search'))->assertForbidden();
    }
}
