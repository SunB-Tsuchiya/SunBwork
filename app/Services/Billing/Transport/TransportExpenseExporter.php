<?php

namespace App\Services\Billing\Transport;

use App\Models\TransportExpense;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 交通費（月単位）の出力。Excel は原本（SB交通費金銭請求伝票_23新.xlsx）から作った雛形
 * resources/billing/transport_expense_template.xlsx に流し込み、PDF は Blade（原本の寸法で配置）。
 * 雛形は原本から「旧様式のシート・印影の画像・サンプル値」を除いたもの（○ の図形は読み込めないので、出力時に描き直す）。
 * 明細は 1 枚 30 行（7〜36 行目）。超える分は 2 枚目以降に自動で続ける。
 */
class TransportExpenseExporter
{
    private const TEMPLATE = 'resources/billing/transport_expense_template.xlsx';
    private const DATA_START = 7;    // 明細の先頭行
    private const TOTAL_ROW = 37;    // 計の行

    /** 部門コード → [番号セル, 名称セル]（雛形） */
    private const DEPT_CELLS = [
        0  => ['D4', 'E4'],
        10 => ['H4', 'I4'],
        20 => ['D5', 'E5'],
        30 => ['H5', 'I5'],
        50 => ['L5', 'M5'],
    ];

    /** 表示用に、発生日昇順・同日は入力順で並べた明細 */
    public static function sortedItems(TransportExpense $expense)
    {
        return $expense->items
            ->sortBy(fn ($it) => [$it->occurrence_date?->toDateString() ?? '9999-99-99', $it->sort_order])
            ->values();
    }

    /** 明細を 1 枚ぶんずつに分ける（キーは 0 から振り直す。明細が無くても 1 枚は返す） */
    public static function paginate($items, int $perPage)
    {
        $pages = $items->chunk($perPage)->map(fn ($c) => $c->values())->values();
        return $pages->isEmpty() ? collect([collect()]) : $pages;
    }

    /**
     * 部門欄の表示内容。
     *  - numbered=true: サン・ブレーン様式（数字コードつき5択）。selectedCode は強調する数字（対応が無ければ null）
     *  - numbered=false: 会社に登録された部署名をそのまま表示
     */
    public static function departmentInfo(TransportExpense $expense): array
    {
        $expense->loadMissing(['company', 'user', 'chargeDepartment']);
        $company = $expense->company ?? $expense->user?->company;
        $name = $expense->chargeDepartment?->name;   // NULL = 共通
        $numbered = TransportCompany::usesNumberedForm($company);

        return [
            'numbered' => $numbered,
            'name' => $name ?? '共通',
            'selectedCode' => $name === null ? 0 : TransportExpense::codeForDepartmentName($name),
        ];
    }

    /** 所属欄に出す会社名（「株式会社」は省く。例: 株式会社サン・ブレーン → サン・ブレーン） */
    public static function companyName(TransportExpense $expense): string
    {
        $expense->loadMissing(['company', 'user.company']);
        $name = $expense->company?->name ?? $expense->user?->company?->name ?? '';
        return trim(preg_replace('/^(株式会社|有限会社)\s*|\s*(株式会社|有限会社)$/u', '', $name));
    }

    /**
     * 文字がセルに収まるように、文字の大きさ（pt）を 0.5pt 刻みで下げる。下限を下回るなら下限（それでも入らなければ、はみ出す分は切れる）。
     * 幅の見積もり: 全角は 1em、半角の英数字は約 0.55em（NotoSansJP は等幅ではないため）。
     *
     * @param float $cellWidth セルの幅（pt。余白を除く前の幅）
     */
    public static function fitFontSize(string $text, float $cellWidth, float $base = 10.0, float $min = 5.5, float $padding = 4.0): float
    {
        $em = 0.0;
        foreach (mb_str_split($text) as $ch) {
            $o = mb_ord($ch);
            $em += match (true) {
                $o >= 0xFF61 && $o <= 0xFF9F => 0.5,                       // 半角カナ
                $o >= 0x2E80 || ($o >= 0xFF00 && $o <= 0xFFEF) => 1.0,     // 全角（漢字・かな・全角記号）
                default => 0.55,
            };
        }
        if ($em <= 0) {
            return $base;
        }
        $fit = floor(($cellWidth - $padding) / $em * 2) / 2;
        return max($min, min($base, $fit));
    }

