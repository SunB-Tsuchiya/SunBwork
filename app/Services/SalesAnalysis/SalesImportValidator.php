<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesOrder;
use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;

/**
 * SalesWorkbookReader が読み取った生データを検証し、受注単位に組み立てる。
 * DB確定保存（Phase 4）はここでは行わない。
 */
class SalesImportValidator
{
    private const REQUIRED_TEXT_FIELDS = [
        'order_number' => '受注No',
        'client_name' => '得意先名',
        'product_name' => '品名',
        'category' => '分類',
        'item_name' => '項目',
        'format_size' => '判型',
    ];

    private const REQUIRED_NUMBER_FIELDS = [
        'color_count' => '色数',
        'quantity' => '台数',
        'unit_price' => '単価',
        'line_amount' => '金額',
    ];

    public function __construct(private SalesWorkbookReader $reader)
    {
    }

    public function validate(
        string $filePath,
        string $departmentKey,
        string $sourceType,
        int $sourceYear,
        ?int $sourceMonth
    ): array {
        $errors = [];
        $warnings = [];

        try {
            $workbook = $this->reader->read($filePath);
        } catch (SalesWorkbookException $e) {
            return $this->invalidResult([$e->getMessage()]);
        }

        foreach ($workbook['header_mismatches'] as $mismatch) {
            $errors[] = "見出し不一致: {$mismatch['column']}列 期待値「{$mismatch['expected']}」実際「{$mismatch['actual']}」";
        }

        $errors = array_merge($errors, $this->validateDepartment($workbook, $departmentKey));
        $errors = array_merge($errors, $this->validateTitlePeriod($workbook, $sourceType, $sourceYear, $sourceMonth));

        if (! empty($errors)) {
            return $this->invalidResult($errors);
        }

        [$rowErrors, $ordersByNumber] = $this->groupRows($workbook['rows'], $sourceType, $sourceYear, $sourceMonth);
        $errors = array_merge($errors, $rowErrors);

        $orders = [];
        foreach ($ordersByNumber as $orderNumber => $rows) {
            $result = $this->buildOrder($orderNumber, $rows);
            if (! empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
                continue;
            }
            $orders[] = $result['order'];
        }

        if (empty($errors)) {
            $errors = array_merge($errors, $this->checkCrossMonthDuplicates($orders, $departmentKey));
        }

        $valid = empty($errors);

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'orders' => $valid ? $orders : [],
            'summary' => $valid ? $this->buildSummary($orders) : null,
            'department_key' => $departmentKey,
            'source_type' => $sourceType,
            'source_year' => $sourceYear,
            'source_month' => $sourceMonth,
        ];
    }

    private function invalidResult(array $errors): array
    {
        return [
            'valid' => false,
            'errors' => $errors,
            'warnings' => [],
            'orders' => [],
            'summary' => null,
            'department_key' => null,
            'source_type' => null,
            'source_year' => null,
            'source_month' => null,
        ];
    }

    private function validateDepartment(array $workbook, string $departmentKey): array
    {
        $errors = [];

        if (! SalesDepartments::isEnabled($departmentKey)) {
            $errors[] = '現在取込可能な部署は企画のみです。';

            return $errors;
        }

        $expectedLabel = SalesDepartments::labelFromKey($departmentKey);

        if ($workbook['department_label'] !== null && $workbook['department_label'] !== $expectedLabel) {
            $errors[] = "対象部署が一致しません（ファイル記載: {$workbook['department_label']} / 選択: {$expectedLabel}）。";
        }

        return $errors;
    }

    private function validateTitlePeriod(array $workbook, string $sourceType, int $sourceYear, ?int $sourceMonth): array
    {
        $errors = [];

        if ($workbook['title_year'] !== null && $workbook['title_year'] !== $sourceYear) {
            $errors[] = "タイトルの年（{$workbook['title_year']}）と指定年（{$sourceYear}）が一致しません。";
        }

        if ($sourceType === 'monthly' && $workbook['title_month'] !== null && $workbook['title_month'] !== $sourceMonth) {
            $errors[] = "タイトルの月（{$workbook['title_month']}）と指定月（{$sourceMonth}）が一致しません。";
        }

        return $errors;
    }

    /** @return array{0: array<int, string>, 1: array<string, array>} */
    private function groupRows(array $rows, string $sourceType, int $sourceYear, ?int $sourceMonth): array
    {
        $rowErrors = [];
        $ordersByNumber = [];

        foreach ($rows as $row) {
            $issues = $this->validateRow($row, $sourceType, $sourceYear, $sourceMonth);

            if (! empty($issues)) {
                foreach ($issues as $issue) {
                    $rowErrors[] = "{$row['source_row_number']}行目: {$issue}";
                }
                continue;
            }

            $ordersByNumber[$row['order_number']][] = $row;
        }

        return [$rowErrors, $ordersByNumber];
    }

    private function validateRow(array $row, string $sourceType, int $sourceYear, ?int $sourceMonth): array
    {
        $issues = [];

        foreach (self::REQUIRED_TEXT_FIELDS as $key => $label) {
            if ($row[$key] === null) {
                $issues[] = "{$label}が空です。";
            }
        }

        foreach (self::REQUIRED_NUMBER_FIELDS as $key => $label) {
            if ($row[$key] === null) {
                $issues[] = "{$label}が数値として読み取れません。";
            } elseif ($row[$key] < 0) {
                $issues[] = "{$label}が負数です。";
            }
        }

        // order_amount_component（受注金額）は同一受注内の途中行で0が許容されるため必須値のみ検証
        if ($row['order_amount_component'] === null) {
            $issues[] = '受注金額が数値として読み取れません。';
        } elseif ($row['order_amount_component'] < 0) {
            $issues[] = '受注金額が負数です。';
        }

        if ($row['plate_date'] === null) {
            $issues[] = 'SB下版日が日付として読み取れません。';

            return $issues;
        }

        $rowYear = (int) substr($row['plate_date'], 0, 4);
        $rowMonth = (int) substr($row['plate_date'], 5, 2);

        if ($sourceType === 'annual' && $rowYear !== $sourceYear) {
            $issues[] = "SB下版日の年（{$rowYear}）が対象年（{$sourceYear}）と一致しません。";
        }

        if ($sourceType === 'monthly' && ($rowYear !== $sourceYear || $rowMonth !== $sourceMonth)) {
            $issues[] = "SB下版日（{$row['plate_date']}）が対象年月（{$sourceYear}年{$sourceMonth}月）と一致しません。";
        }

        return $issues;
    }

    private function buildOrder(string $orderNumber, array $rows): array
    {
        $errors = [];
        $first = $rows[0];
        $clientName = $first['client_name'];
        $productName = $first['product_name'];
        $plateDate = $first['plate_date'];

        foreach ($rows as $row) {
            if ($row['client_name'] !== $clientName) {
                $errors[] = "受注No {$orderNumber}: 得意先名が行によって異なります。";
            }
            if ($row['product_name'] !== $productName) {
                $errors[] = "受注No {$orderNumber}: 品名が行によって異なります。";
            }
            if ($row['plate_date'] !== $plateDate) {
                $errors[] = "受注No {$orderNumber}: SB下版日が行によって異なります。";
            }
        }

        $lineAmountSum = array_sum(array_column($rows, 'line_amount'));
        $orderAmountSum = array_sum(array_column($rows, 'order_amount_component'));

        // 浮動小数点の丸め差異（1円未満）を許容する
        if (abs($lineAmountSum - $orderAmountSum) > 0.01) {
            $errors[] = "受注No {$orderNumber}: 金額合計（" . number_format($lineAmountSum) . '）と受注金額合計（' . number_format($orderAmountSum) . '）が一致しません。';
        }

        if (! empty($errors)) {
            return ['order' => null, 'errors' => $errors];
        }

        return [
            'order' => [
                'order_number' => $orderNumber,
                'client_name' => $clientName,
                'product_name' => $productName,
                'plate_date' => $plateDate,
                'sales_year' => (int) substr($plateDate, 0, 4),
                'sales_month' => (int) substr($plateDate, 5, 2),
                'order_amount' => $orderAmountSum,
                'details' => $rows,
            ],
            'errors' => [],
        ];
    }

    /** 現在有効な他月に同じ受注Noが存在しないかを確認する（sales DB接続） */
    private function checkCrossMonthDuplicates(array $orders, string $departmentKey): array
    {
        $orderNumbers = array_unique(array_column($orders, 'order_number'));
        if (empty($orderNumbers)) {
            return [];
        }

        $activeImportIds = SalesActiveMonth::where('department_key', $departmentKey)
            ->pluck('sales_import_id');

        if ($activeImportIds->isEmpty()) {
            return [];
        }

        $existing = SalesOrder::whereIn('sales_import_id', $activeImportIds)
            ->whereIn('order_number', $orderNumbers)
            ->get(['order_number', 'sales_year', 'sales_month'])
            ->keyBy('order_number');

        $errors = [];
        foreach ($orders as $order) {
            $existingOrder = $existing->get($order['order_number']);
            if (! $existingOrder) {
                continue;
            }
            if ((int) $existingOrder->sales_year === $order['sales_year'] && (int) $existingOrder->sales_month === $order['sales_month']) {
                continue; // 同月の再取込（正常なケース）
            }
            $errors[] = "受注No {$order['order_number']} は既に{$existingOrder->sales_year}年{$existingOrder->sales_month}月分として取込済みです。";
        }

        return $errors;
    }

    private function buildSummary(array $orders): array
    {
        return [
            'order_count' => count($orders),
            'detail_count' => array_sum(array_map(fn ($o) => count($o['details']), $orders)),
            'total_amount' => array_sum(array_column($orders, 'order_amount')),
        ];
    }
}
