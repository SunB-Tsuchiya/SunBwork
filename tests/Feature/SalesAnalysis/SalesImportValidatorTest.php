<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Services\SalesAnalysis\SalesImportValidator;
use App\Services\SalesAnalysis\SalesWorkbookReader;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesImportValidatorTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsSalesWorkbook;

    private function validator(): SalesImportValidator
    {
        return new SalesImportValidator(new SalesWorkbookReader());
    }

    /**
     * サンプル（架空データ）は途中で切られた不完全なデータであり、
     * 受注No 4602841 で M合計とN合計が意図的に不一致になっている。
     * 本番データではこの状態は起こり得ないため、検証は正しくエラーを返すべき。
     */
    public function test_sample_workbook_is_rejected_for_amount_mismatch()
    {
        $path = base_path('tests/Fixtures/SalesAnalysis/sanbrain_meisai_sample.xlsx');

        $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 8);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty(array_filter(
            $result['errors'],
            fn ($e) => str_contains($e, '4602841')
        ));
    }

    public function test_valid_workbook_produces_correct_orders_and_summary()
    {
        $rows = [
            $this->row('1000001', 'A社', '商品A', 10000, 10000, '2026/09/05'),
            // 複数明細の受注: 途中行は受注金額0、最終行に合計
            $this->row('1000002', 'B社', '商品B', 3000, 0, '2026/09/10'),
            $this->row('1000002', 'B社', '商品B', 2000, 5000, '2026/09/10'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $this->assertSame(2, $result['summary']['order_count']);
            $this->assertSame(3, $result['summary']['detail_count']);
            $this->assertSame(15000.0, $result['summary']['total_amount']);

            $order2 = collect($result['orders'])->firstWhere('order_number', '1000002');
            $this->assertSame(5000.0, $order2['order_amount']);
            $this->assertCount(2, $order2['details']);
        } finally {
            @unlink($path);
        }
    }

    public function test_department_label_mismatch_is_rejected()
    {
        $rows = [$this->row('2000001', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        // タイトルは「制作」なのに department_key は planning（企画）で検証する
        $path = $this->makeSalesWorkbook($this->monthlyTitle('制作', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
        } finally {
            @unlink($path);
        }
    }

    public function test_title_month_mismatch_is_rejected()
    {
        $rows = [$this->row('2000002', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            // 指定は10月だがタイトルは9月
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 10);

            $this->assertFalse($result['valid']);
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_amount_is_rejected()
    {
        $rows = [$this->row('2000003', 'A社', '商品A', -1000, -1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
        } finally {
            @unlink($path);
        }
    }

    public function test_annual_workbook_derives_month_from_plate_date()
    {
        $rows = [
            $this->row('3000001', 'A社', '商品A', 1000, 1000, '2026/04/10'),
            $this->row('3000002', 'A社', '商品B', 2000, 2000, '2026/12/20'),
        ];
        $path = $this->makeSalesWorkbook($this->annualTitle('企画', 2026), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'annual', 2026, null);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $order1 = collect($result['orders'])->firstWhere('order_number', '3000001');
            $order2 = collect($result['orders'])->firstWhere('order_number', '3000002');
            $this->assertSame(4, $order1['sales_month']);
            $this->assertSame(12, $order2['sales_month']);
        } finally {
            @unlink($path);
        }
    }

    public function test_order_number_already_active_in_another_month_is_rejected()
    {
        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 8,
            'version' => 1,
            'original_filename' => 'existing.xlsx',
            'file_sha256' => str_repeat('a', 64),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => 1000,
        ]);
        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => '4000001',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'plate_date' => '2026-08-15',
            'sales_year' => 2026,
            'sales_month' => 8,
            'order_amount' => 1000,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 8,
            'sales_import_id' => $import->id,
            'activated_by' => 1,
            'activated_at' => now(),
        ]);

        // 同じ受注Noを別月（9月）で取り込もうとする
        $rows = [$this->row('4000001', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter(
                $result['errors'],
                fn ($e) => str_contains($e, '4000001')
            ));
        } finally {
            @unlink($path);
        }
    }

    private function row(string $orderNumber, string $client, string $product, float $lineAmount, float $orderAmount, string $plateDate): array
    {
        return [
            'order_number' => $orderNumber,
            'client_name' => $client,
            'product_name' => $product,
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => $lineAmount,
            'line_amount' => $lineAmount,
            'order_amount_component' => $orderAmount,
            'plate_date' => $plateDate,
        ];
    }
}