    /** 右上に入れる、出どころの表示（PDF・Excel 共通）。申請 No. は、ウェブの詳細画面と紙を結びつける */
    public static function originLabel(TransportExpense $expense): string
    {
        return 'from SunBWork　申請No.' . $expense->id;
    }

    /** 用件ラベル（IC のとき「(IC)」を添える。Excel・PDF で統一） */
    public static function purposeLabel($item): string
    {
        return $item->purpose_label . ($item->fare_type === 'ic' ? '(IC)' : '');
    }

    public static function title(TransportExpense $expense): string
    {
        [$y, $m] = array_map('intval', explode('-', $expense->billing_month));
        return '交通費金銭請求伝票（令和' . ($y - 2018) . "年{$m}月分）";
    }

    public static function filename(TransportExpense $expense, string $ext): string
    {
        return '交通費請求_' . $expense->user->name . '_' . str_replace('-', '', $expense->billing_month) . '.' . $ext;
    }

    public function pdf(TransportExpense $expense)
    {
        $expense->loadMissing(['user.department', 'items']);
        $items = self::sortedItems($expense);

        return Pdf::loadView('billing.transport.expense_pdf', [
            'expense' => $expense,
            'title'   => self::title($expense),
            'pages'   => self::paginate($items, TransportExpense::ROWS_PER_PAGE),
            'rowsPerPage' => TransportExpense::ROWS_PER_PAGE,
            'departmentCodes' => TransportExpense::DEPARTMENT_CODES,
            'dept' => self::departmentInfo($expense),
            'origin' => self::originLabel($expense),
            'companyName' => self::companyName($expense),
        ])->setPaper('a4', 'portrait')
          // 日本語フォント（NotoSansJP）をサブセット化すると、埋め込みが壊れて Chrome 等で日本語が表示されない。全体を埋め込む
          ->setOption(['enable_font_subsetting' => false]);
    }

