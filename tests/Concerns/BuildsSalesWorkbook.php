<?php

namespace Tests\Concerns;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * テスト用に架空の売上明細xlsxをその場で組み立てる。
 * 本番sales DBのデータは一切使わない（架空データのみ）。
 */
trait BuildsSalesWorkbook
{
    private const HEADER_ROW_JA = ['受注No', '得意先名', '品名', '部品名', '分類', '項目', '進行', '備考', '判型', '色数', '台数', '単価', '金額', '受注金額', 'SB下版日'];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function makeSalesWorkbook(string $title, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', $title);

        foreach (self::HEADER_ROW_JA as $i => $header) {
            $sheet->setCellValue([$i + 1, 2], $header);
        }

        $rowIndex = 3;
        foreach ($rows as $row) {
            $values = [
                $row['order_number'],
                $row['client_name'],
                $row['product_name'],
                $row['part_name'] ?? '',
                $row['category'],
                $row['item_name'],
                $row['progress'] ?? '',
                $row['remarks'] ?? '',
                $row['format_size'],
                $row['color_count'],
                $row['quantity'],
                $row['unit_price'],
                $row['line_amount'],
                $row['order_amount_component'],
                $row['plate_date'],
            ];
            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $rowIndex], $value);
            }
            $rowIndex++;
        }

        // 合計行（B列のみ「合計」、他は空）
        $sheet->setCellValue([2, $rowIndex], '合計');

        $path = tempnam(sys_get_temp_dir(), 'sales_test_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function monthlyTitle(string $departmentLabel, int $year, int $month): string
    {
        return sprintf(
            'サン・ブレーン作業明細　%d年%02d月 (企画、制作、ｵﾝﾃﾞﾏﾝﾄﾞ)　明細 (%s)',
            $year,
            $month,
            $departmentLabel
        );
    }

    private function annualTitle(string $departmentLabel, int $year): string
    {
        return sprintf(
            'サン・ブレーン作業明細　%d年 (企画、制作、ｵﾝﾃﾞﾏﾝﾄﾞ)　明細 (%s)',
            $year,
            $departmentLabel
        );
    }
}
