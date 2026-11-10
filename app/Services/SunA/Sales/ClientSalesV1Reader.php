<?php

namespace App\Services\SunA\Sales;

use App\Services\SalesAnalysis\ClientNameNormalizer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 営業部の「得意先別」売上Excel（形式 v1）の読み取り。形式の詳細: SUNASALES_PLAN1.md §4。
 *
 * 1行 = 得意先 × 年度（暦年。サンエー印刷は1月〜12月が1期）。D〜AA列 = 1月〜12月 ×（売上金額, 受注件数）、
 * AB〜AC列 = 合計、AE列 = 担当部門。得意先名は A列（全行に入っている）から取る（B列は縦結合で先頭行にしか値が無い）。
 *
 * ルール:
 * - 金額・件数がどちらも空欄の月は「未登録」（その月は行を作らない）。どちらかに値があれば登録済み（空の側は0）
 * - 総計行・空行・ヘッダー行は読み飛ばす。合計列と月合計の不一致、総計行との不一致は警告
 * - 担当部門の表記 → 部・課の対応付けは、取込サービス側（DB の部門マスタ）で行う。ここは表記をそのまま返す
 * - 同じ得意先×年が重複していたらエラー（行番号つき）
 *
 * 解析だけを行い、DB には触れない。読み取りの結果は配列で返し、エラー・警告も配列に集める
 * （致命的な形式エラーは SunaSalesImportException）。
 */
class ClientSalesV1Reader
{
    public const FORMAT = 'client_sales_v1';

    private const FIRST_DATA_ROW = 4;

    private const MAX_ROWS = 20000;

    private const MAX_ISSUES = 200;

    /** 売上金額・受注件数の「合計」列（AB, AC） */
    private const TOTAL_AMOUNT_COL = 28;

    private const TOTAL_COUNT_COL = 29;

    private const DEPARTMENT_COL = 31; // AE

    /**
     * @return array{format:string, sheet:string, rows:array<int, array<string, mixed>>, errors:array<int, string>, warnings:array<int, string>, year_min:?int, year_max:?int, row_count:int}
     */
    public function read(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            throw new SunaSalesImportException('Excelファイルを読み込めませんでした。.xlsx 形式のファイルか確認してください。');
        }

