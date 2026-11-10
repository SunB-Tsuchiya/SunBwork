<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ImportChange;
use App\Models\SunA\Sales\ImportSnapshot;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\ClientSalesWorkbookWriter;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\SunaCompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/** 取込の取り消し（直近の取込だけ、取り込む前の状態に完全に戻す）。SUNASALES_PLAN1.md §16 */
class ImportUndoTest extends TestCase
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

    private function importer(bool $canImport = true): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => $canImport]);
        $this->inSalesArea($user);

        return $user;
    }

    private function row(string $client, int $year, string $dept, array $months): array
    {
        return ['client' => $client, 'year' => $year, 'department' => $dept, 'months' => $months];
    }

    private function upload(array $rows): UploadedFile
    {
        $path = $this->tmp[] = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new ClientSalesWorkbookWriter())->write($rows, $path);

        return new UploadedFile($path, '得意先別.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** プレビュー → 確定。作られた取込の id を返す */
    private function importRows(User $user, array $rows, array $replaceYears = []): int
    {
        $token = $this->actingAs($user)->post(route('suna.sales.report.api.import.preview'), ['file' => $this->upload($rows), 'replace_years' => $replaceYears], ['Accept' => 'application/json'])->assertOk()->json('token');

        return (int) $this->actingAs($user)->postJson(route('suna.sales.report.api.import.confirm'), ['token' => $token, 'replace_years' => $replaceYears])->assertOk()->json('import_id');
    }

    private function undo(User $user, int $importId)
    {
        return $this->actingAs($user)->postJson(route('suna.sales.report.api.history.undo', ['import' => $importId]));
    }

    /** 売上の行の全内容（id・値・import_id・日時まで）。取り消しで「完全に同じ」に戻るかの比較に使う */
    private function snapshotOfFacts(?int $companyId = null): array
    {
        return SunaCompanyContext::run($companyId ?? $this->saneiCompanyId, fn () => Fact::query()->orderBy('id')->get()
            ->map(fn ($f) => $f->only(['id', 'client_key', 'client_name', 'sales_year', 'sales_month', 'department_key', 'section_key', 'amount', 'order_count', 'import_id']) + ['created_at' => (string) $f->created_at, 'updated_at' => (string) $f->updated_at])
            ->all());
    }

    private function importRecord(int $id): Import
    {
        SunaCompanyContext::set($this->saneiCompanyId);

        return Import::query()->findOrFail($id);
    }

    public function test_undo_of_an_import_that_only_adds_removes_exactly_the_added_rows()
    {
        $user = $this->importer();
        $first = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1], 2 => [200.0, 2]])]);
        $before = $this->snapshotOfFacts();

        $second = $this->importRows($user, [$this->row('テスト得意先B', 2025, '営業第二部', [1 => [50.0, 1]])]);
        $this->assertCount(3, $this->snapshotOfFacts());

        $this->undo($user, $second)->assertOk()->assertJson(['added_removed' => 1, 'restored_changed' => 0, 'restored_deleted' => 0]);

        $this->assertSame($before, $this->snapshotOfFacts());
        $this->assertSame('undone', $this->importRecord($second)->status);
        $this->assertSame($user->id, $this->importRecord($second)->undone_by);
        $this->assertNotNull($this->importRecord($second)->undone_at);
        $this->assertSame('completed', $this->importRecord($first)->status);
    }

    public function test_undo_restores_changed_deleted_and_department_changes_exactly()
    {
        $user = $this->importer();
        $this->importRows($user, [
            $this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1], 2 => [200.0, 2], 3 => [300.0, 3]]),
            $this->row('テスト得意先B', 2024, '営業第二部', [1 => [10.0, 1]]),
        ]);
        $before = $this->snapshotOfFacts();

        // A: 1月の金額・件数を変更、2月を空欄（削除）、担当部門を第三部に変更、4月を追加／B: 変更なし
        $second = $this->importRows($user, [
            $this->row('テスト得意先A', 2024, '営業第三部', [1 => [999.0, 9], 3 => [300.0, 3], 4 => [40.0, 1]]),
            $this->row('テスト得意先B', 2024, '営業第二部', [1 => [10.0, 1]]),
        ]);
        $changed = $this->snapshotOfFacts();
        $this->assertNotSame($before, $changed);
        $this->assertSame(1, ImportSnapshot::withoutGlobalScopes()->where('import_id', $second)->where('kind', 'deleted')->count());

        $this->undo($user, $second)->assertOk()->assertJson(['added_removed' => 1, 'restored_deleted' => 1]);

        $this->assertSame($before, $this->snapshotOfFacts()); // id・値・日時・import_id まで完全に同じ
        $this->assertSame(0, ImportSnapshot::withoutGlobalScopes()->where('import_id', $second)->count()); // 取り消した取込のぶんは削除
    }

    public function test_undo_restores_rows_removed_by_replace_year()
    {
        $user = $this->importer();
        $this->importRows($user, [
            $this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]]),
            $this->row('テスト得意先B', 2024, '営業第二部', [1 => [10.0, 1]]),
        ]);
        $before = $this->snapshotOfFacts();

        $second = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])], [2024]); // Bが消える
        $this->assertCount(1, $this->snapshotOfFacts());

        $this->undo($user, $second)->assertOk();
        $this->assertSame($before, $this->snapshotOfFacts());
    }

    public function test_only_the_latest_import_can_be_undone_and_undo_goes_back_in_order()
    {
        $user = $this->importer();
        $first = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);
        $afterFirst = $this->snapshotOfFacts();
        $second = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [150.0, 1], 2 => [20.0, 1]])]);
        $afterSecond = $this->snapshotOfFacts();
        $third = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [170.0, 1]])]);

        // 最新（3回目）以外は取り消せない
        $this->undo($user, $first)->assertStatus(422);
        $this->undo($user, $second)->assertStatus(422);
        $this->assertCount(1, $this->snapshotOfFacts());

        // 新しい方から順に取り消すと、それぞれの時点の状態に戻る
        $this->undo($user, $third)->assertOk();
        $this->assertSame($afterSecond, $this->snapshotOfFacts());
        $this->undo($user, $second)->assertOk();
        $this->assertSame($afterFirst, $this->snapshotOfFacts());
        $this->undo($user, $first)->assertOk();
        $this->assertSame([], $this->snapshotOfFacts());
    }

    public function test_cannot_undo_twice()
    {
        $user = $this->importer();
        $first = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);
        $second = $this->importRows($user, [$this->row('テスト得意先B', 2024, '営業第一部', [1 => [5.0, 1]])]);

        $this->undo($user, $second)->assertOk();
        $this->undo($user, $second)->assertStatus(422);      // 取消済み
        $this->assertCount(1, $this->snapshotOfFacts());
        // 取消済みの取込は「最新」に数えない → 1回目が最新になる
        $this->undo($user, $first)->assertOk();
    }

    public function test_an_import_made_before_this_feature_can_be_undone_only_if_it_just_added_rows()
    {
        $user = $this->importer();
        // 追加だけの取込（変更前の姿の保存が無い、この機能より前の取込を再現するため、保存を消す）
        $addOnly = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);
        ImportSnapshot::withoutGlobalScopes()->where('import_id', $addOnly)->delete();
        $this->undo($user, $addOnly)->assertOk();
        $this->assertSame([], $this->snapshotOfFacts());

        // 変更を含む取込（保存を消すと、元に戻せないので取り消せない）
        $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);
        $withChange = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [999.0, 1]])]);
        ImportSnapshot::withoutGlobalScopes()->where('import_id', $withChange)->delete();
        $this->undo($user, $withChange)->assertStatus(422);
        $this->assertCount(1, $this->snapshotOfFacts());
        $this->assertEquals(999, $this->snapshotOfFacts()[0]['amount']);
    }

    public function test_undo_requires_the_import_permission()
    {
        $importer = $this->importer();
        $id = $this->importRows($importer, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);

        $viewerOnly = $this->importer(false);
        $this->undo($viewerOnly, $id)->assertForbidden();
        $this->undo($this->saneiUser('user'), $id)->assertForbidden();
        $this->assertCount(1, $this->snapshotOfFacts());
    }

    public function test_undo_never_touches_another_company()
    {
        $user = $this->importer();
        $mine = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);

        // 別会社にも同じ形のデータと取込がある
        SunaCompanyContext::run($this->otherSaneiCompanyId, function () {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => '他社.xlsx', 'file_sha256' => hash('sha256', 'x'), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            Fact::create(['client_key' => 'テスト得意先a', 'client_name' => 'テスト得意先A', 'sales_year' => 2024, 'sales_month' => 1, 'department_key' => 'dept1', 'amount' => 777, 'order_count' => 1, 'import_id' => $import->id]);
            ImportChange::create(['import_id' => $import->id, 'client_key' => 'テスト得意先a', 'client_name' => 'テスト得意先A', 'sales_year' => 2024, 'sales_month' => 1, 'kind' => 'added', 'field' => 'amount', 'new_value' => '777']);
        });
        $otherId = SunaCompanyContext::run($this->otherSaneiCompanyId, fn () => Import::query()->value('id'));

        $this->undo($user, $otherId)->assertStatus(422);               // 他社の取込は見つからない
        $this->undo($user, $mine)->assertOk();
        $this->assertSame([], $this->snapshotOfFacts());
        $this->assertCount(1, $this->snapshotOfFacts($this->otherSaneiCompanyId)); // 他社のデータは残る
        $this->assertEquals(777, $this->snapshotOfFacts($this->otherSaneiCompanyId)[0]['amount']);
    }

    public function test_audit_log_has_counts_only_and_history_shows_status_and_reason()
    {
        $user = $this->importer();
        $first = $this->importRows($user, [$this->row('テスト得意先A', 2024, '営業第一部', [1 => [100.0, 1]])]);
        $second = $this->importRows($user, [$this->row('テスト得意先B', 2024, '営業第一部', [1 => [5.0, 1]])]);

        $history = collect($this->actingAs($user)->get(route('suna.sales.report.history.index'))->assertOk()->viewData('page')['props']['imports'])->keyBy('id');
        $this->assertTrue($history[$second]['can_undo']);
        $this->assertFalse($history[$first]['can_undo']);
        $this->assertStringContainsString('新しい取込', $history[$first]['undo_reason']);

        $this->undo($user, $second)->assertOk();
        SunaCompanyContext::set($this->saneiCompanyId);
        $log = AuditLog::query()->where('action', 'import_undo')->firstOrFail();
        $this->assertSame($second, (int) $log->target_id);
        $this->assertSame(['added_removed' => 1, 'restored_changed' => 0, 'restored_deleted' => 0], $log->context);
        $this->assertStringNotContainsString('テスト得意先', json_encode($log->toArray(), JSON_UNESCAPED_UNICODE));

        $history = collect($this->actingAs($user)->get(route('suna.sales.report.history.index'))->viewData('page')['props']['imports'])->keyBy('id');
        $this->assertSame('undone', $history[$second]['status']);
        $this->assertSame($user->name, $history[$second]['undone_by_name']);
        $this->assertTrue($history[$first]['can_undo']); // 1回目が最新になる

        // 取消済みの取込は、データ登録状況の「直近の取込」に出ない
        $status = $this->actingAs($user)->getJson(route('suna.sales.report.api.registration_status'))->json();
        $this->assertSame('得意先別.xlsx', $status['last_import']['original_filename']);
        $this->assertSame($first, $status['last_import']['id']);
    }
}
