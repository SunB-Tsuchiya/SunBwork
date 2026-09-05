<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Services\SalesAnalysis\SalesExportService;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesQueryService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesExportServiceTest extends TestCase
{
    use RefreshesSalesDatabase;

    private static int $importSeq = 0;

    private function seedMonth(string $dept, int $year, int $month, array $orders): void
    {
        self::$importSeq++;

        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "export-seed-{$dept}-{$year}-{$month}-" . self::$importSeq),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => count($orders),
            'detail_count' => count($orders),
            'total_amount' => array_sum(array_column($orders, 'amount')),
        ]);

        foreach ($orders as $o) {
            $order = SalesOrder::create([
                'sales_import_id' => $import->id,
                'order_number' => $o['order_number'],
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year,
                'sales_month' => $month,
                'order_amount' => $o['amount'],
            ]);

            SalesOrderDetail::create([
                'sales_order_id' => $order->id,
                'source_row_number' => 1,
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'category' => $o['category'] ?? '組版',
                'item_name' => $o['item_name'] ?? '新規',
                'format_size' => 'A4',
                'color_count' => 1,
                'quantity' => 1,
                'unit_price' => $o['amount'],
                'line_amount' => $o['amount'],
                'order_amount_component' => $o['amount'],
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
            ]);
        }

        SalesActiveMonth::updateOrCreate(
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    private function service(): SalesExportService
    {
        return (new SalesExportService(new SalesQueryService(new SalesImportService())))->forCompany($this->salesTestCompanyId());
    }

    public function test_workbook_has_all_required_sheets()
    {
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'EX-1', 'client_name' => 'A社', 'amount' => 1000]]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, false);

        $this->assertSame(
            ['概要', '月別推移', '得意先別', '分類別', '項目別', '該当明細'],
            $spreadsheet->getSheetNames()
        );
    }

    public function test_summary_sheet_contains_kpi_values()
    {
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'EX-2', 'client_name' => 'A社', 'amount' => 1500]]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, false);
        $sheet = $spreadsheet->getSheetByName('概要');

        // toArray()は既定でformatData=trueのため桁区切りカンマ付き文字列になる。生の数値を見るにはfalseを指定する
        $rows = $sheet->toArray(null, true, false);
        $map = collect($rows)->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

        $this->assertSame(1500.0, (float) $map['期間売上']);
    }

    public function test_orders_sheet_lists_registered_orders()
    {
        $this->seedMonth('planning', 2026, 2, [
            ['order_number' => 'EX-3', 'client_name' => 'A社', 'amount' => 1000],
            ['order_number' => 'EX-4', 'client_name' => 'B社', 'amount' => 2000],
        ]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, false);
        $sheet = $spreadsheet->getSheetByName('該当明細');

        // ヘッダー行 + 2件
        $this->assertSame(3, $sheet->getHighestRow());
    }

    public function test_user_supplied_strings_are_not_interpreted_as_formulas()
    {
        // formula injection対策: 得意先名が"="で始まっていても数式として書き込まれないこと
        $this->seedMonth('planning', 2026, 3, [
            ['order_number' => 'EX-5', 'client_name' => '=1+1', 'amount' => 1000, 'product_name' => '=HYPERLINK("http://evil")'],
        ]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, false);
        $sheet = $spreadsheet->getSheetByName('該当明細');

        $clientCell = $sheet->getCell('C2');
        $productCell = $sheet->getCell('D2');

        $this->assertNotSame(DataType::TYPE_FORMULA, $clientCell->getDataType());
        $this->assertNotSame(DataType::TYPE_FORMULA, $productCell->getDataType());
        $this->assertStringStartsWith("'=", (string) $clientCell->getValue());
        $this->assertStringStartsWith("'=", (string) $productCell->getValue());
    }

    public function test_export_reflects_selected_department_and_year()
    {
        $this->seedMonth('planning', 2025, 1, [['order_number' => 'EX-6', 'client_name' => 'A社', 'amount' => 999]]);
        $this->seedMonth('planning', 2026, 1, [['order_number' => 'EX-7', 'client_name' => 'A社', 'amount' => 111]]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2025, false);
        $sheet = $spreadsheet->getSheetByName('概要');
        $rows = collect($sheet->toArray(null, true, false))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

        $this->assertSame(999.0, (float) $rows['期間売上']);
    }

    public function test_client_sheet_consolidates_clients_when_flag_true()
    {
        $group = SalesClientGroup::create(['company_id' => $this->salesTestCompanyId(), 'name' => '株式会社NON', 'created_by' => 1, 'updated_by' => 1]);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（2）', 'normalized_name' => 'x']);
        SalesClientGroupMember::create(['sales_client_group_id' => $group->id, 'company_id' => $this->salesTestCompanyId(), 'client_name' => '株式会社NON（3）', 'normalized_name' => 'y']);

        $this->seedMonth('planning', 2026, 2, [
            ['order_number' => 'EX-8', 'client_name' => '株式会社NON（2）', 'amount' => 1000],
            ['order_number' => 'EX-9', 'client_name' => '株式会社NON（3）', 'amount' => 2000],
        ]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, true);
        $sheet = $spreadsheet->getSheetByName('得意先別');
        $rows = $sheet->toArray(null, true, false);

        // ヘッダー行 + 統合後1行のみ
        $this->assertSame(2, $sheet->getHighestRow());
        $this->assertSame('株式会社NON', $rows[1][1]);
        $this->assertSame(3000.0, (float) $rows[1][2]);
    }
}
