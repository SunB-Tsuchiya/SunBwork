<?php

namespace Tests\Unit\SalesAnalysis;

use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;
use App\Services\SalesAnalysis\SalesExpenseWorkbookReader;
use Tests\Concerns\BuildsExpenseWorkbook;
use Tests\TestCase;

class SalesExpenseWorkbookReaderTest extends TestCase
{
    use BuildsExpenseWorkbook;

    private function reader(): SalesExpenseWorkbookReader
    {
        return new SalesExpenseWorkbookReader();
    }

    public function test_parses_vendor_rows_into_line_items_with_calendar_year_month()
    {
        $path = $this->makeExpenseWorkbook([
            '情報出版' => [
                [
                    'category' => '主材料費',
                    'vendors' => [
                        ['name' => 'A社', 'amounts' => [4 => 100000, 1 => 50000]],
                    ],
                    'total_label' => '合計',
                ],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);

            $this->assertCount(2, $result['line_items']);

            $april = collect($result['line_items'])->firstWhere('calendar_month', 4);
            $this->assertSame('planning', $april['department_key']);
            $this->assertSame('主材料費', $april['category']);
            $this->assertSame('A社', $april['vendor_name']);
            $this->assertSame(2025, $april['calendar_year']);
            $this->assertSame(100000.0, $april['amount']);
            $this->assertFalse($april['is_shared_split']);

            // 1月は会計年度の翌年（fiscal_year+1）になる
            $january = collect($result['line_items'])->firstWhere('calendar_month', 1);
            $this->assertSame(2026, $january['calendar_year']);
            $this->assertSame(50000.0, $january['amount']);
        } finally {
            @unlink($path);
        }
    }

    public function test_skips_subtotal_rows_and_keeps_vendor_rows_under_nested_subtotals()
    {
        $path = $this->makeExpenseWorkbook([
            '製版' => [
                [
                    'category' => '外注加工費',
                    'vendors' => [
                        ['name' => 'B社', 'amounts' => [4 => 10000]],
                        ['name' => 'C社', 'amounts' => [4 => 20000]],
                    ],
                    'sub_totals' => ['小計①', '小計②'],
                    'total_label' => '合計',
                ],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);

            $this->assertCount(2, $result['line_items']);
            $vendorNames = collect($result['line_items'])->pluck('vendor_name')->sort()->values()->all();
            $this->assertSame(['B社', 'C社'], $vendorNames);
        } finally {
            @unlink($path);
        }
    }

    public function test_subtotal_rows_split_vendors_into_labeled_groups_in_excel_order()
    {
        $path = $this->makeExpenseWorkbook([
            '製版' => [
                [
                    'category' => '外注加工費',
                    'rows' => [
                        ['vendor' => 'B社', 'amounts' => [4 => 10000]],
                        ['vendor' => 'C社', 'amounts' => [4 => 20000]],
                        ['subtotal' => '小計①'],
                        ['vendor' => 'D社', 'amounts' => [4 => 5000]],
                        ['total' => '合計'],
                    ],
                ],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);
            $byVendor = collect($result['line_items'])->keyBy('vendor_name');

            $this->assertSame(1, $byVendor['B社']['subtotal_group']);
            $this->assertSame('小計①', $byVendor['B社']['subtotal_label']);
            $this->assertSame(1, $byVendor['C社']['subtotal_group']);
            $this->assertSame('小計①', $byVendor['C社']['subtotal_label']);

            // 合計行で閉じる最後のグループにはExcelに無い小計を合成しない（ラベルはnull）
            $this->assertSame(2, $byVendor['D社']['subtotal_group']);
            $this->assertNull($byVendor['D社']['subtotal_label']);
        } finally {
            @unlink($path);
        }
    }

    public function test_category_order_follows_excel_appearance_order_within_department()
    {
        $path = $this->makeExpenseWorkbook([
            '情報出版' => [
                ['category' => '主材料費', 'vendors' => [['name' => 'A社', 'amounts' => [4 => 1000]]], 'total_label' => '合計'],
                ['category' => '外注加工費', 'vendors' => [['name' => 'B社', 'amounts' => [4 => 2000]]], 'total_label' => '合計'],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);
            $byCategory = collect($result['line_items'])->keyBy('category');

            $this->assertSame(1, $byCategory['主材料費']['category_order']);
            $this->assertSame(2, $byCategory['外注加工費']['category_order']);
        } finally {
            @unlink($path);
        }
    }

    public function test_total_row_with_split_annotation_flags_all_items_in_that_category()
    {
        $path = $this->makeExpenseWorkbook([
            '共有' => [
                [
                    'category' => '通信費',
                    'vendors' => [
                        ['name' => 'D社', 'amounts' => [4 => 5000]],
                    ],
                    'total_label' => '合計(SAと折半)',
                ],
                [
                    'category' => '主材料費',
                    'vendors' => [
                        ['name' => 'E社', 'amounts' => [4 => 7000]],
                    ],
                    'total_label' => '合計',
                ],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);

            $communication = collect($result['line_items'])->firstWhere('category', '通信費');
            $this->assertTrue($communication['is_shared_split']);
            $this->assertSame('shared', $communication['department_key']);

            $material = collect($result['line_items'])->firstWhere('category', '主材料費');
            $this->assertFalse($material['is_shared_split']);
        } finally {
            @unlink($path);
        }
    }

    public function test_blank_vendor_name_is_kept_as_null_but_amount_is_recorded()
    {
        $path = $this->makeExpenseWorkbook([
            'オンデマンド' => [
                [
                    'category' => '雑費',
                    'vendors' => [
                        ['name' => null, 'amounts' => [5 => 3000]],
                    ],
                    'total_label' => '合計',
                ],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);

            $this->assertCount(1, $result['line_items']);
            $this->assertNull($result['line_items'][0]['vendor_name']);
            $this->assertSame(3000.0, $result['line_items'][0]['amount']);
        } finally {
            @unlink($path);
        }
    }

    public function test_zero_and_blank_amounts_do_not_create_line_items()
    {
        $path = $this->makeExpenseWorkbook([
            '情報出版' => [
                [
                    'category' => '消耗品費',
                    'vendors' => [
                        ['name' => 'F社', 'amounts' => [4 => 0, 5 => 1000]],
                    ],
                    'total_label' => '合計',
                ],
            ],
        ]);

        try {
            $result = $this->reader()->read($path, 2025);

            $this->assertCount(1, $result['line_items']);
            $this->assertSame(5, $result['line_items'][0]['calendar_month']);
        } finally {
            @unlink($path);
        }
    }

    public function test_unknown_sheet_name_is_rejected()
    {
        $path = $this->makeExpenseWorkbook([
            '不明部署' => [
                ['category' => '主材料費', 'vendors' => [['name' => 'A社', 'amounts' => [4 => 1000]]], 'total_label' => '合計'],
            ],
        ]);

        try {
            $this->expectException(SalesWorkbookException::class);
            $this->reader()->read($path, 2025);
        } finally {
            @unlink($path);
        }
    }

    public function test_title_year_mismatch_is_rejected_by_default()
    {
        // 誤った会計年度のまま取り込んでしまう事故が実際に発生したため、既定では止める
        $path = $this->makeExpenseWorkbook([
            '情報出版' => [
                ['category' => '主材料費', 'vendors' => [['name' => 'A社', 'amounts' => [4 => 1000]]], 'total_label' => '合計'],
            ],
        ], fiscalYear: 2024);

        try {
            $this->expectException(SalesWorkbookException::class);
            $this->expectExceptionMessageMatches('/一致しません/u');
            // ファイル本文のタイトルは2024年度だが、2025年度として取り込もうとする
            $this->reader()->read($path, 2025);
        } finally {
            @unlink($path);
        }
    }

    public function test_title_year_mismatch_is_allowed_as_warning_when_forced()
    {
        $path = $this->makeExpenseWorkbook([
            '情報出版' => [
                ['category' => '主材料費', 'vendors' => [['name' => 'A社', 'amounts' => [4 => 1000]]], 'total_label' => '合計'],
            ],
        ], fiscalYear: 2024);

        try {
            $result = $this->reader()->read($path, 2025, force: true);

            $this->assertCount(1, $result['line_items']);
            $this->assertNotEmpty($result['warnings']);
            $this->assertStringContainsString('一致しません', $result['warnings'][0]);
        } finally {
            @unlink($path);
        }
    }

    public function test_workbook_with_no_data_at_all_is_rejected()
    {
        $path = $this->makeExpenseWorkbook([
            '情報出版' => [
                ['category' => '主材料費', 'vendors' => [], 'total_label' => '合計'],
            ],
        ]);

        try {
            $this->expectException(SalesWorkbookException::class);
            $this->reader()->read($path, 2025);
        } finally {
            @unlink($path);
        }
    }
}
