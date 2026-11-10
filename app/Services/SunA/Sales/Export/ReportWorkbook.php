<?php

namespace App\Services\SunA\Sales\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 売上レポートのExcel出力用の、表を書く共通部品（SUNASALES_PLAN1.md §19）。
 *  - 各シートの先頭に、タイトル・条件・出力日時を書き、その下に表を書く
 *  - 文字は**必ず文字列として**書き込む（得意先名が「=」「+」などで始まっても、Excelの数式として実行されない）
 *  - 数値は数値のセル。列の種類: text / int（桁区切りの整数）/ pct（小数1桁の%）
 *  - ファイルはサーバーに保存しない（write() が出力先のストリームに直接書く）
 */
class ReportWorkbook
{
    private Spreadsheet $book;

    private ?Worksheet $ws = null;

    private int $row = 1;

    private int $sheetCount = 0;

    /** 書いた表の行数（監査ログに残す件数。内容は残さない） */
    private int $tableRows = 0;

    /** @param  array<int,string>  $conditions 「年: 2026年」のような条件の行 */
    public function __construct(private string $title, private array $conditions, private string $exportedAt)
    {
        $this->book = new Spreadsheet();
    }

    public function tableRows(): int
    {
        return $this->tableRows;
    }

    /** 新しいシートを作り、タイトル・条件・出力日時を書く。続けて table() / note() を呼ぶ */
    public function sheet(string $name): self
    {
        if ($this->sheetCount === 0) {
            $this->ws = $this->book->getActiveSheet();
        } else {
            $this->ws = $this->book->createSheet();
        }
        $this->sheetCount++;
        // シート名は31文字以内・一部の記号は使えない
        $this->ws->setTitle(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '_', $name), 0, 31));
        $this->row = 1;

        $this->text(1, 1, $this->title . '（' . $name . '）');
        $this->ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->row = 2;
        foreach ($this->conditions as $line) {
            $this->text($this->row, 1, $line);
            $this->row++;
        }
        $this->text($this->row, 1, '出力日時: ' . $this->exportedAt);
        $this->row += 2; // 1行あけて、表

        return $this;
    }

    /** 注記の行（表の上下に、集計の期間の説明などを書く） */
    public function note(string $text): self
    {
        $this->text($this->row, 1, $text);
        $this->ws->getStyle("A{$this->row}")->getFont()->setItalic(true)->getColor()->setRGB('666666');
        $this->row++;

        return $this;
    }

    /**
     * 表を書く。
     *
     * @param  array<int, array{0:string, 1?:string, 2?:float}>  $columns 見出し・種類（text/int/pct。既定は text）・列幅
     * @param  array<int, array<int, string|int|float|null>>  $rows 行ごとの値（null は空欄。未登録を 0 と区別するため）
     */
    public function table(array $columns, array $rows): self
    {
        $headerRow = $this->row;
        foreach ($columns as $i => $col) {
            $this->text($headerRow, $i + 1, $col[0]);
        }
        $lastCol = $this->colLetter(count($columns));
        $head = $this->ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}");
        $head->getFont()->setBold(true);
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $r = $headerRow;
        foreach ($rows as $values) {
            $r++;
            foreach ($columns as $i => $col) {
                $v = $values[$i] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                $type = $col[1] ?? 'text';
                if ($type === 'text' || ! is_numeric($v)) {
                    $this->text($r, $i + 1, (string) $v);
                } else {
                    $addr = $this->colLetter($i + 1) . $r;
                    $this->ws->setCellValue($addr, $type === 'int' ? (int) round((float) $v) : (float) $v);
                    $this->ws->getStyle($addr)->getNumberFormat()->setFormatCode($type === 'int' ? '#,##0' : '0.0"%"');
                }
            }
        }
        foreach ($columns as $i => $col) {
            $this->ws->getColumnDimensionByColumn($i + 1)->setWidth($col[2] ?? ((($col[1] ?? 'text') === 'text') ? 28 : 16));
        }
        $this->ws->freezePane('A' . ($headerRow + 1));
        $this->tableRows += count($rows);
        $this->row = $r + 2;

        return $this;
    }

    /** 出力先（ダウンロードのストリーム）へ書く。サーバーにファイルを残さない */
    public function write(): void
    {
        $this->book->setActiveSheetIndex(0);
        (new Xlsx($this->book))->save('php://output');
        $this->book->disconnectWorksheets();
    }

    /** 文字列として書き込む（数式として解釈されない） */
    private function text(int $row, int $col, string $value): void
    {
        $this->ws->setCellValueExplicit($this->colLetter($col) . $row, $value, DataType::TYPE_STRING);
    }

    private function colLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