        $sheet = null;
        foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
            if ($this->matchesLayout($candidate)) {
                $sheet = $candidate;
                break;
            }
        }
        if ($sheet === null) {
            throw new SunaSalesImportException('「得意先別」の売上Excelの形式ではありません。2行目の見出し（フィルタ・得意先名・年度・1月〜12月・合計・担当部門）と、3行目の「売上金額／受注件数」を確認してください。');
        }

        $result = $this->parse($sheet);
        $spreadsheet->disconnectWorksheets();

        return $result;
    }

    /** 見出し行（2〜3行目）が、この形式のものか */
    public function matchesLayout(Worksheet $ws): bool
    {
        $header = fn (int $col, int $row = 2) => $this->normalizeLabel($ws->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue());

        if (! str_contains($header(2), '得意先') || ! str_contains($header(3), '年度') || ! str_contains($header(self::DEPARTMENT_COL), '担当部門')) {
            return false;
        }
        for ($m = 1; $m <= 12; $m++) {
            if ($header(4 + 2 * ($m - 1)) !== "{$m}月") {
                return false;
            }
            if (! str_contains($header(4 + 2 * ($m - 1), 3), '売上') || ! str_contains($header(5 + 2 * ($m - 1), 3), '件数')) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function parse(Worksheet $ws): array
    {
        $errors = [];
        $warnings = [];
        $rows = [];
        $seen = []; // client_key|year => 行番号
        $sumAmount = 0.0;
        $sumCount = 0;
        $grand = null; // 総計行の [金額, 件数]

        $last = min($ws->getHighestRow(), self::FIRST_DATA_ROW + self::MAX_ROWS);
        if ($ws->getHighestRow() > $last) {
            $errors[] = 'ファイルの行数が多すぎます（' . self::MAX_ROWS . '行まで）。';
        }

        for ($r = self::FIRST_DATA_ROW; $r <= $last; $r++) {
            $a = $this->text($ws, 1, $r);
            $b = $this->text($ws, 2, $r);
            $yearText = $this->text($ws, 3, $r);

            if ($a === '' && $b === '' && $yearText === '') {
                continue; // 空行
            }
            // 総計行: 年が無く、B列（または A列）に「総計」「合計」。取込対象にしない（検算に使う）
            if ($yearText === '' && preg_match('/^(総計|合計)/u', $b !== '' ? $b : $a)) {
                $grand = [$this->number($ws, self::TOTAL_AMOUNT_COL, $r), $this->number($ws, self::TOTAL_COUNT_COL, $r)];
                continue;
            }

            $name = $a !== '' ? $a : $b;
            if ($name === '') {
                $this->addIssue($errors, "{$r}行目: 得意先名が空です。");
                continue;
            }
            $year = $this->parseYear($yearText);
            if ($year === null) {
                $this->addIssue($errors, "{$r}行目: 年度「{$yearText}」を読み取れません（例: 2024年度）。");
                continue;
            }
            $department = $this->text($ws, self::DEPARTMENT_COL, $r);
            if ($department === '') {
                $this->addIssue($errors, "{$r}行目: 担当部門が空です。");
            }

            $key = ClientNameNormalizer::normalize($name);
            $dupKey = $key . '|' . $year;
            if (isset($seen[$dupKey])) {
                $this->addIssue($errors, "{$r}行目: 同じ得意先・年度が重複しています（{$seen[$dupKey]}行目と同じ）。");
                continue;
            }
            $seen[$dupKey] = $r;

            $months = [];
            $rowAmount = 0.0;
            $rowCount = 0;
            for ($m = 1; $m <= 12; $m++) {
                $amountCol = 4 + 2 * ($m - 1);
                [$amount, $amountErr] = $this->numberWithError($ws, $amountCol, $r);
                [$count, $countErr] = $this->numberWithError($ws, $amountCol + 1, $r);
                foreach ([$amountErr, $countErr] as $err) {
                    if ($err !== null) {
                        $this->addIssue($errors, "{$r}行目 {$m}月: {$err}");
                    }
                }
                if ($amount === null && $count === null) {
                    continue; // 未登録の月
                }
                $orders = (int) round($count ?? 0);
                if ($count !== null && abs($count - $orders) > 0.0001) {
                    $this->addIssue($warnings, "{$r}行目 {$m}月: 受注件数が整数ではありません（{$count}→{$orders}に丸めます）。");
                }
                if ($orders < 0) {
                    $this->addIssue($errors, "{$r}行目 {$m}月: 受注件数がマイナスです。");
                }
                $months[$m] = ['amount' => (float) ($amount ?? 0), 'orders' => $orders];
                $rowAmount += $amount ?? 0;
                $rowCount += $orders;
            }

            // 合計列と月合計の突き合わせ（警告）
            $totalAmount = $this->number($ws, self::TOTAL_AMOUNT_COL, $r);
            if ($totalAmount !== null && abs($totalAmount - $rowAmount) > 1) {
                $this->addIssue($warnings, "{$r}行目: 合計列の売上金額（{$totalAmount}）が、月ごとの合計（{$rowAmount}）と一致しません。");
            }
            $totalCount = $this->number($ws, self::TOTAL_COUNT_COL, $r);
            if ($totalCount !== null && abs($totalCount - $rowCount) > 0.5) {
                $this->addIssue($warnings, "{$r}行目: 合計列の受注件数（{$totalCount}）が、月ごとの合計（{$rowCount}）と一致しません。");
            }

            $rows[] = [
                'row_number' => $r,
                'client_name' => $name,
                'client_key' => $key,
                'year' => $year,
                'department_label' => $department,
                'months' => $months,
            ];
            $sumAmount += $rowAmount;
            $sumCount += $rowCount;
        }

        if ($grand !== null) {
            if ($grand[0] !== null && abs($grand[0] - $sumAmount) > 1) {
                $warnings[] = "総計行の売上金額（{$grand[0]}）が、各行の合計（{$sumAmount}）と一致しません。";
            }
            if ($grand[1] !== null && abs($grand[1] - $sumCount) > 0.5) {
                $warnings[] = "総計行の受注件数（{$grand[1]}）が、各行の合計（{$sumCount}）と一致しません。";
            }
        } else {
            $warnings[] = '総計行が見つかりませんでした（検算ができません）。';
        }

        $years = array_column($rows, 'year');
        if ($rows === [] && $errors === []) {
            $errors[] = '取り込めるデータ行がありません（4行目以降に、得意先ごとの行が必要です）。';
        }

        return [
            'format' => self::FORMAT,
            'sheet' => $ws->getTitle(),
            'rows' => $rows,
            'errors' => $errors,
            'warnings' => $warnings,
            'year_min' => $years ? min($years) : null,
            'year_max' => $years ? max($years) : null,
            'row_count' => count($rows),
        ];
    }

    private function addIssue(array &$list, string $message): void
    {
        if (count($list) < self::MAX_ISSUES) {
            $list[] = $message;
        } elseif (count($list) === self::MAX_ISSUES) {
            $list[] = '…ほかにも同様の指摘があります（' . self::MAX_ISSUES . '件まで表示）。';
        }
    }

    private function text(Worksheet $ws, int $col, int $row): string
    {
        $value = $ws->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();

        return is_scalar($value) ? trim(str_replace(["\r", "\n"], ' ', (string) $value)) : '';
    }

    /** 見出し用: 全角半角・空白をそろえる */
    private function normalizeLabel(mixed $value): string
    {
        return is_scalar($value) ? str_replace([' ', '　'], '', mb_convert_kana((string) $value, 'KVas')) : '';
    }

    /** 「2024年度」「2024年」「2024」→ 2024（全角数字も可）。読み取れなければ null */
    private function parseYear(string $text): ?int
    {
        $normalized = mb_convert_kana($text, 'n');
        if (! preg_match('/^(\d{4})(年度?)?$/u', trim($normalized), $m)) {
            return null;
        }
        $year = (int) $m[1];

        return ($year >= 1990 && $year <= 2100) ? $year : null;
    }

    private function number(Worksheet $ws, int $col, int $row): ?float
    {
        return $this->numberWithError($ws, $col, $row)[0];
    }

    /**
     * セルの数値。空欄は null。数値でない文字列は null とエラー文言。
     * 「1,234」「¥1,234」「1,234円」「△100」「▲100」（マイナス）も受け付ける。
     *
     * @return array{0: ?float, 1: ?string}
     */
    private function numberWithError(Worksheet $ws, int $col, int $row): array
    {
        $value = $ws->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getCalculatedValue();
        if ($value === null || $value === '') {
            return [null, null];
        }
        if (is_int($value) || is_float($value)) {
            return [(float) $value, null];
        }
        if (! is_string($value)) {
            return [null, '数値ではない値です。'];
        }

        $s = trim(mb_convert_kana($value, 'nas'));
        if ($s === '') {
            return [null, null];
        }
        $negative = false;
        if (preg_match('/^[△▲]/u', $s)) {
            $negative = true;
            $s = mb_substr($s, 1);
        }
        $s = str_replace([',', '¥', '円', ' '], '', $s);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return [null, "数値として読み取れません（「{$value}」）。"];
        }
        $n = (float) $s;

        return [$negative ? -abs($n) : $n, null];
    }
}
