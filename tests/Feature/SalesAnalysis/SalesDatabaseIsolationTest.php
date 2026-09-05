<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesImport;
use App\Models\SalesAnalysisPermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesDatabaseIsolationTest extends TestCase
{
    use RefreshesSalesDatabase;

    private const SALES_ONLY_TABLES = [
        'sales_imports',
        'sales_active_months',
        'sales_orders',
        'sales_order_details',
        'sales_client_groups',
        'sales_client_group_members',
        'sales_audit_logs',
    ];

    public function test_sales_tables_exist_only_on_sales_connection()
    {
        foreach (self::SALES_ONLY_TABLES as $table) {
            $this->assertTrue(
                Schema::connection('sales')->hasTable($table),
                "sales接続に{$table}が存在しません"
            );
            $this->assertFalse(
                Schema::connection('mysql')->hasTable($table),
                "通常DBに{$table}が誤って作成されています"
            );
        }
    }

    public function test_permission_table_exists_only_on_normal_connection()
    {
        $this->assertTrue(Schema::connection('mysql')->hasTable('sales_analysis_permissions'));
        $this->assertFalse(Schema::connection('sales')->hasTable('sales_analysis_permissions'));
    }

    public function test_sales_model_writes_only_to_sales_connection()
    {
        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
            'version' => 1,
            'original_filename' => 'test.xlsx',
            'file_sha256' => str_repeat('a', 64),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 0,
            'detail_count' => 0,
            'total_amount' => 0,
        ]);

        $this->assertDatabaseHas('sales_imports', ['id' => $import->id], 'sales');
        // sales_imports は通常DBに存在しないテーブルなので missing チェックは
        // test_sales_tables_exist_only_on_sales_connection 側で担保する
    }

    public function test_permission_model_writes_only_to_normal_connection()
    {
        $user = User::factory()->create();

        $permission = SalesAnalysisPermission::create([
            'user_id' => $user->id,
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('sales_analysis_permissions', ['id' => $permission->id], 'mysql');
        // sales_analysis_permissions は sales DBに存在しないテーブルなので missing チェックは
        // test_permission_table_exists_only_on_normal_connection 側で担保する
    }
}
