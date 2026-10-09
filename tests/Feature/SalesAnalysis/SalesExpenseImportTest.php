<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsExpenseWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase 21: 部署別経費表の取込HTTPフロー */
class SalesExpenseImportTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsExpenseWorkbook;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function basicWorkbookPath(float $amount = 10000): string
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

    public function test_upload_imports_all_categories_and_returns_summary()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $path = $this->basicWorkbookPath();

        try {
            $file = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file,
                'fiscal_year' => 2025,
            ]);

            $response->assertOk();
            $response->assertJson(['fiscal_year' => 2025, 'version' => 1, 'line_item_count' => 4]);

            $this->assertDatabaseHas('sales_expense_line_items', ['department_key' => 'planning', 'category' => '主材料費', 'vendor_name' => 'A社'], 'sales');
            $this->assertDatabaseHas('sales_expense_line_items', ['department_key' => 'shared', 'category' => '通信費', 'is_shared_split' => true], 'sales');
        } finally {
            @unlink($path);
        }
    }

    public function test_duplicate_file_hash_is_rejected()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $path = $this->basicWorkbookPath();

        try {
            $file1 = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file1,
                'fiscal_year' => 2025,
            ])->assertOk();

            $file2 = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file2,
                'fiscal_year' => 2025,
            ]);

            $response->assertStatus(422);
        } finally {
            @unlink($path);
        }
    }

    public function test_mismatched_fiscal_year_is_rejected_and_nothing_is_saved()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        // basicWorkbookPath()のタイトル行は既定で2025年度（makeExpenseWorkbookの既定fiscalYear）
        $path = $this->basicWorkbookPath();

        try {
            $file = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            // 誤って2026年度として送信（タイトル行は2025年度のため不一致）
            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file,
                'fiscal_year' => 2026,
            ]);

            $response->assertStatus(422);
            $this->assertStringContainsString('一致しません', $response->json('message'));
            $this->assertSame(0, SalesExpenseImport::count());
            $this->assertSame(0, SalesExpenseLineItem::count());
        } finally {
            @unlink($path);
        }
    }

    public function test_mismatched_fiscal_year_succeeds_when_forced()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $path = $this->basicWorkbookPath();

        try {
            $file = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file,
                'fiscal_year' => 2026,
                'force' => 1,
            ]);

            $response->assertOk();
            $response->assertJson(['fiscal_year' => 2026]);
            $this->assertNotEmpty($response->json('warnings'));
            $this->assertSame(1, SalesExpenseImport::where('fiscal_year', 2026)->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_force_reimport_bypasses_duplicate_check_and_creates_new_version()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $path = $this->basicWorkbookPath();

        try {
            $file1 = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file1,
                'fiscal_year' => 2025,
            ])->assertOk();

            // 全く同じバイト内容のファイルでも、forceを立てれば再取込みできる
            $file2 = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file2,
                'fiscal_year' => 2025,
                'force' => 1,
            ]);

            $response->assertOk();
            $response->assertJson(['version' => 2]);
            $this->assertSame(2, SalesExpenseImport::where('company_id', $this->salesTestCompanyId())->where('fiscal_year', 2025)->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_reimport_same_fiscal_year_creates_new_version_and_keeps_old_rows()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $pathV1 = $this->basicWorkbookPath(10000);
        $pathV2 = $this->basicWorkbookPath(99999);

        try {
            $file1 = new UploadedFile($pathV1, '経費表v1.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file1,
                'fiscal_year' => 2025,
            ])->assertOk();

            $file2 = new UploadedFile($pathV2, '経費表v2.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file2,
                'fiscal_year' => 2025,
            ]);

            $response->assertOk();
            $response->assertJson(['version' => 2]);

            $this->assertSame(2, SalesExpenseImport::where('company_id', $this->salesTestCompanyId())->where('fiscal_year', 2025)->count());
            $this->assertDatabaseHas('sales_expense_line_items', ['amount' => 10000], 'sales');
            $this->assertDatabaseHas('sales_expense_line_items', ['amount' => 99999], 'sales');
        } finally {
            @unlink($pathV1);
            @unlink($pathV2);
        }
    }

    public function test_non_sunbrain_company_is_forbidden()
    {
        // 会社コードをSUNBRAIN以外のまま（既定）
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $path = $this->basicWorkbookPath();

        try {
            $file = new UploadedFile($path, '経費表.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.expense_import.store'), [
                'file' => $file,
                'fiscal_year' => 2025,
            ]);

            $response->assertForbidden();
            $this->assertSame(0, SalesExpenseLineItem::count());
        } finally {
            @unlink($path);
        }
    }

    public function test_leader_cannot_access_expense_import()
    {
        $this->markCompanyAsSunbrain();
        $leader = User::factory()->create(['user_role' => 'leader']);

        $response = $this->actingAs($leader)->get(route('superadmin.sales_analysis.expense_import.create'));

        $response->assertForbidden();
    }
}