    public function excelContent(TransportExpense $expense): string
    {
        $expense->loadMissing(['user.department', 'items']);
        $items = self::sortedItems($expense);
        $pages = self::paginate($items, TransportExpense::ROWS_PER_PAGE);

        $spreadsheet = IOFactory::createReader('Xlsx')->load(base_path(self::TEMPLATE));
        $base = $spreadsheet->getActiveSheet();

        // 2 枚目以降は雛形シートを複製
        $sheets = [$base];
        for ($p = 1; $p < $pages->count(); $p++) {
            $clone = clone $base;
            $clone->setTitle('tmp' . $p);   // 重複タイトルを避ける（fillSheet で付け直す）
            $spreadsheet->addSheet($clone);
            $sheets[] = $clone;
        }

        $grand = (int) $items->sum('amount');
        $last  = $pages->count() - 1;
        foreach ($pages as $p => $chunk) {
            $this->fillSheet($sheets[$p], $expense, $chunk, $p, $last, $grand);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    private function fillSheet(Worksheet $sheet, TransportExpense $expense, $chunk, int $page, int $last, int $grand): void
    {
        $set = function (string $cell, $value) use ($sheet): void {
            $sheet->setCellValue($cell, $value);
        };

        $set('A1', '交通費金銭請求伝票' . ($last > 0 ? '　' . ($page + 1) . '/' . ($last + 1) : ''));
        $sheet->setTitle(($page + 1) . '枚目');

        // 請求日（= 申請日。申請時に伝票の billing_date を更新している）・対象月・所属（会社名）・氏名
        $d = $expense->billing_date;
        $set('D3', ExcelDate::PHPToExcel($d));   // 日付の値のまま（表示は雛形の書式「令和8年9月30日」）
        [$y, $m] = array_map('intval', explode('-', $expense->billing_month));
        $set('K3', '（令和' . ($y - 2018) . "年{$m}月分）");
        $set('U3', self::companyName($expense));
        $set('U4', $expense->user->name);

        // 部門欄: サン・ブレーン様式は該当コードを ○ で囲む。それ以外の会社は、数字コードの欄を消して登録部署名を ○ で囲む
        $dept = self::departmentInfo($expense);
        if ($dept['numbered']) {
            if ($dept['selectedCode'] !== null && isset(self::DEPT_CELLS[$dept['selectedCode']])) {
                $this->circle($sheet, self::DEPT_CELLS[$dept['selectedCode']][0], 23, 23, 3, 2);
            }
        } else {
            foreach (self::DEPT_CELLS as $cells) {
                foreach ($cells as $c) {
                    $sheet->setCellValue($c, null);
                }
            }
            $set('A4', '部門');
            $set('D4', $dept['name']);
            $this->circle($sheet, 'D4', max(40, mb_strlen($dept['name']) * 14 + 14), 23, 0, 2);
        }

        $pageSum = 0;
        foreach ($chunk->values() as $i => $item) {
            $row = self::DATA_START + $i;
            if ($item->occurrence_date) {
                $set("A{$row}", ExcelDate::PHPToExcel($item->occurrence_date));   // 表示は雛形の書式「9月1日」
            }
            $set("D{$row}", $item->destination ?? '');
            $set("J{$row}", self::purposeLabel($item));
            $set("O{$row}", $item->station_from ?? '');
            $set("T{$row}", $item->station_to ?? '');
            if ($item->amount > 0) {
                $set("X{$row}", $item->amount);   // 書式は雛形のまま
            }
            // 長い文字は、Excel の「縮小して全体を表示する」で収める（折り返しは使わない）
            foreach (['D', 'J', 'O', 'T', 'X'] as $col) {
                $al = $sheet->getStyle("{$col}{$row}")->getAlignment();
                $al->setShrinkToFit(true)->setWrapText(false);
            }
            $pageSum += (int) $item->amount;
        }

        // 計: 1 枚なら「計」、複数枚なら各枚「小計」・最終枚は全体の「合計」
        $t = self::TOTAL_ROW;
        $set("S{$t}", null);   // 計の行の中央の「－」は消す（明細の空行には残す）
        if ($last === 0) {
            $set("A{$t}", '計');
            $set("X{$t}", $pageSum);
        } elseif ($page < $last) {
            $set("A{$t}", '小計');
            $set("X{$t}", $pageSum);
        } else {
            $set("A{$t}", '合計（全' . ($last + 1) . '枚）');
            $set("X{$t}", $grand);
        }

        // モノクロ化
        $sheet->getStyle('A1:Z40')->getFont()->getColor()->setRGB('000000');

        // 右上: このサイトから申請したものだと、紙でわかるようにする（総務・経理は、確認・検算をウェブで行える）
        $sheet->mergeCells('S2:Z2');
        $sheet->setCellValue('S2', self::originLabel($expense));
        $sheet->getStyle('S2:Z2')->getFont()->setSize(8)->getColor()->setRGB('444444');
        $sheet->getStyle('S2:Z2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }

    /**
     * ○ を描く（原本は Excel の楕円図形だが、図形は読み書きできないため透過 PNG で再現する）。
     * GD が使えない環境では、代わりに該当セルを太字・下線にする。
     */
    private function circle(Worksheet $sheet, string $cell, int $w, int $h, int $offsetX, int $offsetY): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $sheet->getStyle($cell)->getFont()->setBold(true)->setUnderline(Font::UNDERLINE_SINGLE);
            return;
        }
        $k = 4;                                                    // 4 倍で描いて縮小（輪郭をなめらかに）
        $big = imagecreatetruecolor($w * $k, $h * $k);
        imagealphablending($big, false);
        imagesavealpha($big, true);
        $clear = imagecolorallocatealpha($big, 0, 0, 0, 127);
        imagefill($big, 0, 0, $clear);
        $black = imagecolorallocatealpha($big, 0, 0, 0, 0);
        $line = (int) round(1.6 * $k);                              // 線の太さ（原本の 1.5pt 相当）
        imagefilledellipse($big, intdiv($w * $k, 2), intdiv($h * $k, 2), $w * $k - 1, $h * $k - 1, $black);
        imagefilledellipse($big, intdiv($w * $k, 2), intdiv($h * $k, 2), $w * $k - 1 - 2 * $line, $h * $k - 1 - 2 * $line, $clear);

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagecopyresampled($img, $big, 0, 0, 0, 0, $w, $h, $w * $k, $h * $k);
        imagedestroy($big);

        $drawing = new MemoryDrawing();
        $drawing->setName('部門の囲み');
        $drawing->setImageResource($img);
        $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
        $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
        $drawing->setCoordinates($cell);
        $drawing->setOffsetX($offsetX);
        $drawing->setOffsetY($offsetY);
        $drawing->setWorksheet($sheet);
    }
}
