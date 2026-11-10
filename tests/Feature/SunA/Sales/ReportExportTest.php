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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * 売上レポートのExcel出力（SUNASALES_PLAN1.md §19）。画面と同じ集計の結果が、そのままExcelになることを確かめる。
 *
 * データ（受注件数は各行1。名前は「ダミー商事X」など）:
 *   2024: X 1月100・2月200・3月300（部1）／Y 1月50（部2）／Z 2月400（第三部 第一課）
 *   2025: X 1月150・2月250（部1）／Y 1月80（部2）／Z 1月60・2月20（第一課）／V 2月10（第三部 第二課）／N 1月500（部1）
 *   登録のある月: 2024={1,2,3} 2025={1,2}（途中の年）
 */
class ReportExportTest extends TestCase
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

    private function seedData(int $companyId, string $nameOfX = 'ダミー商事X'): void
    {
        SunaCompanyContext::run($companyId, function () use ($nameOfX) {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => 'ダミー.xlsx', 'file_sha256' => hash('sha256', uniqid()), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            $rows = [
                ['X', 2024, 1, 100, 'dept1', null], ['X', 2024, 2, 200, 'dept1', null], ['X', 2024, 3, 300, 'dept1', null],
                ['Y', 2024, 1, 50, 'dept2', null], ['Z', 2024, 2, 400, 'dept3', 'dept3_sec1'],
                ['X', 2025, 1, 150, 'dept1', null], ['X', 2025, 2, 250, 'dept1', null],
                ['Y', 2025, 1, 80, 'dept2', null],
                ['Z', 2025, 1, 60, 'dept3', 'dept3_sec1'], ['Z', 2025, 2, 20, 'dept3', 'dept3_sec1'],
                ['V', 2025, 2, 10, 'dept3', 'dept3_sec2'], ['N', 2025, 1, 500, 'dept1', null],
            ];
            foreach ($rows as [$c, $year, $month, $amount, $dept, $section]) {
                Fact::create(['client_key' => $c, 'client_name' => $c === 'X' ? $nameOfX : "ダミー商事{$c}", 'sales_year' => $year, 'sales_month' => $month, 'department_key' => $dept, 'section_key' => $section, 'amount' => $amount, 'order_count' => 1, 'import_id' => $import->id]);
            }
        });
    }

    private function viewer(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);

        return $user;
    }

    /** 出力されたExcelを読み戻す */
    private function book($response): Spreadsheet
    {
        $path = $this->tmp[] = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path);
    }

    private function export(string $name, array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->viewer())->get(route("suna.sales.report.export.{$name}", $query));
    }

    /** シートの全セル（数式は計算せず、書かれた値のまま） */
    private function cells(Spreadsheet $book, string $sheet): array
    {
        return $book->getSheetByName($sheet)->toArray(null, false, false, false);
    }

    /** 先頭列が $label の行 */
    private function rowOf(array $cells, string $label): ?array
    {
        foreach ($cells as $row) {
            if (($row[0] ?? null) === $label) {
                return $row;
            }
        }

        return null;
    }

    public function test_annual_export_has_the_same_numbers_as_the_screen()
    {
        $res = $this->export('annual', ['year' => 2025])->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString(rawurlencode('年次分析'), $res->headers->get('Content-Disposition'));
        $book = $this->book($res);

        $this->assertSame(['サマリー', '月別売上', '部門別', '得意先別'], $book->getSheetNames());

        // タイトル・条件・出力日時
        $summary = $this->cells($book, 'サマリー');
        $this->assertSame('売上レポート 年次分析（サマリー）', $summary[0][0]);
        $this->assertContains('年: 2025年', array_column($summary, 0));
        $this->assertContains('集計期間: 1〜2月累計', array_column($summary, 0));

        // 画面（API）と同じ数字
        $api = $this->actingAs($this->viewer())->getJson(route('suna.sales.report.api.annual.summary', ['year' => 2025, 'years' => 5]))->json();
        $sales = $this->rowOf($summary, '売上（円）');
        $this->assertEquals($api['kpi']['amount']['value'], $sales[1]);
        $this->assertEquals(1070, $sales[1]);       // 当年 1〜2月
        $this->assertEquals(750, $sales[2]);        // 前年の同じ月（1〜2月。3月の300は含めない）
        $this->assertEquals(320, $sales[3]);
        $this->assertEquals(42.7, $sales[4]);
        $this->assertEquals(5, $this->rowOf($summary, '取引社数（名寄せ後）')[1]);

        // 数値のセルで、桁区切りの書式
        $cell = $book->getSheetByName('サマリー')->getCell('B' . (array_search($sales, $summary, true) + 1));
        $this->assertSame('n', $cell->getDataType());
        $this->assertSame('#,##0', $cell->getStyle()->getNumberFormat()->getFormatCode());

        // 月別売上: 登録のない月は空欄（0ではない）
        $monthly = $this->cells($book, '月別売上');
        $y2025 = $this->rowOf($monthly, '2025年');
        $this->assertEquals(790, $y2025[1]);        // 150+80+60+500
        $this->assertEquals(280, $y2025[2]);        // 250+20+10
        $this->assertNull($y2025[3]);

        // 部門別: 第三部（全体）と、その下の課
        $dept = $this->cells($book, '部門別');
        $this->assertEquals(90, $this->rowOf($dept, '営業第三部')[1]);
        $this->assertEquals(80, $this->rowOf($dept, '　└ 第一課')[1]);
        $this->assertEquals(10, $this->rowOf($dept, '　└ 第二課')[1]);

        // 得意先別: 全件。終了した得意先は区分つき
        $clients = $this->cells($book, '得意先別');
        $this->assertSame('新規', $this->rowOf($clients, 'ダミー商事N')[6]);
        $this->assertSame('継続', $this->rowOf($clients, 'ダミー商事X')[6]);
        $z = $this->rowOf($clients, 'ダミー商事Z');
        $this->assertEquals(80, $z[1]);
    }

    public function test_every_export_returns_a_workbook_with_the_expected_sheets()
    {
        $cases = [
            ['same_month', ['month' => 1], ['年別', '部門別×年']],
            ['department', [], ['売上', '構成比', '前年比']],
            ['compare', ['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025], ['サマリー', '部門別', '得意先別']],
            ['compare', ['mode' => 'month', 'year_a' => 2024, 'month_a' => 3, 'year_b' => 2025, 'month_b' => 2], ['サマリー', '部門別', '得意先別']],
            ['client', ['client_key' => 'X'], ['年別', '月別', '担当部門の履歴']],
        ];
        foreach ($cases as [$name, $query, $sheets]) {
            $book = $this->book($this->export($name, $query)->assertOk());
            $this->assertSame($sheets, $book->getSheetNames(), $name);
        }
    }

    public function test_same_month_department_and_compare_numbers()
    {
        $jan = $this->book($this->export('same_month', ['month' => 1]));
        $y = $this->rowOf($this->cells($jan, '年別'), '2025年1月');
        $this->assertEquals(790, $y[1]);   // 150+80+60+500
        $matrix = $this->cells($jan, '部門別×年');
        $this->assertEquals(650, $this->rowOf($matrix, '営業第一部')[2]);      // 2025年1月: X150 + N500

        $dept = $this->book($this->export('department'));
        $sales = $this->cells($dept, '売上');
        $this->assertEquals(1070, $this->rowOf($sales, '全部門合計')[2]);       // 同じ月まで（1〜2月）の2025年
        $this->assertContains('集計の期間: 各年 1〜2月累計', array_column($sales, 0));
        $this->assertEquals(8.4, $this->rowOf($this->cells($dept, '構成比'), '営業第三部')[2]);

        $cmp = $this->cells($this->book($this->export('compare', ['mode' => 'year', 'year_a' => 2024, 'year_b' => 2025])), 'サマリー');
        $this->assertContains('期間A: 2024年 1〜2月累計', array_column($cmp, 0));
        $this->assertEquals(750, $this->rowOf($cmp, '売上（円）')[1]);
        $this->assertEquals(1070, $this->rowOf($cmp, '売上（円）')[2]);
        $this->assertEquals(42.7, $this->rowOf($cmp, '売上（円）')[4]);

        $cl = $this->book($this->export('client', ['client_key' => 'X']));
        $this->assertEquals(400, $this->rowOf($this->cells($cl, '年別'), '2025年')[1]);
        $this->assertNull($this->rowOf($this->cells($cl, '月別'), '2025年')[3]);
    }

    public function test_compare_export_lists_ended_clients_on_their_own_sheet()
    {
        // 2024年3月にだけ売上のある得意先 W は、2025年2月と比べると「終了」
        SunaCompanyContext::run($this->saneiCompanyId, function () {
            Fact::create(['client_key' => 'W', 'client_name' => 'ダミー商事W', 'sales_year' => 2024, 'sales_month' => 3, 'department_key' => 'dept1', 'amount' => 30, 'order_count' => 1, 'import_id' => Import::query()->value('id')]);
        });
        $book = $this->book($this->export('compare', ['mode' => 'month', 'year_a' => 2024, 'month_a' => 3, 'year_b' => 2025, 'month_b' => 2]));

        $this->assertSame(['サマリー', '部門別', '得意先別', '終了した得意先'], $book->getSheetNames());
        $ended = $this->rowOf($this->cells($book, '終了した得意先'), 'ダミー商事W');
        $this->assertEquals(30, $ended[1]);
    }

    public function test_grouped_clients_are_exported_as_one()
    {
        SunaCompanyContext::run($this->saneiCompanyId, fn () => app(ClientGroupService::class)->create('XY商事', ['X', 'Y'], 1));

        $clients = $this->cells($this->book($this->export('annual', ['year' => 2025])), '得意先別');
        $names = array_column($clients, 0);
        $this->assertContains('XY商事', $names);
        $this->assertNotContains('ダミー商事X', $names);
        $this->assertNotContains('ダミー商事Y', $names);
        $this->assertEquals(480, $this->rowOf($clients, 'XY商事')[1]);   // X(150+250) + Y(80)

        $detail = $this->cells($this->book($this->export('client', ['client_key' => 'g:' . SunaCompanyContext::run($this->saneiCompanyId, fn () => \App\Models\SunA\Sales\ClientGroup::query()->value('id'))])), '年別');
        $this->assertTrue(collect(array_column($detail, 0))->contains(fn ($v) => is_string($v) && str_starts_with($v, '名寄せ済み（2社をまとめて集計）')));
    }

    public function test_text_is_never_interpreted_as_a_formula()
    {
        // 得意先名が数式として始まっていても、文字のまま書き込まれる
        SunaCompanyContext::run($this->saneiCompanyId, fn () => Fact::query()->where('client_key', 'X')->update(['client_name' => '=1+1']));

        $book = $this->book($this->export('annual', ['year' => 2025]));
        $sheet = $book->getSheetByName('得意先別');
        $found = false;
        foreach ($sheet->getRowIterator() as $row) {
            $cell = $sheet->getCell('A' . $row->getRowIndex());
            if ($cell->getValue() === '=1+1') {
                $found = true;
                $this->assertSame('s', $cell->getDataType());   // 文字列
                $this->assertFalse($cell->isFormula());
            }
        }
        $this->assertTrue($found);
    }

    public function test_audit_log_has_screen_and_row_count_only()
    {
        $this->export('annual', ['year' => 2025, 'dept' => 'dept1'])->assertOk();
        $this->export('client', ['client_key' => 'X'])->assertOk();

        $logs = SunaCompanyContext::run($this->saneiCompanyId, fn () => AuditLog::query()->where('action', 'export')->orderBy('id')->get());
        $this->assertSame(['annual', 'client'], $logs->pluck('target_type')->all());
        $this->assertSame(['rows'], array_keys($logs[0]->context));
        $this->assertGreaterThan(0, $logs[0]->context['rows']);
        $json = json_encode($logs->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('ダミー商事', $json);   // 得意先名
        $this->assertStringNotContainsString('dept1', $json);        // 条件の内容
        $this->assertStringNotContainsString('2025', str_replace('created_at', '', json_encode(array_map(fn ($l) => $l['context'], $logs->toArray()))));
    }

    public function test_permission_and_invalid_conditions()
    {
        $nobody = $this->saneiUser('user');
        foreach ([['annual', ['year' => 2025]], ['same_month', ['month' => 1]], ['department', []], ['client', ['client_key' => 'X']]] as [$name, $q]) {
            $this->export($name, $q, $nobody)->assertForbidden();
        }
        $this->assertSame(0, SunaCompanyContext::run($this->saneiCompanyId, fn () => AuditLog::query()->count()));

        $viewer = $this->viewer();
        $get = fn (string $name, array $q) => $this->actingAs($viewer)->getJson(route("suna.sales.report.export.{$name}", $q));
        $get('annual', ['year' => 2025, 'dept' => 'nope'])->assertStatus(422);
        $get('annual', [])->assertStatus(422);
        $get('compare', ['mode' => 'month', 'year_a' => 2024, 'year_b' => 2025])->assertStatus(422);
        $get('department', ['basis' => 'nope'])->assertStatus(422);

        // データが無い条件は、メッセージつきの422（ファイルは作らない・記録もしない）
        $r = $this->actingAs($this->viewer())->getJson(route('suna.sales.report.export.annual', ['year' => 2030]));
        $r->assertStatus(422);
        $this->assertStringContainsString('2030年', $r->json('message'));
        $this->actingAs($this->viewer())->getJson(route('suna.sales.report.export.client', ['client_key' => 'nope']))->assertStatus(422);
        $this->actingAs($this->viewer())->getJson(route('suna.sales.report.export.compare', ['mode' => 'month', 'year_a' => 2025, 'month_a' => 3, 'year_b' => 2025, 'month_b' => 2]))->assertStatus(422);
        $this->assertSame(0, SunaCompanyContext::run($this->saneiCompanyId, fn () => AuditLog::query()->count()));
    }

    public function test_other_company_data_is_not_exported()
    {
        $this->seedData($this->otherSaneiCompanyId, '別会社の商事');

        $clients = $this->cells($this->book($this->export('annual', ['year' => 2025])), '得意先別');
        $this->assertNotContains('別会社の商事', array_column($clients, 0));
        $this->assertEquals(1070, $this->rowOf($this->cells($this->book($this->export('annual', ['year' => 2025])), 'サマリー'), '売上（円）')[1]);
    }
}
