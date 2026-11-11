<?php

namespace App\Services\SalesAnalysis;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * 売上分析のExcel出力（PLAN 2.8/3.4）。画面で指定した条件（部署・年・得意先統合）を反映し、
 * 概要・月別推移・得意先別・分類別・項目別・該当明細を別シートで出力する。
 * 初期版は「年次分析」画面（PLAN 2.8の必須シート構成と一致）を対象とする。
 *
 * formula injection対策: 得意先名・品名などの利用者（経理側）由来の文字列は
 * setCellValueExplicit()でTYPE_STRINGとして明示的に書き込み、先頭が=+-@等の場合は
 * アポストロフィを付与する二重の対策でExcelに数式として解釈させない。
 */
class SalesExportService
{
    private ?int $companyId = null;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    /** 会社別データ分離（2026-09-05）。呼び出し側は先に必ずこれを呼ぶ（SalesQueryServiceと同じ設計） */
    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->queryService->forCompany($companyId);

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExportService: forCompany()が呼ばれる前にworkbookメソッドが実行されました。');
        }

        return $this->companyId;
    }

    public function annualAnalysisWorkbook(string $departmentKey, int $year, bool $consolidateClients): Spreadsheet
    {
        $summary = $this->queryService->annualSummary($departmentKey, $year, $consolidateClients);
        $departmentLabel = $departmentKey === 'all'
            ? '全部署合計'
            : (SalesDepartments::labelForKey($this->requireCompanyId(), $departmentKey) ?? $departmentKey);
        $supportsChannels = (bool) ($summary['supports_order_channels'] ?? false);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->buildSummarySheet($spreadsheet, $summary, $departmentLabel, $consolidateClients, $supportsChannels);
        $this->buildMonthlySheet($spreadsheet, $summary, $supportsChannels);
        $this->buildClientSheet($spreadsheet, $summary, $supportsChannels);
        $this->buildBreakdownSheet($spreadsheet, '分類別', $summary['categories'], $supportsChannels);
        $this->buildBreakdownSheet($spreadsheet, '項目別', $summary['items'], $supportsChannels);
        $this->buildOrdersSheet($spreadsheet, $departmentKey, $year, max((int) $summary['last_registered_month'], 0), $supportsChannels);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * 期別分析（4月始まり）用Excel出力。シート構成はannualAnalysisWorkbook()と同じだが、
     * 「概要」「月別推移」「該当明細」は期別専用のキー名（fiscal_year等）を扱うため別メソッドにする。
     * 「得意先別」「分類別」「項目別」はtop_clients/categories/itemsの形が同一のため共用する。
     */
    public function fiscalYearAnalysisWorkbook(string $departmentKey, int $fiscalYear, bool $consolidateClients): Spreadsheet
    {
        $summary = $this->queryService->fiscalYearSummary($departmentKey, $fiscalYear, $consolidateClients);
        $departmentLabel = $departmentKey === 'all'
            ? '全部署合計'
            : (SalesDepartments::labelForKey($this->requireCompanyId(), $departmentKey) ?? $departmentKey);
        $supportsChannels = (bool) ($summary['supports_order_channels'] ?? false);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->buildFiscalSummarySheet($spreadsheet, $summary, $departmentLabel, $consolidateClients, $supportsChannels);
        $this->buildFiscalMonthlySheet($spreadsheet, $summary, $supportsChannels);
        $this->buildClientSheet($spreadsheet, $summary, $supportsChannels);
        $this->buildBreakdownSheet($spreadsheet, '分類別', $summary['categories'], $supportsChannels);
        $this->buildBreakdownSheet($spreadsheet, '項目別', $summary['items'], $supportsChannels);
        $this->buildFiscalOrdersSheet($spreadsheet, $departmentKey, $fiscalYear, max((int) $summary['last_registered_month'], 0), $supportsChannels);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildFiscalSummarySheet(Spreadsheet $spreadsheet, array $summary, string $departmentLabel, bool $consolidateClients, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('概要');

        $comparisonRange = $summary['comparison_month_range'];
        $rangeLabel = $comparisonRange[0] === $comparisonRange[1]
            ? "期{$comparisonRange[0]}ヶ月目のみ"
            : "期{$comparisonRange[0]}〜{$comparisonRange[1]}ヶ月目";

        $rows = [
            ['部署', $departmentLabel],
            ['期', "{$summary['fiscal_year']}年度（{$summary['period_start']['year']}年{$summary['period_start']['month']}月〜{$summary['period_end']['year']}年{$summary['period_end']['month']}月）"],
            ['比較モード', $summary['comparison_mode'] === 'full' ? '通期比較（12ヶ月）' : "進行中期（{$rangeLabel}の同期間比較）"],
            ['期間売上', $summary['kpi']['period_amount']],
            ['前期同期売上', $summary['kpi']['prior_period_amount']],
            ['差額', $summary['kpi']['amount_diff']],
            ['増減率(%)', $summary['kpi']['amount_rate']],
            ['受注件数', $summary['kpi']['order_count']],
            ['前期同期受注件数', $summary['kpi']['prior_order_count']],
            ['1案件平均', $summary['kpi']['avg_order_amount']],
            ['未配賦額', $summary['kpi']['unallocated_amount']],
            ['参考: 前期通期実績', $summary['kpi']['full_prior_year_amount']],
            ...$this->channelSummaryRows($summary['kpi'], $supportsChannels),
            ['得意先統合', $consolidateClients ? 'ON' : 'OFF'],
            ['出力日時', now()->format('Y-m-d H:i')],
        ];

        foreach ($rows as $i => [$label, $value]) {
            $r = $i + 1;
            $this->setUserString($sheet, "A{$r}", $label);
            if (is_numeric($value)) {
                $this->setAmount($sheet, "B{$r}", $value);
            } else {
                $this->setUserString($sheet, "B{$r}", $value);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(32);
        $sheet->getStyle('A1:A' . count($rows))->getFont()->setBold(true);
    }

    private function buildFiscalMonthlySheet(Spreadsheet $spreadsheet, array $summary, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('月別推移');

        $headers = ['年月', '売上', '前期同月', '差額', '増減率(%)', '受注件数', '1案件平均', '状態'];
        if ($supportsChannels) {
            $headers = [...$headers, 'サンエー印刷経由', '独自受注', '独自比率(%)'];
        }
        $this->writeHeaderRow($sheet, $headers);

        $row = 2;
        foreach ($summary['monthly'] as $m) {
            $sheet->setCellValue("A{$row}", "{$m['calendar_year']}/{$m['calendar_month']}");
            $this->setAmount($sheet, "B{$row}", $m['amount']);
            $this->setAmount($sheet, "C{$row}", $m['prior_year_amount']);
            $this->setAmount($sheet, "D{$row}", $m['diff']);
            $this->setAmount($sheet, "E{$row}", $m['rate']);
            $sheet->setCellValue("F{$row}", $m['order_count']);
            $this->setAmount($sheet, "G{$row}", $m['avg_order_amount']);
            $this->setUserString($sheet, "H{$row}", $this->monthStateLabel($m));
            if ($supportsChannels) {
                $this->setAmount($sheet, "I{$row}", $m['standard_amount'] ?? null);
                $this->setAmount($sheet, "J{$row}", $m['direct_amount'] ?? null);
                $this->setAmount($sheet, "K{$row}", $m['direct_share'] ?? null);
            }
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', $supportsChannels ? 'K' : 'H');
    }

    private function buildFiscalOrdersSheet(Spreadsheet $spreadsheet, string $departmentKey, int $fiscalYear, int $lastRegisteredMonth, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('該当明細');

        $headers = ['年月', '受注No', '得意先', '品名', '金額', 'SB下版日'];
        if ($supportsChannels) {
            $headers[] = '受注経路';
        }
        $this->writeHeaderRow($sheet, $headers);

        $orders = $lastRegisteredMonth > 0
            ? $this->queryService->fiscalYearOrders($departmentKey, $fiscalYear, $lastRegisteredMonth)
            : [];

        $row = 2;
        foreach ($orders as $o) {
            $sheet->setCellValue("A{$row}", "{$o['sales_year']}/{$o['sales_month']}");
            $this->setUserString($sheet, "B{$row}", $o['order_number']);
            $this->setUserString($sheet, "C{$row}", $o['client_name']);
            $this->setUserString($sheet, "D{$row}", $o['product_name']);
            $this->setAmount($sheet, "E{$row}", $o['order_amount']);
            $this->setUserString($sheet, "F{$row}", $o['plate_date']);
            if ($supportsChannels) {
                $this->setUserString($sheet, "G{$row}", $o['order_channel_label'] ?? '');
            }
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', $supportsChannels ? 'G' : 'F');
    }

    private function buildSummarySheet(Spreadsheet $spreadsheet, array $summary, string $departmentLabel, bool $consolidateClients, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('概要');

        $comparisonRange = $summary['comparison_month_range'];
        $rangeLabel = $comparisonRange[0] === $comparisonRange[1]
            ? "{$comparisonRange[0]}月のみ"
            : "{$comparisonRange[0]}〜{$comparisonRange[1]}月";

        $rows = [
            ['部署', $departmentLabel],
            ['年', $summary['year']],
            ['比較モード', $summary['comparison_mode'] === 'full' ? '通期比較（12ヶ月）' : "進行中年（{$rangeLabel}の同期間比較）"],
            ['期間売上', $summary['kpi']['period_amount']],
            ['前年同期売上', $summary['kpi']['prior_period_amount']],
            ['差額', $summary['kpi']['amount_diff']],
            ['増減率(%)', $summary['kpi']['amount_rate']],
            ['受注件数', $summary['kpi']['order_count']],
            ['前年同期受注件数', $summary['kpi']['prior_order_count']],
            ['1案件平均', $summary['kpi']['avg_order_amount']],
            ['未配賦額', $summary['kpi']['unallocated_amount']],
            ['参考: 前年通期実績', $summary['kpi']['full_prior_year_amount']],
            ...$this->channelSummaryRows($summary['kpi'], $supportsChannels),
            ['得意先統合', $consolidateClients ? 'ON' : 'OFF'],
            ['出力日時', now()->format('Y-m-d H:i')],
        ];

        foreach ($rows as $i => [$label, $value]) {
            $r = $i + 1;
            $this->setUserString($sheet, "A{$r}", $label);
            if (is_numeric($value)) {
                $this->setAmount($sheet, "B{$r}", $value);
            } else {
                $this->setUserString($sheet, "B{$r}", $value);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getStyle('A1:A' . count($rows))->getFont()->setBold(true);
    }

    private function buildMonthlySheet(Spreadsheet $spreadsheet, array $summary, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('月別推移');

        $headers = ['月', '売上', '前年同月', '差額', '増減率(%)', '受注件数', '1案件平均', '状態'];
        if ($supportsChannels) {
            $headers = [...$headers, 'サンエー印刷経由', '独自受注', '独自比率(%)'];
        }
        $this->writeHeaderRow($sheet, $headers);

        $row = 2;
        foreach ($summary['monthly'] as $m) {
            $sheet->setCellValue("A{$row}", $m['month'] . '月');
            $this->setAmount($sheet, "B{$row}", $m['amount']);
            $this->setAmount($sheet, "C{$row}", $m['prior_year_amount']);
            $this->setAmount($sheet, "D{$row}", $m['diff']);
            $this->setAmount($sheet, "E{$row}", $m['rate']);
            $sheet->setCellValue("F{$row}", $m['order_count']);
            $this->setAmount($sheet, "G{$row}", $m['avg_order_amount']);
            $this->setUserString($sheet, "H{$row}", $this->monthStateLabel($m));
            if ($supportsChannels) {
                $this->setAmount($sheet, "I{$row}", $m['standard_amount'] ?? null);
                $this->setAmount($sheet, "J{$row}", $m['direct_amount'] ?? null);
                $this->setAmount($sheet, "K{$row}", $m['direct_share'] ?? null);
            }
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', $supportsChannels ? 'K' : 'H');
    }

    private function monthStateLabel(array $m): string
    {
        return match (true) {
            $m['state'] === 'future' => '未到来',
            $m['state'] === 'no_data' => '未登録',
            ($m['registration'] ?? null) === 'partial' => '一部未登録（片方の経路のみ）',
            $m['has_issue'] => '登録済み（未配賦額あり）',
            $m['needs_review'] => '登録済み（複数回取込あり）',
            default => '登録済み',
        };
    }

    /** サン・ブレーンだけに追加する概要シートの経路内訳行 */
    private function channelSummaryRows(array $kpi, bool $supportsChannels): array
    {
        if (! $supportsChannels) {
            return [];
        }

        return [
            ['サンエー印刷経由', $kpi['standard_amount']],
            ['独自受注', $kpi['direct_amount']],
            ['独自受注比率(%)', $kpi['direct_share']],
        ];
    }

    private function buildClientSheet(Spreadsheet $spreadsheet, array $summary, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('得意先別');

        $headers = ['順位', '得意先', '金額', '構成比(%)', '前年同期金額', '差額', '増減率(%)'];
        if ($supportsChannels) {
            $headers = [...$headers, 'サンエー印刷経由', '独自受注', '独自比率(%)'];
        }
        $this->writeHeaderRow($sheet, $headers);

        $row = 2;
        foreach ($summary['top_clients'] as $i => $c) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $this->setUserString($sheet, "B{$row}", $c['client_name']);
            $this->setAmount($sheet, "C{$row}", $c['amount']);
            $this->setAmount($sheet, "D{$row}", $c['share_pct']);
            $this->setAmount($sheet, "E{$row}", $c['prior_year_amount']);
            $this->setAmount($sheet, "F{$row}", $c['diff']);
            $this->setAmount($sheet, "G{$row}", $c['rate']);
            if ($supportsChannels) {
                $this->setAmount($sheet, "H{$row}", $c['standard_amount'] ?? null);
                $this->setAmount($sheet, "I{$row}", $c['direct_amount'] ?? null);
                $this->setAmount($sheet, "J{$row}", $c['direct_share'] ?? null);
            }
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', $supportsChannels ? 'J' : 'G');
    }

    private function buildBreakdownSheet(Spreadsheet $spreadsheet, string $title, array $rows, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        $headers = [$title === '分類別' ? '分類' : '項目', '金額', '構成比(%)'];
        if ($supportsChannels) {
            $headers = [...$headers, 'サンエー印刷経由', '独自受注', '独自比率(%)'];
        }
        $this->writeHeaderRow($sheet, $headers);

        $row = 2;
        foreach ($rows as $r) {
            $this->setUserString($sheet, "A{$row}", $r['label']);
            $this->setAmount($sheet, "B{$row}", $r['amount']);
            $this->setAmount($sheet, "C{$row}", $r['share']);
            if ($supportsChannels) {
                $this->setAmount($sheet, "D{$row}", $r['standard_amount'] ?? null);
                $this->setAmount($sheet, "E{$row}", $r['direct_amount'] ?? null);
                $this->setAmount($sheet, "F{$row}", $r['direct_share'] ?? null);
            }
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', $supportsChannels ? 'F' : 'C');
    }

    private function buildOrdersSheet(Spreadsheet $spreadsheet, string $departmentKey, int $year, int $monthsRegistered, bool $supportsChannels): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('該当明細');

        $headers = ['月', '受注No', '得意先', '品名', '金額', 'SB下版日'];
        if ($supportsChannels) {
            $headers[] = '受注経路';
        }
        $this->writeHeaderRow($sheet, $headers);

        $orders = $monthsRegistered > 0
            ? $this->queryService->periodOrders($departmentKey, $year, 1, $monthsRegistered)
            : [];

        $row = 2;
        foreach ($orders as $o) {
            $sheet->setCellValue("A{$row}", $o['sales_month'] . '月');
            $this->setUserString($sheet, "B{$row}", $o['order_number']);
            $this->setUserString($sheet, "C{$row}", $o['client_name']);
            $this->setUserString($sheet, "D{$row}", $o['product_name']);
            $this->setAmount($sheet, "E{$row}", $o['order_amount']);
            $this->setUserString($sheet, "F{$row}", $o['plate_date']);
            if ($supportsChannels) {
                $this->setUserString($sheet, "G{$row}", $o['order_channel_label'] ?? '');
            }
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', $supportsChannels ? 'G' : 'F');
    }

    /**
     * 注目ポイントのExcel出力（SBATTN_PLAN1.md）。画面と同じ集計（SalesAttentionService）の結果を、シートごとに出力する。
     * 新規・休眠は、画面の上位件数ではなく全件を出す。
     *
     * @return array{spreadsheet: Spreadsheet, rows: int} rows は一覧の行数の合計（監査ログ用）
     */
    public function attentionWorkbook(string $departmentKey, int $year, bool $consolidateClients, int $dormantMonths): array
    {
        $summary = app(SalesAttentionService::class)->forCompany($this->requireCompanyId())
            ->summary($departmentKey, $year, $consolidateClients, $dormantMonths, null);
        $departmentLabel = $departmentKey === 'all'
            ? '全部署合計'
            : (SalesDepartments::labelForKey($this->requireCompanyId(), $departmentKey) ?? $departmentKey);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $rowCount = 0;

        // 条件
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('条件');
        $this->writeHeaderRow($sheet, ['項目', '内容']);
        $dormant = $summary['dormant'] ?? null;
        $conditions = [
            ['部署', $departmentLabel],
            ['年', "{$year}年"],
            ['得意先統合', $consolidateClients ? 'する' : 'しない'],
            ['集計期間', ($summary['has_data'] ?? false) ? "{$year}年1〜" . $summary['last_month'] . '月（登録済みの月）' : '登録なし'],
            ['前年との比較', ($summary['comparison_available'] ?? false) ? ($year - 1) . '年の同じ月と比べています' : '前年のデータがないため、増加・減少・新規は出していません'],
            ['休眠の期間', "{$dormantMonths}か月"],
            ['休眠の判定', ($dormant['available'] ?? false)
                ? "基準月 {$dormant['latest_label']}。{$dormant['silent_label']} に売上がなく、その前の12か月（{$dormant['prior_label']}）には売上があった得意先"
                : ($dormant['message'] ?? '')],
        ];
        $r = 2;
        foreach ($conditions as [$k, $v]) {
            $this->setUserString($sheet, "A{$r}", $k);
            $this->setUserString($sheet, "B{$r}", $v);
            $r++;
        }
        $this->autoSizeColumns($sheet, 'A', 'B');

        $diffHeaders = ['得意先', '金額', '前年同期金額', '差額', '増減率(%)'];
        foreach ([
            ['大きく増えた得意先', $summary['increase']['rows'] ?? []],
            ['大きく減った得意先', $summary['decrease']['rows'] ?? []],
        ] as [$title, $rows]) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $this->writeHeaderRow($sheet, $diffHeaders);
            $r = 2;
            foreach ($rows as $row) {
                $this->setUserString($sheet, "A{$r}", $row['label']);
                $this->setAmount($sheet, "B{$r}", $row['amount']);
                $this->setAmount($sheet, "C{$r}", $row['prior_amount']);
                $this->setAmount($sheet, "D{$r}", $row['diff']);
                $this->setAmount($sheet, "E{$r}", $row['rate']);
                $r++;
                $rowCount++;
            }
            $this->autoSizeColumns($sheet, 'A', 'E');
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('新規の得意先');
        $this->writeHeaderRow($sheet, ['得意先', '今年の金額']);
        $r = 2;
        foreach ($summary['new']['rows'] ?? [] as $row) {
            $this->setUserString($sheet, "A{$r}", $row['label']);
            $this->setAmount($sheet, "B{$r}", $row['amount']);
            $r++;
            $rowCount++;
        }
        $this->autoSizeColumns($sheet, 'A', 'B');

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('休眠の得意先');
        $this->writeHeaderRow($sheet, ['得意先', '前の12か月の金額', '最後に売上があった月', '売上のない期間(か月)']);
        $r = 2;
        foreach ($dormant['rows'] ?? [] as $row) {
            $this->setUserString($sheet, "A{$r}", $row['label']);
            $this->setAmount($sheet, "B{$r}", $row['prior_amount']);
            $this->setUserString($sheet, "C{$r}", $row['last_label']);
            $this->setAmount($sheet, "D{$r}", $row['silent_months']);
            $r++;
            $rowCount++;
        }
        $this->autoSizeColumns($sheet, 'A', 'D');

        $spreadsheet->setActiveSheetIndex(0);

        return ['spreadsheet' => $spreadsheet, 'rows' => $rowCount];
    }

    private function writeHeaderRow(Worksheet $sheet, array $headers): void
    {
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}1", $h);
            $col++;
        }
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');
    }

    private function autoSizeColumns(Worksheet $sheet, string $fromCol, string $toCol): void
    {
        for ($c = $fromCol; $c !== $this->nextColumn($toCol); $c = $this->nextColumn($c)) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function nextColumn(string $col): string
    {
        return chr(ord($col) + 1);
    }

    /**
     * 数値セル（システム計算値）。nullは"—"表示にする（0円と誤表示しない）。
     */
    private function setAmount(Worksheet $sheet, string $coordinate, $value): void
    {
        if ($value === null) {
            $sheet->setCellValue($coordinate, '—');

            return;
        }

        $sheet->setCellValue($coordinate, (float) $value);
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0.##');
    }

    /**
     * 利用者（経理側）由来の文字列セル。formula injection対策として、
     * TYPE_STRINGで明示的に文字列型として書き込み、先頭が=+-@等の場合はアポストロフィを付与する。
     */
    private function setUserString(Worksheet $sheet, string $coordinate, $value): void
    {
        $text = $value === null ? '' : (string) $value;

        if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text)) {
            $text = "'" . $text;
        }

        $sheet->setCellValueExplicit($coordinate, $text, DataType::TYPE_STRING);
    }
}
