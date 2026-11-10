<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ImportChange;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\ClientSalesWorkbookWriter;
use App\Services\SunA\Sales\SunaCompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/** 売上レポートの取込（アップロード → 差分プレビュー → 確定）。行単位の追加・更新、入れ替え、会社分離、権限。SUNASALES_PLAN1.md §6 */
class ImportTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    /** @var array<int, string> */
    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function importer(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => true]);
        $this->inSalesArea($user);

        return $user;
    }

    private function row(string $client, int $year, string $dept, array $months): array
    {
        return ['client' => $client, 'year' => $year, 'department' => $dept, 'months' => $months];
    }

    private function upload(array $rows, array $options = []): UploadedFile
    {
        $path = $this->tmp[] = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new ClientSalesWorkbookWriter())->write($rows, $path, $options);

        return new UploadedFile($path, '得意先別.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function preview(User $user, array $rows, array $replaceYears = [], array $options = [])
    {
        return $this->actingAs($user)->post(route('suna.sales.report.api.import.preview'), ['file' => $this->upload($rows, $options), 'replace_years' => $replaceYears], ['Accept' => 'application/json']);
    }

    private function confirm(User $user, string $token, array $replaceYears = [])
    {
        return $this->actingAs($user)->postJson(route('suna.sales.report.api.import.confirm'), ['token' => $token, 'replace_years' => $replaceYears]);
    }

    /** プレビュー→確定を続けて行う */
    private function importRows(User $user, array $rows, array $replaceYears = []): array
    {
        $token = $this->preview($user, $rows, $replaceYears)->assertOk()->json('token');
        $this->confirm($user, $token, $replaceYears)->assertOk();

        return $this->facts();
    }

    /** @return array<string, array{amount: float, orders: int, dept: string, section: ?string}> "client|year|month" => 値 */
    private function facts(?int $companyId = null): array
    {
        return SunaCompanyContext::run($companyId ?? $this->saneiCompanyId, fn () => Fact::query()->orderBy('client_name')->orderBy('sales_year')->orderBy('sales_month')->get()
            ->mapWithKeys(fn ($f) => ["{$f->client_name}|{$f->sales_year}|{$f->sales_month}" => ['amount' => $f->amount, 'orders' => $f->order_count, 'dept' => $f->department_key, 'section' => $f->section_key]])->all());
    }

    public function test_preview_shows_the_plan_but_does_not_change_any_data()
    {
        $user = $this->importer();

        $response = $this->preview($user, [
            $this->row('テスト得意先001', 2024, '営業第一部', [1 => [1000.0, 2], 2 => [500.0, 1]]),
            $this->row('テスト得意先002', 2025, '営業第三部', [12 => [900.0, 4]]),
        ]);

        $response->assertOk();
        $response->assertJsonPath('can_confirm', true);
        $response->assertJsonPath('row_count', 2);
        $response->assertJsonPath('summary.months_added', 3);
        $response->assertJsonPath('summary.client_years_new', 2);
        $response->assertJsonPath('years', [2024, 2025]);
        $this->assertSame([], $this->facts()); // 確定するまで、データは変わらない
        SunaCompanyContext::set($this->saneiCompanyId);
        $this->assertSame(0, Import::query()->count());
    }

    public function test_confirm_imports_rows_maps_departments_and_records_history_without_personal_details()
    {
        $user = $this->importer();

        $facts = $this->importRows($user, [
            $this->row('テスト得意先001', 2024, '営業第一部', [1 => [1000.0, 2], 2 => [0.0, 0]]),
            $this->row('テスト得意先002', 2025, '営業第三部', [12 => [900.0, 4]]),               // 第三部の直轄
            $this->row('テスト得意先003', 2025, '営業第三部第二課', [3 => [50.0, 1]]),
        ]);

        $this->assertSame(4, count($facts));
        $this->assertSame(['amount' => 1000.0, 'orders' => 2, 'dept' => 'dept1', 'section' => null], $facts['テスト得意先001|2024|1']);
        $this->assertSame(['amount' => 0.0, 'orders' => 0, 'dept' => 'dept1', 'section' => null], $facts['テスト得意先001|2024|2']); // 0円は登録済み
        $this->assertSame(['amount' => 900.0, 'orders' => 4, 'dept' => 'dept3', 'section' => 'dept3_direct'], $facts['テスト得意先002|2025|12']);
        $this->assertSame('dept3_sec2', $facts['テスト得意先003|2025|3']['section']);

        SunaCompanyContext::set($this->saneiCompanyId);
        $import = Import::query()->firstOrFail();
        $this->assertSame($user->id, $import->imported_by);
        $this->assertSame([4, 0, 0, 0, 3], [$import->added_count, $import->changed_count, $import->removed_count, $import->unchanged_count, $import->row_count]);
        $this->assertSame(2024, $import->year_min);
        $this->assertSame(2025, $import->year_max);
        $this->assertSame(4, ImportChange::query()->where('import_id', $import->id)->where('kind', 'added')->count());

        // 監査ログは件数だけ（得意先名・金額・ファイル内容を入れない）
        $log = AuditLog::query()->where('action', 'import')->firstOrFail();
        $this->assertStringNotContainsString('テスト得意先', json_encode($log->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame(4, $log->context['months_added']);

        // 取込ファイルは保存しない（確定で削除）
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_reimport_updates_only_the_rows_in_the_file_and_keeps_the_rest()
    {
        $user = $this->importer();
        $this->importRows($user, [
            $this->row('テスト得意先001', 2024, '営業第一部', [1 => [1000.0, 2], 2 => [500.0, 1]]),
            $this->row('テスト得意先002', 2024, '営業第二部', [1 => [200.0, 1]]),
        ]);

        // 補完用の一部だけのファイル: 得意先001の2024年（金額変更・2月は空欄・3月追加）と、得意先003の2023年だけ
        $facts = $this->importRows($user, [
            $this->row('テスト得意先001', 2024, '営業第一部', [1 => [1100.0, 2], 3 => [70.0, 1]]),
            $this->row('テスト得意先003', 2023, '営業第一部', [5 => [10.0, 1]]),
        ]);

        $this->assertSame(1100.0, $facts['テスト得意先001|2024|1']['amount']);          // 金額を更新
        $this->assertArrayNotHasKey('テスト得意先001|2024|2', $facts);                    // ファイルで空欄になった月は削除
        $this->assertSame(70.0, $facts['テスト得意先001|2024|3']['amount']);            // 追加
        $this->assertSame(200.0, $facts['テスト得意先002|2024|1']['amount']);          // ファイルに無い行は残る
        $this->assertSame(10.0, $facts['テスト得意先003|2023|5']['amount']);

        SunaCompanyContext::set($this->saneiCompanyId);
        $import = Import::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame([2, 1, 1, 0], [$import->added_count, $import->changed_count, $import->removed_count, $import->unchanged_count]);
        $kinds = ImportChange::query()->where('import_id', $import->id)->get()->map(fn ($c) => "{$c->client_name}:{$c->sales_year}:{$c->sales_month}:{$c->kind}:{$c->field}:{$c->old_value}>{$c->new_value}")->sort()->values()->all();
        $this->assertContains('テスト得意先001:2024:1:changed:amount:1000>1100', $kinds);
        $this->assertContains('テスト得意先001:2024:2:removed:amount:500>', $kinds);
    }

    public function test_identical_reimport_changes_nothing_and_a_department_change_updates_all_months()
    {
        $user = $this->importer();
        $rows = [$this->row('テスト得意先001', 2025, '営業第一部', [1 => [100.0, 1], 2 => [200.0, 2]])];
        $this->importRows($user, $rows);

        $same = $this->preview($user, $rows)->assertOk();
        $same->assertJsonPath('summary.months_unchanged', 2);
        $same->assertJsonPath('summary.client_years_unchanged', 1);
        $same->assertJsonPath('summary.months_added', 0);

        // 担当部門だけが変わる（金額は同じ）→ その年の全月の担当部門が更新される
        $moved = $this->importRows($user, [$this->row('テスト得意先001', 2025, '営業第三部第一課', [1 => [100.0, 1], 2 => [200.0, 2]])]);
        $this->assertSame(['dept3', 'dept3_sec1'], [$moved['テスト得意先001|2025|1']['dept'], $moved['テスト得意先001|2025|1']['section']]);
        $this->assertSame(['dept3', 'dept3_sec1'], [$moved['テスト得意先001|2025|2']['dept'], $moved['テスト得意先001|2025|2']['section']]);
        SunaCompanyContext::set($this->saneiCompanyId);
        $this->assertSame(1, ImportChange::query()->where('field', 'department')->count());
    }

    public function test_replace_years_deletes_rows_missing_from_the_file_only_for_the_specified_years()
    {
        $user = $this->importer();
        $this->importRows($user, [
            $this->row('テスト得意先001', 2024, '営業第一部', [1 => [1.0, 1]]),
            $this->row('テスト得意先002', 2024, '営業第一部', [1 => [2.0, 1]]),
            $this->row('テスト得意先002', 2023, '営業第一部', [1 => [3.0, 1]]),
        ]);

        // 2024年を入れ替える: ファイルに無い得意先002の2024年は削除。2023年（ファイルに無い年）は指定しても対象外（丸ごと消さない）
        $facts = $this->importRows($user, [$this->row('テスト得意先001', 2024, '営業第一部', [1 => [1.0, 1]])], [2024, 2023]);

        $this->assertArrayHasKey('テスト得意先001|2024|1', $facts);
        $this->assertArrayNotHasKey('テスト得意先002|2024|1', $facts); // 入れ替えで削除
        $this->assertArrayHasKey('テスト得意先002|2023|1', $facts);    // 残る

        SunaCompanyContext::set($this->saneiCompanyId);
        $this->assertSame([2024], Import::query()->orderByDesc('id')->firstOrFail()->replace_years);
    }

    public function test_unknown_department_label_blocks_the_import()
    {
        $user = $this->importer();

        $response = $this->preview($user, [$this->row('テスト得意先001', 2025, '営業第四部', [1 => [1.0, 1]])]);

        $response->assertOk();
        $response->assertJsonPath('can_confirm', false);
        $this->assertStringContainsString('営業第四部', $response->json('errors.0'));
        $this->assertStringContainsString('登録されていません', $response->json('errors.0'));

        $this->confirm($user, $response->json('token'))->assertStatus(422);
        $this->assertSame([], $this->facts());
    }

    public function test_confirm_token_is_single_use_and_bound_to_the_user_and_company()
    {
        $user = $this->importer();
        $other = $this->importer();
        $token = $this->preview($user, [$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]])])->json('token');

        $this->confirm($other, $token)->assertStatus(422);   // 別の人は確定できない
        $this->confirm($user, $token)->assertOk();
        $this->confirm($user, $token)->assertStatus(422);    // 2回目は不可（取込済み）
        $this->confirm($user, 'nonexistent-token')->assertStatus(422);
        $this->assertSame(1, count($this->facts()));
    }

    public function test_discard_removes_the_temp_file_and_invalidates_the_token()
    {
        $user = $this->importer();
        $token = $this->preview($user, [$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]])])->json('token');
        $this->assertCount(1, Storage::disk('local')->allFiles());

        $this->actingAs($user)->postJson(route('suna.sales.report.api.import.discard'), ['token' => $token])->assertOk();

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->confirm($user, $token)->assertStatus(422);
    }

    public function test_invalid_files_are_rejected_and_leave_no_temp_file()
    {
        $user = $this->importer();

        // 別の形式のExcel
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->setCellValue('A1', '別の表');
        $path = $this->tmp[] = tempnam(sys_get_temp_dir(), 'bad') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        $response = $this->actingAs($user)->post(route('suna.sales.report.api.import.preview'), ['file' => new UploadedFile($path, 'x.xlsx', null, null, true)], ['Accept' => 'application/json']);
        $response->assertStatus(422);
        $this->assertStringContainsString('形式ではありません', $response->json('message'));
        $this->assertSame([], Storage::disk('local')->allFiles());

        // xlsx 以外
        $csv = $this->tmp[] = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
        file_put_contents($csv, 'a,b');
        $this->actingAs($user)->post(route('suna.sales.report.api.import.preview'), ['file' => new UploadedFile($csv, 'x.csv', null, null, true)], ['Accept' => 'application/json'])->assertStatus(422);
        // ファイル無し
        $this->actingAs($user)->postJson(route('suna.sales.report.api.import.preview'), [])->assertStatus(422);
    }

    public function test_same_file_reimport_is_flagged_as_duplicate_within_the_company_only()
    {
        $user = $this->importer();
        $file = $this->upload([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]])]);
        $send = fn () => $this->actingAs($user)->post(route('suna.sales.report.api.import.preview'), ['file' => new UploadedFile($file->getPathname(), '得意先別.xlsx', null, null, true)], ['Accept' => 'application/json']);

        $first = $send()->assertOk();
        $first->assertJsonPath('duplicate_file', false);
        $this->confirm($user, $first->json('token'))->assertOk();

        $send()->assertOk()->assertJsonPath('duplicate_file', true);

        // 別の会社では、同じファイルでも重複扱いにならない
        SunaCompanyContext::set($this->otherSaneiCompanyId);
        $this->assertSame(0, Import::query()->where('file_sha256', Import::withoutGlobalScopes()->value('file_sha256'))->count());
    }

    public function test_import_never_touches_another_companys_data()
    {
        $user = $this->importer();
        $this->importRows($user, [$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]])]);

        // 別会社の担当者として取り込む（部門マスタ・データは会社ごと）
        $otherUser = User::factory()->create(['user_role' => 'user', 'company_id' => $this->otherSaneiCompanyId]);
        ReportPermission::create(['user_id' => $otherUser->id, 'enabled' => true, 'can_import' => true]);
        $this->inSalesArea($otherUser);
        $this->importRows($otherUser, [$this->row('テスト得意先001', 2025, '営業第二部', [1 => [999.0, 9]])]);

        $mine = $this->facts($this->saneiCompanyId);
        $theirs = $this->facts($this->otherSaneiCompanyId);
        $this->assertSame(1.0, $mine['テスト得意先001|2025|1']['amount']);   // 影響なし
        $this->assertSame(999.0, $theirs['テスト得意先001|2025|1']['amount']);
        $this->assertSame('dept1', $mine['テスト得意先001|2025|1']['dept']);
        $this->assertSame('dept2', $theirs['テスト得意先001|2025|1']['dept']);
    }

    public function test_permissions_view_only_users_cannot_import_but_can_see_the_history()
    {
        $viewer = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $viewer->id, 'enabled' => true, 'can_import' => false]);
        $this->inSalesArea($viewer);
        $noAccess = $this->saneiUser('user');
        $super = User::factory()->create(['user_role' => 'superadmin']);

        $file = fn () => $this->upload([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]])]);
        $this->actingAs($viewer)->get(route('suna.sales.report.import.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('suna.sales.report.api.import.preview'), ['file' => $file()], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('suna.sales.report.api.import.confirm'), ['token' => 'x'])->assertForbidden();
        $this->actingAs($noAccess)->get(route('suna.sales.report.history.index'))->assertForbidden();

        $this->actingAs($viewer)->get(route('suna.sales.report.history.index'))->assertOk();
        $this->actingAs($super)->get(route('suna.sales.report.import.create'))->assertOk();
        $this->actingAs($this->importer())->get(route('suna.sales.report.import.create'))->assertOk();
    }

    public function test_history_lists_imports_and_changes_and_other_companys_import_is_not_found()
    {
        $user = $this->importer();
        $this->importRows($user, [$this->row('テスト得意先001', 2025, '営業第一部', [1 => [100.0, 1]])]);
        $this->importRows($user, [$this->row('テスト得意先001', 2025, '営業第一部', [1 => [150.0, 1]])]);

        SunaCompanyContext::set($this->saneiCompanyId);
        $latest = Import::query()->orderByDesc('id')->firstOrFail();
        $oldest = Import::query()->orderBy('id')->firstOrFail();
        SunaCompanyContext::clear();

        $page = $this->actingAs($user)->get(route('suna.sales.report.history.index'))->assertOk();
        $imports = $page->original->getData()['page']['props']['imports'];
        $this->assertSame([$latest->id, $oldest->id], array_column($imports, 'id')); // 新しい順
        $this->assertSame(1, $imports[0]['changed_count']);

        $changes = $this->actingAs($user)->getJson(route('suna.sales.report.api.history.changes', ['import' => $latest->id]));
        $changes->assertOk();
        $changes->assertJsonPath('changes.0.kind', 'changed');
        $changes->assertJsonPath('changes.0.old', '100');
        $changes->assertJsonPath('changes.0.new', '150');
        $changes->assertJsonPath('total', 1);

        // 別会社の取込・存在しない取込は 404
        $otherImport = SunaCompanyContext::run($this->otherSaneiCompanyId, fn () => Import::create([
            'format' => 'client_sales_v1', 'original_filename' => 'x.xlsx', 'file_sha256' => 'x', 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now(),
        ]));
        $this->actingAs($user)->getJson(route('suna.sales.report.api.history.changes', ['import' => $otherImport->id]))->assertNotFound();
        $this->actingAs($user)->getJson(route('suna.sales.report.api.history.changes', ['import' => 999999]))->assertNotFound();
    }

    public function test_a_realistic_sized_first_import_works()
    {
        $user = $this->importer();
        $rows = [];
        for ($c = 1; $c <= 120; $c++) {
            foreach ([2023, 2024, 2025, 2026] as $year) {
                $months = [];
                foreach (range(1, $year === 2026 ? 9 : 12) as $m) {
                    $months[$m] = [(float) ($c * 1000 + $m), $m % 5];
                }
                $rows[] = $this->row(sprintf('テスト得意先%03d', $c), $year, ['営業第一部', '営業第二部', '営業第三部', '営業第三部第一課', '営業第三部第二課'][$c % 5], $months);
            }
        }

        $response = $this->preview($user, $rows)->assertOk();
        $response->assertJsonPath('summary.months_added', 120 * (12 * 3 + 9));
        $this->assertLessThanOrEqual(500, count($response->json('details')));
        $this->assertSame(120 * 45, $response->json('details_total'));
        $this->confirm($user, $response->json('token'))->assertOk();

        $this->assertSame(120 * 45, count($this->facts()));
    }
}
