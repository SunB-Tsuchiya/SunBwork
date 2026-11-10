<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\Company;
use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\Department;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\SunaCompanyContext;
use App\Services\SunA\Sales\SunaCompanyContextMissingException;
use App\Services\SunA\Sales\SunaCompanyNotEligibleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * 売上レポート（SunA/Sales）の会社分離（SUNASALES_PLAN1.md §5.3）。
 * サン・ブレーンとサンエー印刷（および別会社）のデータが混ざらないこと、会社未設定は fail-closed であること、
 * 既存のサン・ブレーンの売上テーブル（sales_*）に触れないことを保証する。
 */
class CompanyIsolationTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
    }

    protected function tearDown(): void
    {
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function makeImport(int $companyId): Import
    {
        return SunaCompanyContext::run($companyId, fn () => Import::create([
            'format' => 'client_sales_v1', 'original_filename' => 'x.xlsx', 'file_sha256' => hash('sha256', uniqid()),
            'status' => 'completed', 'imported_by' => 1, 'imported_at' => now(),
        ]));
    }

    private function makeFact(int $companyId, string $client, int $year = 2025, int $month = 4, float $amount = 100): void
    {
        $import = $this->makeImport($companyId);
        SunaCompanyContext::run($companyId, fn () => Fact::create([
            'client_key' => $client, 'client_name' => $client, 'sales_year' => $year, 'sales_month' => $month,
            'department_key' => 'dept1', 'amount' => $amount, 'order_count' => 1, 'import_id' => $import->id,
        ]));
    }

    public function test_queries_return_only_the_current_companys_rows()
    {
        $this->makeFact($this->saneiCompanyId, 'A社', amount: 100);
        $this->makeFact($this->otherSaneiCompanyId, 'B社', amount: 999);

        SunaCompanyContext::set($this->saneiCompanyId);
        $this->assertSame(['A社'], Fact::query()->pluck('client_name')->all());
        $this->assertSame(1, Import::query()->count());
        $this->assertSame(100.0, (float) Fact::query()->sum('amount'));

        SunaCompanyContext::set($this->otherSaneiCompanyId);
        $this->assertSame(['B社'], Fact::query()->pluck('client_name')->all());
        $this->assertSame(999.0, (float) Fact::query()->sum('amount'));
    }

    public function test_find_and_aggregates_cannot_reach_another_companys_row()
    {
        $this->makeFact($this->otherSaneiCompanyId, 'B社');
        $otherId = SunaCompanyContext::run($this->otherSaneiCompanyId, fn () => Fact::query()->value('id'));

        SunaCompanyContext::set($this->saneiCompanyId);
        $this->assertNull(Fact::find($otherId));
        $this->assertSame(0, Fact::where('client_name', 'B社')->count());
        $this->assertSame(0, Fact::query()->update(['amount' => 1])); // 他社の行は更新もできない
        $this->assertSame(0, Fact::query()->delete());                // 削除もできない

        SunaCompanyContext::set($this->otherSaneiCompanyId);
        $this->assertNotNull(Fact::find($otherId));
        $this->assertSame(100.0, Fact::find($otherId)->amount); // 変わっていない
    }

    public function test_using_models_without_a_company_context_fails_closed()
    {
        SunaCompanyContext::clear();

        foreach ([Fact::class, Import::class, Department::class, AuditLog::class] as $model) {
            try {
                $model::query()->count();
                $this->fail("{$model} は会社未設定で例外にならなかった");
            } catch (SunaCompanyContextMissingException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(SunaCompanyContextMissingException::class);
        Fact::create(['client_key' => 'a', 'client_name' => 'a', 'sales_year' => 2025, 'sales_month' => 1, 'department_key' => 'dept1', 'import_id' => 1]);
    }

    public function test_company_id_is_set_automatically_and_a_foreign_company_id_is_rejected()
    {
        SunaCompanyContext::set($this->saneiCompanyId);
        $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => 'x.xlsx', 'file_sha256' => 'a', 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
        $this->assertSame($this->saneiCompanyId, (int) $import->company_id);

        $this->expectException(\LogicException::class);
        Import::create(['company_id' => $this->otherSaneiCompanyId, 'format' => 'x', 'original_filename' => 'x', 'file_sha256' => 'b', 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
    }

    public function test_bulk_insert_scoped_stamps_the_company_and_rejects_foreign_rows()
    {
        $import = $this->makeImport($this->saneiCompanyId);
        SunaCompanyContext::set($this->saneiCompanyId);
        $row = ['client_key' => 'a', 'client_name' => 'a', 'sales_year' => 2025, 'sales_month' => 4, 'department_key' => 'dept1', 'amount' => 1, 'order_count' => 1, 'import_id' => $import->id, 'created_at' => now(), 'updated_at' => now()];

        Fact::insertScoped([$row]);
        $this->assertSame($this->saneiCompanyId, (int) DB::connection('sales')->table('suna_sl_facts')->value('company_id'));

        $this->expectException(\LogicException::class);
        Fact::insertScoped([array_merge($row, ['client_key' => 'b', 'company_id' => $this->otherSaneiCompanyId])]);
    }

    public function test_every_suna_sl_table_has_a_company_id_column()
    {
        $tables = collect(DB::connection('sales')->select('show tables'))->map(fn ($r) => array_values((array) $r)[0])
            ->filter(fn ($t) => str_starts_with($t, 'suna_sl_'));

        $this->assertGreaterThanOrEqual(5, $tables->count());
        foreach ($tables as $table) {
            $this->assertTrue(Schema::connection('sales')->hasColumn($table, 'company_id'), "{$table} に company_id が無い");
        }
    }

    public function test_only_general_companies_other_than_the_superadmin_company_can_be_set()
    {
        $sunbrain = Company::create(['name' => 'SB_' . uniqid(), 'code' => 'SB_' . uniqid(), 'company_type' => 'sunbrain', 'active' => true])->id;
        $superadminCompany = Company::create(['name' => 'SA_' . uniqid(), 'code' => 'SUPERADMIN', 'company_type' => 'general', 'active' => true])->id;

        $this->assertFalse(SunaCompanyContext::isEligible($sunbrain));
        $this->assertFalse(SunaCompanyContext::isEligible($superadminCompany));
        $this->assertFalse(SunaCompanyContext::isEligible(999999));
        $this->assertTrue(SunaCompanyContext::isEligible($this->saneiCompanyId));

        $this->expectException(SunaCompanyNotEligibleException::class);
        SunaCompanyContext::set($sunbrain);
    }

    public function test_department_initializer_is_idempotent_company_scoped_and_maps_excel_labels()
    {
        SunaCompanyContext::set($this->saneiCompanyId);
        $init = new DepartmentInitializer();
        $this->assertSame(6, $init->ensureDefaults());
        $this->assertSame(0, $init->ensureDefaults()); // 2回目は追加しない

        // Excelの表記 → 部・課。「営業第三部」とだけ書かれたものは第三部の直轄
        $this->assertSame(['department_key' => 'dept1', 'section_key' => null], $init->resolve('営業第一部'));
        $this->assertSame(['department_key' => 'dept2', 'section_key' => null], $init->resolve('営業第二部'));
        $this->assertSame(['department_key' => 'dept3', 'section_key' => 'dept3_direct'], $init->resolve('営業第三部'));
        $this->assertSame(['department_key' => 'dept3', 'section_key' => 'dept3_sec1'], $init->resolve('営業第三部第一課'));
        $this->assertSame(['department_key' => 'dept3', 'section_key' => 'dept3_sec2'], $init->resolve(' 営業第三部第二課 '));
        $this->assertNull($init->resolve('営業第四部')); // 未登録の表記は取込を止める
        $this->assertSame('営業第三部 第一課', $init->labelFor('dept3', 'dept3_sec1'));

        // 別会社の部門マスタには影響しない
        SunaCompanyContext::set($this->otherSaneiCompanyId);
        $this->assertSame(0, Department::query()->count());
        $this->assertNull($init->resolve('営業第一部'));
    }

    public function test_existing_sunbrain_sales_tables_are_untouched()
    {
        $before = collect(DB::connection('sales')->select('show tables'))->map(fn ($r) => array_values((array) $r)[0])
            ->reject(fn ($t) => str_starts_with($t, 'suna_sl_'))->sort()->values()->all();

        $this->makeFact($this->saneiCompanyId, 'A社');

        // サンエー印刷のデータ投入で、既存の売上テーブルの行は増えない
        foreach (['sales_imports', 'sales_orders', 'sales_order_details', 'sales_active_months', 'sales_client_groups'] as $table) {
            $this->assertSame(0, DB::connection('sales')->table($table)->count(), "{$table} に行が増えている");
        }
        $this->assertContains('sales_imports', $before);
    }
}
