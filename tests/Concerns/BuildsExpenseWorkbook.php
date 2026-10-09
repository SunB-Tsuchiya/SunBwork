<?php

namespace Tests\Concerns;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Phase 21: テスト用に架空の部署別経費表xlsxをその場で組み立てる。
 * 本番sales DBのデータは一切使わない（架空データのみ）。
 */
trait BuildsExpenseWorkbook
{
    private const EXPENSE_MONTH_COLUMNS = [
        4 => 'C', 5 => 'D', 6 => 'E', 7 => 'F', 8 => 'G', 9 => 'H',
        10 => 'I', 11 => 'J', 12 => 'K', 1 => 'L', 2 => 'M', 3 => 'N',
    ];

    /**
     * @param  array<string, array<int, array{category: string, vendors?: array<int, array{name: ?string, amounts: array<int, float>}>, sub_totals?: array<int, string>, total_label?: string, rows?: array<int, array>}>>  $sheets
     *         キー: シート名（情報出版/製版/オンデマンド/共有）、値: 科目ブロックの配列。
     *         `rows`を指定した場合は会社名明細行・小計行・合計行を好きな順序で並べられる
     *         （例: [['vendor'=>'A社','amounts'=>[4=>100]], ['subtotal'=>'小計①'], ['vendor'=>'B社','amounts'=>[4=>200]], ['total'=>'合計']]）。
     *         `rows`未指定時は従来どおり`vendors`→`sub_totals`→`total_label`の順で機械的に並べる。
     */
    private function makeExpenseWorkbook(array $sheets, int $fiscalYear = 2025): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $blocks) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->setCellValue('A1', "55期({$fiscalYear}年4月～" . ($fiscalYear + 1) . "年3月）　{$title}部経費");
            $sheet->setCellValue('A2', '科目');
            $sheet->setCellValue('B2', '会社名');
            foreach (['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'] as $i => $label) {
                $sheet->setCellValue([3 + $i, 2], $label);
            }
            $sheet->setCellValue([15, 2], '累計');

            $row = 3;
            foreach ($blocks as $block) {
                $sheet->setCellValue("A{$row}", $block['category']);

                $rows = $block['rows'] ?? $this->legacyBlockRows($block);

                foreach ($rows as $entry) {
                    if (array_key_exists('vendor', $entry)) {
                        $sheet->setCellValue("B{$row}", $entry['vendor'] ?? '');
                        foreach ($entry['amounts'] as $month => $amount) {
                            $col = self::EXPENSE_MONTH_COLUMNS[$month];
                            $sheet->setCellValue("{$col}{$row}", $amount);
                        }
                    } elseif (array_key_exists('subtotal', $entry)) {
                        $sheet->setCellValue("B{$row}", $entry['subtotal']);
                    } elseif (array_key_exists('total', $entry)) {
                        $sheet->setCellValue("B{$row}", $entry['total']);
                    }
                    $row++;
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'expense_test_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** `rows`未指定時の互換変換: vendors→sub_totals→total_labelの順に機械的に並べる */
    private function legacyBlockRows(array $block): array
    {
        $rows = [];
        foreach ($block['vendors'] ?? [] as $vendor) {
            $rows[] = ['vendor' => $vendor['name'], 'amounts' => $vendor['amounts']];
        }
        foreach ($block['sub_totals'] ?? [] as $label) {
            $rows[] = ['subtotal' => $label];
        }
        if (isset($block['total_label'])) {
            $rows[] = ['total' => $block['total_label']];
        }

        return $rows;
    }
}
