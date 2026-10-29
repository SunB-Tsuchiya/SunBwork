<?php

namespace App\Services\SalesAnalysis;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 経費のExcel出力（Phase24-4）。画面（部署シート・部署比較）と同じ内容を、部署ごとのシートで出力する。
 * 先頭に「部署比較」シート、続けて部署ごとに「科目→支払先→4月〜3月→累計」の部署シート（小計・合計つき）。
 * 比較年度の累計と増減も付ける（期の途中の年度は同期間比較）。粗利計算はしない。
 *
 * formula injection対策: 科目名・支払先名など取込Excel由来の文字列は、SalesExportServiceと同じく
 * TYPE_STRINGで明示的に書き込み、先頭が=+-@等の場合はアポストロフィを付与する。
 */
class SalesExpenseExportService
{
    private const MONTH_COLUMNS = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];

    public function __construct(private SalesExpenseQueryService $queryService)
    {
    }

    private ?int $companyId = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->queryService->forCompany($companyId);

        return $this;
    }

    /**
     * @param  array<string, string>  $departmentLabels 部署キー=>表示名（出力する部署を、この順で）
     */
    public function workbook(int $fiscalYear, array $departmentLabels, ?int $compareFiscalYear = null, ?string $comparePeriod = null, ?User $viewer = null): Spreadsheet
    {
        $overview = $this->queryService->departmentsOverview($fiscalYear, array_keys($departmentLabels), $compareFiscalYear, $comparePeriod);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->buildOverviewSheet($spreadsheet, $overview, $departmentLabels);
        foreach ($departmentLabels as $key => $label) {
            $sheet = $this->queryService->departmentSheet($fiscalYear, $key, $compareFiscalYear, $comparePeriod);
            if ($sheet['has_data']) {
                // 経費メモ（Phase24-5）: その年度のメモを「メモ」列に出す（viewerが無ければ出さない）
                $notes = $viewer !== null && $this->companyId !== null
                    ? (new SalesExpenseNoteService())->forCompany($this->companyId)->forDepartment($key, $fiscalYear, $viewer)['notes']
                    : [];
                $this->buildDepartmentSheet($spreadsheet, $label, $sheet, $notes);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** 「56期（2026年度）」。経費機能はサン・ブレーン専用で、期数=会計年度-1970（画面のfiscalPeriodLabelと同じ） */
    private function periodLabel(int $fiscalYear): string
    {
        return ($fiscalYear - 1970) . "期（{$fiscalYear}年度）";
    }

    /** 「◯月まで実績（同期間比較）」など、出力の条件を1行で表す */
    private function conditionText(array $data): string
    {
        $parts = [];
        if ($data['actual_months'] > 0) {
            $month = $data['months'][$data['actual_months'] - 1]['calendar_month'];
            $parts[] = "{$month}月まで実績" . ($data['is_partial'] ? '（期の途中）' : '');
        }
        $parts[] = $data['previous_has_data']
            ? '比較: ' . $this->periodLabel($data['previous_fiscal_year']) . ($data['compare_period'] === 'ytd' ? '（同期間）' : '（通年）')
            : '比較年度のデータなし';

        return implode(' / ', $parts);
    }

    private function buildOverviewSheet(Spreadsheet $spreadsheet, array $overview, array $departmentLabels): void
    {
        $sheet = new Worksheet($spreadsheet, '部署比較');
        $spreadsheet->addSheet($sheet);

        $this->setText($sheet, 'A1', $this->periodLabel($overview['fiscal_year']) . ' 経費 部署比較');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->setText($sheet, 'A2', $this->conditionText($overview));

        $headers = ['部署', '金額', '構成比(%)', '比較年度', '増減額', '増減率(%)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(ord('A') + $i) . '4', $h);
        }
        $this->styleHeader($sheet, 'A4:F4');

        $row = 5;
        foreach ($overview['departments'] as $d) {
            $this->setText($sheet, "A{$row}", $departmentLabels[$d['department_key']] ?? $d['department_key']);
            $this->setAmount($sheet, "B{$row}", $d['total_amount']);
            $this->setNumber($sheet, "C{$row}", $d['share_pct'], '0.0');
            $this->setAmount($sheet, "D{$row}", $d['previous_total_amount']);
            $this->setAmount($sheet, "E{$row}", $d['diff']);
            $this->setNumber($sheet, "F{$row}", $d['diff_pct'], '0.0');
            $row++;
        }
        $this->setText($sheet, "A{$row}", '合計');
        $this->setAmount($sheet, "B{$row}", $overview['total_amount']);
        $this->setAmount($sheet, "D{$row}", $overview['previous_total_amount']);
        $this->setAmount($sheet, "E{$row}", $overview['previous_total_amount'] === null ? null : $overview['total_amount'] - $overview['previous_total_amount']);
        $this->setNumber($sheet, "F{$row}", $overview['diff_pct'], '0.0');
        $this->styleTotal($sheet, "A{$row}:F{$row}");

        // 科目×部署のクロス表
        $row += 3;
        $this->setText($sheet, "A{$row}", '科目×部署');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", '科目');
        $col = 'B';
        foreach ($overview['departments'] as $d) {
            $this->setText($sheet, "{$col}{$row}", $departmentLabels[$d['department_key']] ?? $d['department_key']);
            $col++;
        }
        $sheet->setCellValue("{$col}{$row}", '合計');
        $this->styleHeader($sheet, "A{$row}:{$col}{$row}");
        $row++;
        foreach ($overview['cross'] as $c) {
            $this->setText($sheet, "A{$row}", $c['category']);
            $col = 'B';
            foreach ($overview['departments'] as $d) {
                $this->setAmount($sheet, "{$col}{$row}", $c['by_department'][$d['department_key']] ?? null);
                $col++;
            }
            $this->setAmount($sheet, "{$col}{$row}", $c['total_amount']);
            $row++;
        }

        foreach (range('A', 'H') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    /** @param  array<int, array>  $notes その年度のメモ（名寄せ後の科目名・支払先名に読み替え済み） */
    private function buildDepartmentSheet(Spreadsheet $spreadsheet, string $label, array $data, array $notes = []): void
    {
        // シート名は31文字まで・一部の記号は使えない
        $sheet = new Worksheet($spreadsheet, mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $label), 0, 31));
        $spreadsheet->addSheet($sheet);

        $this->setText($sheet, 'A1', $this->periodLabel($data['fiscal_year']) . " {$label} 経費");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->setText($sheet, 'A2', $this->conditionText($data));
        $departmentNotes = $this->noteText($notes, null, null);
        if ($departmentNotes !== '') {
            $this->setText($sheet, 'A3', '部署メモ: ' . $departmentNotes);
        }

        // ヘッダー（4行目）: 科目 / 支払先 / 4月〜3月 / 累計 / 構成比 / 比較年度累計 / 増減額 / 増減率
        $headers = ['科目', '支払先'];
        foreach ($data['months'] as $m) {
            $headers[] = $m['calendar_month'] . '月';
        }
        array_push($headers, '累計', '構成比(%)', '比較年度累計', '増減額', '増減率(%)', 'メモ');
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->col($i) . '4', $h);
        }
        $lastCol = $this->col(count($headers) - 1);
        $this->styleHeader($sheet, "A4:{$lastCol}4");

        $row = 5;
        foreach ($data['categories'] as $c) {
            // 科目の行（月別合計・累計・構成比・比較）
            $this->setText($sheet, "A{$row}", $c['category'] . ($c['is_shared_split'] ? '（折半）' : ''));
            $this->writeAmounts($sheet, $row, $c['months'], $c['total_amount'], $c['share_pct'], $c['previous_total_amount'], $c['diff'], $c['diff_pct'], $c['is_new']);
            $this->setText($sheet, "T{$row}", $this->noteText($notes, $c['category'], null));
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
            $row++;

            foreach ($c['groups'] as $g) {
                foreach ($g['vendors'] as $v) {
                    $this->setText($sheet, "B{$row}", $v['vendor_name']);
                    $share = $c['total_amount'] > 0 ? round($v['total_amount'] / $c['total_amount'] * 100, 1) : null;
                    $this->writeAmounts($sheet, $row, $v['months'], $v['total_amount'], $share, $v['previous_total_amount'], $v['diff'], $v['diff_pct'], $v['is_new']);
                    $this->setText($sheet, "T{$row}", $this->noteText($notes, $c['category'], $v['vendor_name']));
                    $row++;
                }
                if ($g['subtotal_label']) {
                    $this->setText($sheet, "B{$row}", $g['subtotal_label']);
                    $this->writeAmounts($sheet, $row, $g['subtotal_months'], $g['subtotal_amount'], null, null, null, null, false);
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                    $row++;
                }
            }
        }

        // 合計行
        $this->setText($sheet, "A{$row}", '合計');
        $previous = $data['previous_total_amount'];
        $this->writeAmounts(
            $sheet, $row, $data['month_totals'], $data['total_amount'], 100.0, $previous,
            $previous === null ? null : $data['total_amount'] - $previous,
            ($previous !== null && $previous > 0) ? round(($data['total_amount'] - $previous) / $previous * 100, 1) : null,
            false,
        );
        $this->styleTotal($sheet, "A{$row}:{$lastCol}{$row}");

        $sheet->freezePane('C5');
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(34);
        foreach (self::MONTH_COLUMNS as $c) {
            $sheet->getColumnDimension($c)->setWidth(11);
        }
        foreach (['O', 'P', 'Q', 'R', 'S'] as $c) {
            $sheet->getColumnDimension($c)->setWidth(13);
        }
        $sheet->getColumnDimension('T')->setWidth(48);
    }

    /**
     * 指定の付け先（部署全体=null,null／科目=科目,null／支払先=科目,支払先）のメモを、「4月: 本文 / 本文」の形に1つの文字列へまとめる。
     *
     * @param  array<int, array>  $notes
     */
    private function noteText(array $notes, ?string $category, ?string $vendor): string
    {
        $texts = [];
        foreach ($notes as $n) {
            if ($n['category'] === $category && $n['vendor_name'] === $vendor) {
                $texts[] = ($n['calendar_month'] ? "{$n['calendar_month']}月: " : '') . $n['body'];
            }
        }

        return implode(' / ', $texts);
    }

    /** 1行ぶんの金額（月別12列・累計・構成比・比較年度累計・増減額・増減率）を書く。0円の月は空欄にする */
    private function writeAmounts(Worksheet $sheet, int $row, array $months, float $total, ?float $share, ?float $previous, ?float $diff, ?float $diffPct, bool $isNew): void
    {
        foreach ($months as $i => $v) {
            if ($v > 0) {
                $this->setAmount($sheet, self::MONTH_COLUMNS[$i] . $row, $v);
            }
        }
        $this->setAmount($sheet, "O{$row}", $total);
        $this->setNumber($sheet, "P{$row}", $share, '0.0');
        $this->setAmount($sheet, "Q{$row}", $previous);
        $this->setAmount($sheet, "R{$row}", $diff);
        if ($isNew) {
            $this->setText($sheet, "S{$row}", '新規');
        } else {
            $this->setNumber($sheet, "S{$row}", $diffPct, '0.0');
        }
    }

    private function col(int $index): string
    {
        return chr(ord('A') + $index);
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    }

    private function styleTotal(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    }

    /** 金額セル（システム計算値）。nullは空欄 */
    private function setAmount(Worksheet $sheet, string $coordinate, ?float $value): void
    {
        if ($value === null) {
            return;
        }
        $sheet->setCellValue($coordinate, round($value));
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
    }

    private function setNumber(Worksheet $sheet, string $coordinate, ?float $value, string $format): void
    {
        if ($value === null) {
            return;
        }
        $sheet->setCellValue($coordinate, $value);
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($format);
    }

    /** 取込Excel由来の文字列セル（formula injection対策つき） */
    private function setText(Worksheet $sheet, string $coordinate, ?string $value): void
    {
        $text = $value ?? '';
        if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text)) {
            $text = "'" . $text;
        }
        $sheet->setCellValueExplicit($coordinate, $text, DataType::TYPE_STRING);
    }
}
