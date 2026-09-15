<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Services\SalesAnalysis\SalesExportService;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesQueryService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase 20: 年次・期別Excel出力へのサンエー印刷経由/独自受注内訳の反映を検証する */
class SalesExportServiceChannelTest extends TestCase
{
    use RefreshesSalesDatabase;

    private static int $importSeq = 0;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function seedMonth(string $dept, int $year, int $month, string $channel, array $orders): void
    {
        self::$importSeq++;

        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'department_key' => $dept,
            'order_channel' => $channel,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "export-channel-seed-{$dept}-{$channel}-{$year}-{$month}-" . self::$importSeq),
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
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'order_channel' => $channel, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    private function service(): SalesExportService
    {
        return (new SalesExportService(new SalesQueryService(new SalesImportService())))->forCompany($this->salesTestCompanyId());
    }

    public function test_annual_workbook_adds_channel_columns_for_sunbrain()
    {
        $this->markCompanyAsSunbrain();
        $this->seedMonth('planning', 2026, 1, 'standard', [['order_number' => 'EX-S1', 'client_name' => 'A社', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 1, 'direct', [['order_number' => 'EX-D1', 'client_name' => 'A社', 'amount' => 300]]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, false);

        $summarySheet = $spreadsheet->getSheetByName('概要');
        $summaryValues = [];
        foreach ($summarySheet->getRowIterator() as $row) {
            $label = $summarySheet->getCell([1, $row->getRowIndex()])->getValue();
            $value = $summarySheet->getCell([2, $row->getRowIndex()])->getValue();
            if ($label !== '') {
                $summaryValues[$label] = $value;
            }
        }
        $this->assertSame(1300.0, $summaryValues['期間売上']);
        $this->assertSame(1000.0, $summaryValues['サンエー印刷経由']);
        $this->assertSame(300.0, $summaryValues['独自受注']);

        $monthlySheet = $spreadsheet->getSheetByName('月別推移');
        $this->assertSame('サンエー印刷経由', $monthlySheet->getCell('I1')->getValue());
        $this->assertSame('独自受注', $monthlySheet->getCell('J1')->getValue());
        $this->assertSame(1000.0, $monthlySheet->getCell('I2')->getValue());
        $this->assertSame(300.0, $monthlySheet->getCell('J2')->getValue());

        $ordersSheet = $spreadsheet->getSheetByName('該当明細');
        $this->assertSame('受注経路', $ordersSheet->getCell('G1')->getValue());
        $orderChannelLabels = [];
        foreach ([2, 3] as $r) {
            $orderChannelLabels[] = $ordersSheet->getCell('G' . $r)->getValue();
        }
        sort($orderChannelLabels);
        $this->assertSame(['サンエー印刷経由', '独自受注'], $orderChannelLabels);
    }

    public function test_annual_workbook_has_no_channel_columns_for_non_sunbrain_company()
    {
        // 会社コードをSUNBRAIN以外のまま（既定）
        $this->seedMonth('planning', 2026, 1, 'standard', [['order_number' => 'EX-N1', 'client_name' => 'A社', 'amount' => 1000]]);

        $spreadsheet = $this->service()->annualAnalysisWorkbook('planning', 2026, false);

        $monthlySheet = $spreadsheet->getSheetByName('月別推移');
        $this->assertNotSame('サンエー印刷経由', $monthlySheet->getCell('I1')->getValue());

        $ordersSheet = $spreadsheet->getSheetByName('該当明細');
        $this->assertNotSame('受注経路', $ordersSheet->getCell('G1')->getValue());
    }

    public function test_fiscal_year_workbook_adds_channel_columns_for_sunbrain()
    {
        $this->markCompanyAsSunbrain();
        $this->seedMonth('planning', 2026, 4, 'standard', [['order_number' => 'FY-S1', 'client_name' => 'A社', 'amount' => 2000]]);
        $this->seedMonth('planning', 2026, 4, 'direct', [['order_number' => 'FY-D1', 'client_name' => 'A社', 'amount' => 500]]);

        $spreadsheet = $this->service()->fiscalYearAnalysisWorkbook('planning', 2026, false);

        $summarySheet = $spreadsheet->getSheetByName('概要');
        $summaryValues = [];
        foreach ($summarySheet->getRowIterator() as $row) {
            $label = $summarySheet->getCell([1, $row->getRowIndex()])->getValue();
            $value = $summarySheet->getCell([2, $row->getRowIndex()])->getValue();
            if ($label !== '') {
                $summaryValues[$label] = $value;
            }
        }
        $this->assertSame(2000.0, $summaryValues['サンエー印刷経由']);
        $this->assertSame(500.0, $summaryValues['独自受注']);
    }
}
