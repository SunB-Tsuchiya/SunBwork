<?php

namespace App\Services\SalesAnalysis;

use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * 印刷帳票ソフト出力のxlsx（年次一括／月次）を読み取る。
 * 計算式は評価しない（getValueのみ使用。getCalculatedValueは使わない）。
 */
class SalesWorkbookReader
{
    private const HEADER_ROW = 2;

    private const DATA_START_ROW = 3;

    /** 列 => 内部キー */
    private const COLUMNS = [
        'A' => 'order_number',
        'B' => 'client_name',
        'C' => 'product_name',
        'D' => 'part_name',
        'E' => 'category',
        'F' => 'item_name',
        'G' => 'progress',
        'H' => 'remarks',
        'I' => 'format_size',
        'J' => 'color_count',
        'K' => 'quantity',
        'L' => 'unit_price',
        'M' => 'line_amount',
        'N' => 'order_amount_component',
        'O' => 'plate_date',
    ];

    /** 列 => 期待される見出し文字列（正規化後比較） */
    private const EXPECTED_HEADERS = [
        'A' => '受注No',
        'B' => '得意先名',
        'C' => '品名',
        'D' => '部品名',
        'E' => '分類',
        'F' => '項目',
        'G' => '進行',
        'H' => '備考',
        'I' => '判型',
        'J' => '色数',
        'K' => '台数',
        'L' => '単価',
        'M' => '金額',
        'N' => '受注金額',
        'O' => 'SB下版日',
    ];

    public function read(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $e) {
            throw new SalesWorkbookException('xlsxファイルを読み込めませんでした。');
        }

        if ($spreadsheet->getSheetCount() !== 1) {
            throw new SalesWorkbookException('対象シートが1つではありません。余分なシートを削除してください。');
        }

        $sheet = $spreadsheet->getActiveSheet();

        $title = trim((string) $sheet->getCell('A1')->getValue());
        if ($title === '') {
            throw new SalesWorkbookException('タイトル行（1行目）が空です。');
        }

        $titleInfo = $this->parseTitle($title);
        $headerMismatches = $this->checkHeaders($sheet);
        $rows = $this->readRows($sheet);

        if (count($rows) === 0) {
            throw new SalesWorkbookException('明細データが1件もありません。');
        }

        return [
            'sheet_title' => $title,
            'department_label' => $titleInfo['department_label'],
            'title_year' => $titleInfo['year'],
            'title_month' => $titleInfo['month'],
            'header_mismatches' => $headerMismatches,
            'rows' => $rows,
        ];
    }

    /**
     * 例: "サン・ブレーン作業明細 2026年08月 (企画、制作、ｵﾝﾃﾞﾏﾝﾄﾞ) 明細 (企画)"
     * 年次は月部分が無い想定: "サン・ブレーン作業明細 2026年 (...) 明細 (企画)"
     * 年次のタイトル書式は未確認のため、年月の抽出に失敗してもここでは例外にせず
     * null を返し、月判定は呼び出し側（Validator）が行の下版日で行う。
     */
    private function parseTitle(string $title): array
    {
        $normalized = preg_replace('/[\x{3000}\s]+/u', ' ', $title);

        if (! preg_match(
            '/(\d{4})年(?:(\d{1,2})月)?.*明細\s*\(([^)]+)\)\s*$/u',
            $normalized,
            $m
        )) {
            return ['year' => null, 'month' => null, 'department_label' => null];
        }

        return [
            'year' => (int) $m[1],
            'month' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null,
            'department_label' => trim($m[3]),
        ];
    }

    private function checkHeaders(Worksheet $sheet): array
    {
        $mismatches = [];
        foreach (self::EXPECTED_HEADERS as $col => $expected) {
            $actual = trim((string) $sheet->getCell($col . self::HEADER_ROW)->getValue());

            if ($this->normalizeHeaderText($actual) !== $this->normalizeHeaderText($expected)) {
                $mismatches[] = [
                    'column' => $col,
                    'expected' => $expected,
                    'actual' => $actual,
                ];
            }
        }

        return $mismatches;
    }

    /** ふりがな注記・空白差異を吸収するため、空白と括弧書きの注記を除去して比較する */
    private function normalizeHeaderText(string $text): string
    {
        $text = preg_replace('/[\x{3000}\s]+/u', '', $text);

        return preg_replace('/[（(].*?[）)]/u', '', $text);
    }

    private function readRows(Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestRow();

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $raw = [];
            foreach (self::COLUMNS as $col => $key) {
                $raw[$key] = $sheet->getCell($col . $rowNumber)->getValue();
            }

            if ($this->isBlankRow($raw) || $this->isTotalRow($raw)) {
                continue;
            }

            $rows[] = [
                'source_row_number' => $rowNumber,
                'order_number' => $this->normalizeText($raw['order_number']),
                'client_name' => $this->normalizeText($raw['client_name']),
                'product_name' => $this->normalizeText($raw['product_name']),
                'part_name' => $this->normalizeText($raw['part_name']),
                'category' => $this->normalizeText($raw['category']),
                'item_name' => $this->normalizeText($raw['item_name']),
                'progress' => $this->normalizeText($raw['progress']),
                'remarks' => $this->normalizeText($raw['remarks']),
                'format_size' => $this->normalizeText($raw['format_size']),
                'color_count' => $this->normalizeNumber($raw['color_count']),
                'quantity' => $this->normalizeNumber($raw['quantity']),
                'unit_price' => $this->normalizeNumber($raw['unit_price']),
                'line_amount' => $this->normalizeNumber($raw['line_amount']),
                'order_amount_component' => $this->normalizeNumber($raw['order_amount_component']),
                'plate_date' => $this->normalizeDate($raw['plate_date']),
                'plate_date_raw' => $raw['plate_date'],
            ];
        }

        return $rows;
    }

    private function isBlankRow(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isTotalRow(array $raw): bool
    {
        return trim((string) $raw['client_name']) === '合計';
    }

    private function normalizeText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
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

    /** JSTの日付として 'Y-m-d' へ正規化する。Carbon::parse等のUTC変換は経由しない */
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (Throwable $e) {
                return null;
            }
        }

        $text = mb_convert_kana(trim((string) $value), 'n');

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }
}
