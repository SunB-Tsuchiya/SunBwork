<?php

namespace App\Services\SunA\Sales;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 営業部の「得意先別」売上Excel（形式 v1）と同じ形のExcelを書き出す。
 * 用途: ①自動テストのダミーExcel ②ローカル確認用のダミーデータ作成コマンド（架空の得意先名）。
 * 実データは扱わない（本番の売上Excelをここから作らない）。形式は ClientSalesV1Reader の読み取りルールと対応している。
 *
 * レイアウト: 1行目=タイトル・単位／2行目=列見出し（フィルタ・得意先名・年度・1月〜12月・合計・担当部門）／
 * 3行目=月ごとの「売上金額」「受注件数」／4行目〜=得意先×年度の行／最終行=総計。
 * A列=フィルタ用の得意先名（全行）、B列=得意先名（得意先の先頭行だけ）、C列=「2024年度」、AE列=担当部門。
 */
class ClientSalesWorkbookWriter
{
    /**
     * @param  array<int, array{client: string, year: int, department: string, months?: array<int, array{0:?float,1:?int}>}>  $rows
     *         months: 月(1〜12) => [売上金額, 受注件数]。空欄（未登録）の月は含めない。金額か件数の片方が null なら、その列だけ空欄
     * @param  array{total_row?: bool, total_label?: string, omit_year_suffix?: bool}  $options
     *         total_row=false で総計行を書かない。omit_year_suffix=true で C列を「2024」（年度の語なし）にする
     */
    public function write(array $rows, string $path, array $options = []): string
    {
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Sheet2');

        $ws->setCellValue('B1', '2023年～2026年9月_得意先別');
        $ws->setCellValue('AB1', '（売上金額単位：円）');

        foreach (['A2' => 'フィルタ', 'B2' => '得意先名', 'C2' => '年度', 'AB2' => '合計', 'AE2' => '担当部門'] as $cell => $text) {
            $ws->setCellValue($cell, $text);
        }
        for ($m = 1; $m <= 12; $m++) {
            $col = Coordinate::stringFromColumnIndex(4 + 2 * ($m - 1));
            $ws->setCellValue("{$col}2", "{$m}月");
        }
        for ($i = 0; $i < 13; $i++) { // 12か月 + 合計
            $amountCol = Coordinate::stringFromColumnIndex(4 + 2 * $i);
            $countCol = Coordinate::stringFromColumnIndex(5 + 2 * $i);
            $ws->setCellValue("{$amountCol}3", '売上金額');
            $ws->setCellValue("{$countCol}3", '受注件数');
        }

        $row = 4;
        $previousClient = null;
        $grandAmount = 0.0;
        $grandCount = 0;
        foreach ($rows as $r) {
            $ws->setCellValue("A{$row}", $r['client']);
            if ($r['client'] !== $previousClient) {
                $ws->setCellValue("B{$row}", $r['client']); // B列は得意先の先頭行だけ（実物は縦結合）
            }
            $previousClient = $r['client'];
            $ws->setCellValue("C{$row}", ($options['omit_year_suffix'] ?? false) ? (string) $r['year'] : "{$r['year']}年度");
            $ws->setCellValue("AE{$row}", $r['department']);

            $sumAmount = 0.0;
            $sumCount = 0;
            foreach ($r['months'] ?? [] as $month => [$amount, $count]) {
                $amountCol = Coordinate::stringFromColumnIndex(4 + 2 * ($month - 1));
                $countCol = Coordinate::stringFromColumnIndex(5 + 2 * ($month - 1));
                if ($amount !== null) {
                    $ws->setCellValue("{$amountCol}{$row}", $amount);
                    $sumAmount += $amount;
                }
                if ($count !== null) {
                    $ws->setCellValue("{$countCol}{$row}", $count);
                    $sumCount += $count;
                }
            }
            $ws->setCellValue("AB{$row}", $sumAmount);
            $ws->setCellValue("AC{$row}", $sumCount);
            $grandAmount += $sumAmount;
            $grandCount += $sumCount;
            $row++;
        }

        if ($options['total_row'] ?? true) {
            $row += 2; // 実物は、データの後に空行を挟んで総計行がある
            $ws->setCellValue("B{$row}", $options['total_label'] ?? '総計');
            $ws->setCellValue("AB{$row}", $options['total_amount'] ?? $grandAmount);
            $ws->setCellValue("AC{$row}", $options['total_count'] ?? $grandCount);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
