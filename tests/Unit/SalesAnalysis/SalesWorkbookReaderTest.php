<?php

namespace Tests\Unit\SalesAnalysis;

use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;
use App\Services\SalesAnalysis\SalesWorkbookReader;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\TestCase;

class SalesWorkbookReaderTest extends TestCase
{
    use BuildsSalesWorkbook;

    private function sampleFixturePath(): string
    {
        return base_path('tests/Fixtures/SalesAnalysis/sanbrain_meisai_sample.xlsx');
    }

    public function test_reads_sample_workbook_title_and_headers()
    {
        $reader = new SalesWorkbookReader();

        $result = $reader->read($this->sampleFixturePath());

        $this->assertSame(2026, $result['title_year']);
        $this->assertSame(8, $result['title_month']);
        $this->assertSame('企画', $result['department_label']);
        $this->assertSame([], $result['header_mismatches']);
    }

    public function test_skips_blank_and_total_rows_from_sample()
    {
        $reader = new SalesWorkbookReader();

        $result = $reader->read($this->sampleFixturePath());

        // サンプルは Row3-16 が明細（14行）、Row17 空行、Row18 合計行
        $this->assertCount(14, $result['rows']);
        $this->assertSame('4507274', $result['rows'][0]['order_number']);
        $this->assertSame('4602841', $result['rows'][array_key_last($result['rows'])]['order_number']);
    }

    public function test_normalizes_date_and_number_cells()
    {
        $reader = new SalesWorkbookReader();

        $result = $reader->read($this->sampleFixturePath());
        $first = $result['rows'][0];

        $this->assertSame('2026-08-05', $first['plate_date']);
        $this->assertSame(95700.0, $first['line_amount']);
        $this->assertSame(0.0, $first['color_count']);
    }

    public function test_rejects_workbook_with_multiple_sheets()
    {
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), [
            $this->sampleRow('1000001', '2026/09/01'),
        ]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $spreadsheet->createSheet()->setTitle('余分なシート');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

            $reader = new SalesWorkbookReader();

            $this->expectException(SalesWorkbookException::class);
            $reader->read($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_empty_workbook()
    {
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), []);

        try {
            $reader = new SalesWorkbookReader();

            $this->expectException(SalesWorkbookException::class);
            $reader->read($path);
        } finally {
            @unlink($path);
        }
    }

    private function sampleRow(string $orderNumber, string $plateDate): array
    {
        return [
            'order_number' => $orderNumber,
            'client_name' => 'テスト商事',
            'product_name' => 'テスト商品',
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_amount' => 1000,
            'order_amount_component' => 1000,
            'plate_date' => $plateDate,
        ];
    }
}
