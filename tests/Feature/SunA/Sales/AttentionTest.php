<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\ClientGroupService;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\SunaCompanyContext;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * 売上レポートの注目ポイント（SUNASALES_PLAN1.md §20）。ダミーデータの数値を手計算と照合する。
 *
 * データ（部門は断りがなければ部1）:
 *   A: 2024/9=500 ／ B: 2025/3=300（部2） ／ C: 2024/9=40・2025/9=100・2025/12=100 ／ D: 2024/1=200
 *   E: 2025/8=50 ／ G: 2024/3=100・2025/3=400 ／ H: 2024/12=900・2025/12=100 ／ F1: 2024/8=100 ／ F2: 2024/9=100
 * 2025年の登録月={3,8,9,12} → 前年の同じ月と比べる。直近の登録月=2025/12。
 *   増加: G +300, C +160 ／ 減少: H -800 ／ 新規: B 300, E 50 ／ 終了: A, F1, F2
 * 休眠（直近=2025/12）:
 *   3か月（止まり=2025/10〜12／前=2024/10〜2025/9）: G 400, B 300, E 50
 *   6か月（止まり=2025/7〜12／前=2024/7〜2025/6）:  A 500, G 400, B 300, F1 100, F2 100
 *   12か月（止まり=2025/1〜12／前=2024/1〜12）:       A 500, D 200, F1 100, F2 100
 */
class AttentionTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    /** @var array<int, string> */
    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
        $this->seedData($this->saneiCompanyId);
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function seedData(int $companyId): void
    {
        SunaCompanyContext::run($companyId, function () use ($companyId) {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => 'ダミー.xlsx', 'file_sha256' => hash('sha256', uniqid()), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            $rows = [
                ['A', 2024, 9, 500, 'dept1'], ['B', 2025, 3, 300, 'dept2'],
                ['C', 2024, 9, 40, 'dept1'], ['C', 2025, 9, 100, 'dept1'], ['C', 2025, 12, 100, 'dept1'],
                ['D', 2024, 1, 200, 'dept1'], ['E', 2025, 8, 50, 'dept1'],
                ['G', 2024, 3, 100, 'dept1'], ['G', 2025, 3, 400, 'dept1'],
                ['H', 2024, 12, 900, 'dept1'], ['H', 2025, 12, 100, 'dept1'],
                ['F1', 2024, 8, 100, 'dept1'], ['F2', 2024, 9, 100, 'dept1'],
            ];
            foreach ($rows as [$c, $year, $month, $amount, $dept]) {
                Fact::create(['client_key' => $c, 'client_name' => "ダミー商事{$c}", 'sales_year' => $year, 'sales_month' => $month, 'department_key' => $dept, 'section_key' => null, 'amount' => $amount, 'order_count' => 1, 'import_id' => $import->id]);
            }
        });
    }

    private function viewer(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);

        return $user;
    }

    private function summary(array $query = [])
    {
        return $this->actingAs($this->viewer())->getJson(route('suna.sales.report.api.attention.summary', $query + ['year' => 2025]));
    }

    /** @return array<int, string> 得意先名（ダミー商事を除いた記号）の並び */
    private function names(array $rows): array
    {
        return array_map(fn ($r) => str_replace('ダミー商事', '', $r['label']), $rows);
    }

    public function test_increase_and_decrease_are_the_top_differences_in_order()
    {
        $s = $this->summary()->assertOk()->json();

        $this->assertTrue($s['comparison_available']);
        $this->assertSame(['G', 'C'], $this->names($s['increase']['rows']));
        $this->assertEquals([300, 160], array_column($s['increase']['rows'], 'diff'));
        $this->assertSame(['H'], $this->names($s['decrease']['rows']));
        $this->assertEquals(-800, $s['decrease']['rows'][0]['diff']);
        $this->assertSame(2, $s['increase']['total_count']);
    }

    public function test_new_clients_are_listed_by_current_amount()
    {
        $s = $this->summary()->assertOk()->json();

        $this->assertSame(['B', 'E'], $this->names($s['new']['rows']));
        $this->assertEquals([300, 50], array_column($s['new']['rows'], 'amount'));
    }

    public function test_increase_and_decrease_are_limited_to_the_top_ten()
    {
        SunaCompanyContext::run($this->saneiCompanyId, function () {
            $import = Import::first();
            foreach (range(1, 12) as $i) {
                foreach ([[2024, 100], [2025, 100 + $i]] as [$y, $a]) {
                    Fact::create(['client_key' => "U{$i}", 'client_name' => "上げ商事{$i}", 'sales_year' => $y, 'sales_month' => 12, 'department_key' => 'dept1', 'section_key' => null, 'amount' => $a, 'order_count' => 1, 'import_id' => $import->id]);
                }
            }
        });
        $s = $this->summary()->assertOk()->json();

        $this->assertCount(10, $s['increase']['rows']);
        $this->assertSame(14, $s['increase']['total_count']);   // U1〜U12（+1〜+12）と G(+300)・C(+160)
        $this->assertSame(['ダミー商事G', 'ダミー商事C', '上げ商事12', '上げ商事11', '上げ商事10', '上げ商事9', '上げ商事8', '上げ商事7', '上げ商事6', '上げ商事5'], array_column($s['increase']['rows'], 'label'));
    }

    public function test_dormant_clients_for_three_six_and_twelve_months()
    {
        $three = $this->summary(['dormant_months' => 3])->assertOk()->json()['dormant'];
        $this->assertTrue($three['available']);
        $this->assertSame(['G', 'B', 'E'], $this->names($three['rows']));
        $this->assertEquals([400, 300, 50], array_column($three['rows'], 'prior_amount'));
        $this->assertSame('2025年12月', $three['latest_label']);
        $this->assertSame('2025年10月〜2025年12月', $three['silent_label']);
        $this->assertSame('2024年10月〜2025年9月', $three['prior_label']);

        $six = $this->summary()->assertOk()->json()['dormant'];   // 既定は6か月
        $this->assertSame(6, $six['months']);
        $this->assertSame(['A', 'G', 'B', 'F1', 'F2'], $this->names($six['rows']));
        $this->assertSame('2024年9月', $six['rows'][0]['last_label']);
        $this->assertSame(15, $six['rows'][0]['silent_months']);   // 2025/12 - 2024/9

        $twelve = $this->summary(['dormant_months' => 12])->assertOk()->json()['dormant'];
        $this->assertSame(['A', 'D', 'F1', 'F2'], $this->names($twelve['rows']));
    }

    public function test_dormant_ignores_clients_with_sales_in_the_silent_period_in_any_department()
    {
        // 部2で絞っても、止まっている期間の売上は全部門で見る。B（部2）だけが残る（6か月）
        $six = $this->summary(['dept' => 'dept2'])->assertOk()->json()['dormant'];
        $this->assertSame(['B'], $this->names($six['rows']));

        // B が止まっている期間（2025/7〜12）に、別の部門で売上を立てたら、休眠ではなくなる
        SunaCompanyContext::run($this->saneiCompanyId, fn () => Fact::create(['client_key' => 'B', 'client_name' => 'ダミー商事B', 'sales_year' => 2025, 'sales_month' => 10, 'department_key' => 'dept1', 'section_key' => null, 'amount' => 10, 'order_count' => 1, 'import_id' => Import::first()->id]));
        $six = $this->summary(['dept' => 'dept2'])->assertOk()->json()['dormant'];
        $this->assertSame([], $six['rows']);
    }

    public function test_grouped_clients_are_one_dormant_row()
    {
        SunaCompanyContext::run($this->saneiCompanyId, fn () => app(ClientGroupService::class)->create('F商事', ['F1', 'F2'], 1));
        $six = $this->summary()->assertOk()->json()['dormant'];

        $this->assertSame(['ダミー商事A', 'ダミー商事G', 'ダミー商事B', 'F商事'], array_column($six['rows'], 'label'));
        $this->assertEquals(200, $six['rows'][3]['prior_amount']);
    }

    public function test_dormant_says_data_is_missing_when_the_range_is_too_short()
    {
        // D（2024/1）を消すと、登録は 2024/3 から。6か月の判定には 2024/7 以前からの登録が必要なので足りる…が、12か月には 2024/1 が必要
        $twelve = $this->summary(['dormant_months' => 12])->assertOk()->json()['dormant'];
        $this->assertTrue($twelve['available']);

        SunaCompanyContext::run($this->saneiCompanyId, fn () => Fact::query()->whereIn('client_key', ['D'])->delete());
        $twelve = $this->summary(['dormant_months' => 12])->assertOk()->json()['dormant'];
        $this->assertFalse($twelve['available']);
        $this->assertStringContainsString('データが足りません', $twelve['message']);
        $this->assertSame([], $twelve['rows']);

        $six = $this->summary()->assertOk()->json()['dormant'];
        $this->assertTrue($six['available']);
    }

    public function test_first_year_has_no_comparison_but_still_shows_dormant()
    {
        $s = $this->summary(['year' => 2024])->assertOk()->json();

        $this->assertFalse($s['comparison_available']);
        $this->assertSame([], $s['increase']);
        $this->assertSame([], $s['new']);
        $this->assertTrue($s['dormant']['available']);
    }

    public function test_validation_permission_and_company_isolation()
    {
        $this->summary(['dormant_months' => 5])->assertStatus(422);
        $this->summary(['dept' => 'nonexistent'])->assertStatus(422);

        $outsider = $this->saneiUser('user');
        $this->actingAs($outsider)->getJson(route('suna.sales.report.api.attention.summary', ['year' => 2025]))->assertForbidden();
        $this->actingAs($outsider)->get(route('suna.sales.report.attention'))->assertForbidden();

        // 別会社のデータは出ない
        SunaCompanyContext::run($this->otherSaneiCompanyId, function () {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => 'x.xlsx', 'file_sha256' => hash('sha256', uniqid()), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            Fact::create(['client_key' => 'Q', 'client_name' => '別会社商事', 'sales_year' => 2024, 'sales_month' => 9, 'department_key' => 'dept1', 'section_key' => null, 'amount' => 99999, 'order_count' => 1, 'import_id' => $import->id]);
        });
        $this->assertNotContains('別会社商事', array_column($this->summary()->json()['dormant']['rows'], 'label'));
    }

    public function test_page_renders_for_a_viewer()
    {
        $this->actingAs($this->viewer())->get(route('suna.sales.report.attention'))->assertOk();
    }

    public function test_excel_export_has_all_rows_and_records_only_screen_and_row_count()
    {
        $res = $this->actingAs($this->viewer())->get(route('suna.sales.report.export.attention', ['year' => 2025, 'dormant_months' => 6]));
        $res->assertOk();
        $path = $this->tmp[] = tempnam(sys_get_temp_dir(), 'att') . '.xlsx';
        file_put_contents($path, $res->streamedContent());
        $book = IOFactory::load($path);

        $this->assertSame(['大きく増えた得意先', '大きく減った得意先', '新規の得意先', '休眠の得意先'], $book->getSheetNames());
        $dormant = array_column($book->getSheetByName('休眠の得意先')->toArray(null, false, false, false), 0);
        foreach (['ダミー商事A', 'ダミー商事G', 'ダミー商事B', 'ダミー商事F1', 'ダミー商事F2'] as $name) {
            $this->assertContains($name, $dormant);
        }
        $new = array_column($book->getSheetByName('新規の得意先')->toArray(null, false, false, false), 0);
        $this->assertContains('ダミー商事B', $new);
        $this->assertContains('ダミー商事E', $new);

        $log = SunaCompanyContext::run($this->saneiCompanyId, fn () => AuditLog::query()->where('action', 'export')->where('target_type', 'attention')->latest('id')->first());
        $this->assertNotNull($log);
        $this->assertSame(['rows'], array_keys($log->context));
    }
}
