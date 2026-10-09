<?php

namespace App\Services\SalesAnalysis;

use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * 部署別経費表（情報出版/製版/オンデマンド/共有の4シート、科目×会社名(支払先)×月の
 * ブロック構造）を読み取る（Phase 21）。計算式は評価しない（getValueのみ使用）。
 *
 * シート構造（PLAN Phase20 21.2節）:
 * - 1行目: タイトル（年度の自動判定には使わない。呼び出し側のフォーム入力を正とする）
 * - ヘッダー行: A列=「科目」、B列=「会社名」の行を探索する（決め打ちしない）
 * - 以降: 科目ブロックの繰り返し。A列に科目名がある行がブロック開始。
 *   B列に「小計」を含む行はExcel上の中間小計の区切り（金額は読まず、ラベル文字列のみ
 *   保持し、以降の行を次の小計グループとして扱う）。B列に「合計」を含む行はブロック終端
 *   マーカー（「折半」を含めば経路フラグを立てる。金額は読まない。この行で閉じる最後の
 *   グループには小計ラベルを付けない＝Excelに無い小計を合成しない）。それ以外の行は
 *   会社名（支払先）＋月別金額の明細行。
 */
class SalesExpenseWorkbookReader
{
    /** 列 => 会計年度内の月（4月始まり〜翌3月） */
    private const MONTH_COLUMNS = [
        'C' => 4, 'D' => 5, 'E' => 6, 'F' => 7, 'G' => 8, 'H' => 9,
        'I' => 10, 'J' => 11, 'K' => 12, 'L' => 1, 'M' => 2, 'N' => 3,
    ];

    private const MAX_HEADER_SEARCH_ROW = 10;

    /**
     * @param  bool  $force  trueの場合、タイトル行の年度と$fiscalYearが不一致でも例外にせず警告のみにする
     * @return array{line_items: array<int, array{department_key:string, category:string, vendor_name:?string, calendar_year:int, calendar_month:int, amount:float, is_shared_split:bool}>, warnings: array<int, string>, title_fiscal_year: ?int}
     * @throws SalesWorkbookException
     */
    public function read(string $filePath, int $fiscalYear, bool $force = false): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $e) {
            throw new SalesWorkbookException('経費表xlsxを読み込めませんでした。');
        }

        $sheets = $spreadsheet->getAllSheets();
        if (count($sheets) === 0) {
            throw new SalesWorkbookException('シートがありません。');
        }

        $unknownSheets = [];
        foreach ($sheets as $sheet) {
            if (! array_key_exists($sheet->getTitle(), SalesExpenses::SHEET_DEPARTMENT_MAP)) {
                $unknownSheets[] = $sheet->getTitle();
            }
        }
        if (! empty($unknownSheets)) {
            throw new SalesWorkbookException(
                '想定外のシートがあります: ' . implode('、', $unknownSheets)
                . '（想定シート: ' . implode('、', array_keys(SalesExpenses::SHEET_DEPARTMENT_MAP)) . '）'
            );
        }

        $lineItems = [];
        $warnings = [];
        $titleFiscalYear = null;

        foreach ($sheets as $sheet) {
            $departmentKey = SalesExpenses::SHEET_DEPARTMENT_MAP[$sheet->getTitle()];

            $parsedTitleYear = $this->parseTitleFiscalYear((string) $sheet->getCell('A1')->getValue());
            if ($parsedTitleYear !== null) {
                $titleFiscalYear ??= $parsedTitleYear;
            }

            [$sheetItems, $sheetWarnings] = $this->readSheet($sheet, $departmentKey, $fiscalYear);
            $lineItems = array_merge($lineItems, $sheetItems);
            $warnings = array_merge($warnings, $sheetWarnings);
        }

        if (count($lineItems) === 0) {
            throw new SalesWorkbookException('経費データが1件もありません。');
        }

        if ($titleFiscalYear !== null && $titleFiscalYear !== $fiscalYear) {
            // 誤った会計年度のまま取り込んでしまう事故が実際に発生したため、既定では取込を止める。
            // 意図的に別年度として取り込みたい場合のみ$forceで警告扱いに緩和する
            if (! $force) {
                throw new SalesWorkbookException(
                    "タイトル行の年度（{$titleFiscalYear}年度）と指定した会計年度（{$fiscalYear}年度）が一致しません。"
                    . '会計年度の入力を確認してください。意図的に別の年度として取り込む場合は「警告があっても取り込む」にチェックを入れて再実行してください。'
                );
            }

            $warnings[] = "タイトル行の年度（{$titleFiscalYear}年度）と指定した会計年度（{$fiscalYear}年度）が一致しません。入力した会計年度を正として取り込みました。";
        }

        return ['line_items' => $lineItems, 'warnings' => $warnings, 'title_fiscal_year' => $titleFiscalYear];
    }

    /** 例: "55期(2025年4月～2026年3月）　情報出版部経費" → 2025 */
    private function parseTitleFiscalYear(string $title): ?int
    {
        if (preg_match('/(\d{4})年4月/u', $title, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @return array{0: array<int, array>, 1: array<int, string>}
     */
    private function readSheet(Worksheet $sheet, string $departmentKey, int $fiscalYear): array
    {
        $highestRow = $sheet->getHighestRow();
        $headerRow = $this->findHeaderRow($sheet, $highestRow);

        if ($headerRow === null) {
            throw new SalesWorkbookException(
                "シート「{$sheet->getTitle()}」にヘッダー行（A列「科目」・B列「会社名」）が見つかりません。"
            );
        }

        $items = [];
        $warnings = [];
        $currentCategory = null;
        $categoryOrder = 0;
        $subtotalGroup = 1;
        $buffer = [];

        $flush = function (bool $isSplit, ?string $subtotalLabel) use (&$items, &$buffer, $departmentKey, $fiscalYear) {
            foreach ($buffer as $pending) {
                $items[] = [
                    'department_key' => $departmentKey,
                    'category' => $pending['category'],
                    'category_order' => $pending['category_order'],
                    'vendor_name' => $pending['vendor_name'],
                    'calendar_year' => $this->calendarYearForMonth($fiscalYear, $pending['month']),
                    'calendar_month' => $pending['month'],
                    'amount' => $pending['amount'],
                    'is_shared_split' => $isSplit,
                    'subtotal_group' => $pending['subtotal_group'],
                    'subtotal_label' => $subtotalLabel,
                ];
            }
            $buffer = [];
        };

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $colA = $this->normalizeLabel((string) $sheet->getCell("A{$row}")->getValue());
            $colB = trim((string) $sheet->getCell("B{$row}")->getValue());

            if ($colA !== '') {
                // 新しい科目の開始。前の科目に明示的な合計行が無かった場合は折半なし・小計ラベル無しでflushする
                $flush(false, null);
                $currentCategory = $colA;
                $categoryOrder++;
                $subtotalGroup = 1;
            }

            if ($currentCategory === null) {
                continue;
            }

            if (mb_strpos($colB, '小計') !== false) {
                // Excel上の中間小計の区切り。グループをラベル付きで閉じ、次の行から新しいグループにする
                $flush(false, $colB !== '' ? $colB : '小計');
                $subtotalGroup++;

                continue;
            }

            if (mb_strpos($colB, '合計') !== false) {
                // 最終グループ。Excelに無い小計を合成しないためラベルは付けない
                $flush(mb_strpos($colB, '折半') !== false, null);

                continue;
            }

            $vendorName = $colB !== '' ? $colB : null;
            foreach (self::MONTH_COLUMNS as $col => $month) {
                $amount = $this->normalizeNumber($sheet->getCell("{$col}{$row}")->getValue());
                if ($amount === null || $amount == 0.0) {
                    continue;
                }

                $buffer[] = [
                    'category' => $currentCategory,
                    'category_order' => $categoryOrder,
                    'vendor_name' => $vendorName,
                    'month' => $month,
                    'amount' => $amount,
                    'subtotal_group' => $subtotalGroup,
                ];
            }
        }

        // シート末尾まで合計行が無いまま終わった最後の科目ブロック
        $flush(false, null);

        return [$items, $warnings];
    }

    private function findHeaderRow(Worksheet $sheet, int $highestRow): ?int
    {
        $limit = min(self::MAX_HEADER_SEARCH_ROW, $highestRow);

        for ($row = 1; $row <= $limit; $row++) {
            $a = trim((string) $sheet->getCell("A{$row}")->getValue());
            $b = trim((string) $sheet->getCell("B{$row}")->getValue());

            if ($a === '科目' && $b === '会社名') {
                return $row;
            }
        }

        return null;
    }

    /** 科目名はセル内改行・全角空白を含むことがあるため正規化する（例: "業務\n委託料"→"業務委託料"） */
    private function normalizeLabel(string $value): string
    {
        return preg_replace('/[\x{3000}\s]+/u', '', $value);
    }

    private function normalizeNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = mb_convert_kana((string) $value, 'n');
        $text = str_replace([',', '，', ' ', '　'], '', $text);

        return is_numeric($text) ? (float) $text : null;
    }

    /** 4〜12月はfiscal_yearの暦年、1〜3月はfiscal_year+1の暦年になる */
    private function calendarYearForMonth(int $fiscalYear, int $month): int
    {
        return $month >= 4 ? $fiscalYear : $fiscalYear + 1;
    }
}
