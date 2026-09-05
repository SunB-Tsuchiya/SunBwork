<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesOrder;
use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;

/**
 * SalesWorkbookReader が読み取った生データを検証し、受注単位に組み立てる。
 * DB確定保存（Phase 4）はここでは行わない。
 *
 * 2026-09-03 Codexレビュー（SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md 6章）により、
 * 実帳票の欠損・記載ゆれを前提に error/warning を分離する設計へ変更した。
 * - blocking error: 受注No・SB下版日の欠損/解析不能、実在しない日付、
 *   同一受注内の得意先名/品名/下版日の矛盾、N列（受注金額）規則違反、負数（金額・単価・受注金額を除く）。
 * - warning（行は除外せず、対象列はNULL保存のまま取込を継続する）: 得意先名・品名の空欄、
 *   色数・台数の空欄、金額・単価の空欄/負数（事故損金等）、M/N不一致（未配賦額として提示）。
 *
 * 2026-09-04変更（ユーザー確認）: N列（受注金額）はこれまで「正の値のみ」を許容していたが、
 * 事故・刷り直し等で受注全体の合計が正当にマイナスになるケースがあり、これを理由に受注を
 * 丸ごと除外すべきではないため「0以外の値（正または負）」を許容するよう緩和した。
 * 途中行が0/NULLで最後の1行だけが0以外という構造の要件（規則違反はblocking error）は維持する。
 * - 項目・判型・分類の空欄は警告も出さない（デジタル案件では恒常的に空欄になるため）。
 *
 * 2026-09-03 実機検証（ユーザー報告）により、blocking errorを「受注単位で個別除外可能」
 * にする方式へ変更した。受注No・SB下版日が読み取れない行だけは所属受注を特定できないため
 * 引き続きファイル全体を止める（$errors）。それ以外の受注単位のエラーは $invalid_orders
 * として返し、呼び出し側が $excludedOrderNumbers を指定して再検証すると、その受注は完全に
 * スキップ（DB未保存）され、残りの受注だけで確定できる。除外した受注No は $warnings に記録する。
 */
class SalesImportValidator
{
    private const REQUIRED_TEXT_FIELDS = [
        'order_number' => '受注No',
    ];

    /** 空欄を警告付きで許容する項目。行は除外せずNULL保存する */
    private const OPTIONAL_TEXT_FIELDS = [
        'client_name' => '得意先名',
        'product_name' => '品名',
    ];

    /**
     * 空欄が常態化している項目。行は除外せずNULL保存し、警告も出さない
     * （2026-09-03実機検証・ユーザー確認: デジタル案件では判型・項目・分類が恒常的に空欄になる）
     */
    private const SILENT_OPTIONAL_TEXT_FIELDS = ['item_name', 'format_size', 'category'];

    private const OPTIONAL_NUMBER_FIELDS = [
        'color_count' => '色数',
        'quantity' => '台数',
        'unit_price' => '単価',
        'line_amount' => '金額',
    ];

    /** 事故損金等の値引き・調整行で負数が正当に発生する項目。負数チェックの対象外とする */
    private const NEGATIVE_ALLOWED_FIELDS = ['unit_price', 'line_amount'];

    public function __construct(private SalesWorkbookReader $reader)
    {
    }

    /**
     * @param  array<int, string>  $excludedOrderNumbers  検証エラーが確認済みで、取込対象から明示的に除外する受注No
     *                                                      （受注は一切保存されない。ユーザーが画面で選択した番号）
     */
    public function validate(
        string $filePath,
        string $departmentKey,
        string $sourceType,
        int $sourceYear,
        ?int $sourceMonth,
        ?int $sourceMonthEnd = null,
        array $excludedOrderNumbers = []
    ): array {
        $fileErrors = [];
        $warnings = [];

        try {
            $workbook = $this->reader->read($filePath);
        } catch (SalesWorkbookException $e) {
            return $this->invalidResult([$e->getMessage()]);
        }

        foreach ($workbook['header_mismatches'] as $mismatch) {
            $fileErrors[] = "見出し不一致: {$mismatch['column']}列 期待値「{$mismatch['expected']}」実際「{$mismatch['actual']}」";
        }

        $fileErrors = array_merge($fileErrors, $this->validateDepartment($workbook, $departmentKey));

        if (! empty($fileErrors)) {
            return $this->invalidResult($fileErrors);
        }

        // 受注No自体が読み取れない行はどの受注か特定できないため、除外対象にできず常にファイル全体を止める。
        // それ以外のエラーは受注単位（$ordersByNumber）に紐づけ、後続で個別に除外できるようにする。
        [$rowFileErrors, $ordersByNumber] = $this->groupRows($workbook['rows'], $sourceType, $sourceYear, $sourceMonth, $sourceMonthEnd);
        $fileErrors = array_merge($fileErrors, $rowFileErrors);

        $orders = [];
        // 受注No => エラー配列。$excludedOrderNumbersの内容に関わらず、まず全受注を検証する
        // （Codexレビュー2回目 High-1対応: 除外指定だけで検証をスキップできると、正常な受注や
        // 存在しない受注Noまで不正に取込対象外にできてしまう）
        $invalidOrders = [];

        foreach ($ordersByNumber as $bucket) {
            $orderNumber = $bucket['order_number'];
            $orderErrors = $bucket['errors'];
            $built = $this->buildOrder($orderNumber, $bucket['rows']);
            $orderErrors = array_merge($orderErrors, $built['errors']);

            if (! empty($orderErrors)) {
                $invalidOrders[$orderNumber] = $orderErrors;

                continue;
            }

            $warnings = array_merge($warnings, $bucket['warnings'], $built['warnings']);
            $orders[] = $built['order'];
        }

        // 他月との重複も受注単位のエラーとして扱う（該当受注のみ除外可能にする）
        if (empty($fileErrors)) {
            $duplicateErrorsByOrderNumber = $this->checkCrossMonthDuplicates($orders, $departmentKey);

            if (! empty($duplicateErrorsByOrderNumber)) {
                $orders = array_values(array_filter($orders, function ($order) use ($duplicateErrorsByOrderNumber, &$invalidOrders) {
                    if (! isset($duplicateErrorsByOrderNumber[$order['order_number']])) {
                        return true;
                    }
                    $invalidOrders[$order['order_number']] = [$duplicateErrorsByOrderNumber[$order['order_number']]];

                    return false;
                }));
            }
        }

        // 除外リクエストは「検証で実際にエラーと判定された受注No」に対してのみ許可する。
        // 正常な受注や存在しない受注Noが1件でも含まれていたら、ファイル全体を確定不可にする
        // （個別に無視して一部だけ除外する、という部分許可はしない）。
        $excludedOrders = [];
        if (! empty($excludedOrderNumbers) && empty($fileErrors)) {
            $illegalExclusions = array_values(array_diff($excludedOrderNumbers, array_keys($invalidOrders)));

            if (! empty($illegalExclusions)) {
                sort($illegalExclusions);
                $fileErrors[] = '除外対象として認められない受注No: ' . implode('、', $illegalExclusions)
                    . '（検証エラーのある受注のみ除外できます。正常な受注や存在しない受注Noは除外できません）';
            } else {
                foreach ($excludedOrderNumbers as $orderNumber) {
                    unset($invalidOrders[$orderNumber]);
                    $excludedOrders[] = $orderNumber;
                }
            }
        }

        if (! empty($excludedOrders)) {
            sort($excludedOrders);
            $warnings[] = '指定により除外した受注No: ' . implode('、', $excludedOrders) . '（' . count($excludedOrders) . '件は取込対象外・未保存）';
        }

        $invalidOrdersList = [];
        foreach ($invalidOrders as $orderNumber => $errors) {
            $invalidOrdersList[] = ['order_number' => (string) $orderNumber, 'errors' => $errors];
        }

        $valid = empty($fileErrors) && empty($invalidOrdersList);

        return [
            'valid' => $valid,
            'errors' => $fileErrors,
            'invalid_orders' => $invalidOrdersList,
            'excluded_orders' => $excludedOrders,
            'warnings' => $warnings,
            'orders' => $valid ? $orders : [],
            'summary' => $valid ? $this->buildSummary($orders) : null,
            'department_key' => $departmentKey,
            'source_type' => $sourceType,
            'source_year' => $sourceYear,
            'source_month' => $sourceMonth,
            'source_month_end' => $sourceMonthEnd,
        ];
    }

    private function invalidResult(array $errors): array
    {
        return [
            'valid' => false,
            'errors' => $errors,
            'invalid_orders' => [],
            'excluded_orders' => [],
            'warnings' => [],
            'orders' => [],
            'summary' => null,
            'department_key' => null,
            'source_type' => null,
            'source_year' => null,
            'source_month' => null,
            'source_month_end' => null,
        ];
    }

    private function validateDepartment(array $workbook, string $departmentKey): array
    {
        $errors = [];

        if (! SalesDepartments::isEnabled($departmentKey)) {
            $errors[] = '現在取込可能な部署は企画・制作・オンデマンドのみです。';

            return $errors;
        }

        $expectedLabel = SalesDepartments::labelFromKey($departmentKey);

        if ($workbook['department_label'] !== null && $workbook['department_label'] !== $expectedLabel) {
            $errors[] = "対象部署が一致しません（ファイル記載: {$workbook['department_label']} / 選択: {$expectedLabel}）。";
        }

        return $errors;
    }

    /**
     * 行を受注No単位にグルーピングし、行レベルのエラー・警告もその受注のバケツへ積む。
     * 受注No自体が読み取れない行だけは、どの受注か特定できず個別除外もできないため
     * ファイル全体を止めるエラー（$fileErrors）として扱う。
     *
     * @return array{0: array<int, string>, 1: array<string, array{rows: array, errors: array<int, string>, warnings: array<int, string>}>}
     */
    private function groupRows(array $rows, string $sourceType, int $sourceYear, ?int $sourceMonth, ?int $sourceMonthEnd): array
    {
        $fileErrors = [];
        $ordersByNumber = [];

        foreach ($rows as $row) {
            $result = $this->validateRow($row, $sourceType, $sourceYear, $sourceMonth, $sourceMonthEnd);

            $errors = array_map(fn ($issue) => "{$row['source_row_number']}行目: {$issue}", $result['errors']);
            $warnings = array_map(fn ($issue) => "{$row['source_row_number']}行目: {$issue}", $result['warnings']);

            if ($row['order_number'] === null) {
                $fileErrors = array_merge($fileErrors, $errors, $warnings);

                continue;
            }

            // 純数字の受注No（例: "8000013"）はPHPの配列キーとして使うと自動的にintへ変換されてしまうため、
            // 除外リスト（HTTPから渡される文字列配列）との厳密比較がずれないよう order_number を明示的に保持する
            if (! isset($ordersByNumber[$row['order_number']])) {
                $ordersByNumber[$row['order_number']] = ['order_number' => $row['order_number'], 'rows' => [], 'errors' => [], 'warnings' => []];
            }

            $ordersByNumber[$row['order_number']]['rows'][] = $row;
            $ordersByNumber[$row['order_number']]['errors'] = array_merge($ordersByNumber[$row['order_number']]['errors'], $errors);
            $ordersByNumber[$row['order_number']]['warnings'] = array_merge($ordersByNumber[$row['order_number']]['warnings'], $warnings);
        }

        return [$fileErrors, $ordersByNumber];
    }

    /** @return array{errors: array<int, string>, warnings: array<int, string>} */
    private function validateRow(array $row, string $sourceType, int $sourceYear, ?int $sourceMonth, ?int $sourceMonthEnd): array
    {
        $errors = [];
        $warnings = [];

        foreach (self::REQUIRED_TEXT_FIELDS as $key => $label) {
            if ($row[$key] === null) {
                $errors[] = "{$label}が空です。";
            }
        }

        foreach (self::OPTIONAL_TEXT_FIELDS as $key => $label) {
            if ($row[$key] === null) {
                $warnings[] = "{$label}が空欄です。";
            }
        }

        foreach (self::OPTIONAL_NUMBER_FIELDS as $key => $label) {
            if ($row[$key] === null) {
                $warnings[] = "{$label}が数値として読み取れないため空欄として扱いました。";
            } elseif ($row[$key] < 0 && ! in_array($key, self::NEGATIVE_ALLOWED_FIELDS, true)) {
                $errors[] = "{$label}が負数です。";
            }
        }

        if ($row['plate_date'] === null) {
            $errors[] = 'SB下版日が日付として読み取れません（実在しない日付の可能性があります）。';

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $rowYear = (int) substr($row['plate_date'], 0, 4);
        $rowMonth = (int) substr($row['plate_date'], 5, 2);

        if ($sourceType === 'annual' && $rowYear !== $sourceYear) {
            $errors[] = "SB下版日の年（{$rowYear}）が対象年（{$sourceYear}）と一致しません。";
        }

        if ($sourceType === 'monthly' && ($rowYear !== $sourceYear || $rowMonth !== $sourceMonth)) {
            $errors[] = "SB下版日（{$row['plate_date']}）が対象年月（{$sourceYear}年{$sourceMonth}月）と一致しません。";
        }

        if ($sourceType === 'range') {
            if ($rowYear !== $sourceYear) {
                $errors[] = "SB下版日の年（{$rowYear}）が対象年（{$sourceYear}）と一致しません。";
            } elseif ($rowMonth < $sourceMonth || $rowMonth > $sourceMonthEnd) {
                $errors[] = "SB下版日（{$row['plate_date']}）が指定範囲（{$sourceMonth}月〜{$sourceMonthEnd}月）の外です。";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function buildOrder(string $orderNumber, array $rows): array
    {
        $errors = [];
        $warnings = [];

        // Excel記載順（行番号順）で「最後の行」を判定するため明示的にソートする
        usort($rows, fn ($a, $b) => $a['source_row_number'] <=> $b['source_row_number']);

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

        // N列（受注金額）規則: 0以外の値（正または負）を持つ行は、同一受注内でちょうど1行、かつ最後の行であること。
        // 2026-09-04変更（ユーザー確認: 事故・刷り直し等で受注全体がマイナスになることもあり得るため、
        // 「正の値」限定をやめ「0以外の値」に緩和した。マイナスを理由に受注を丸ごと除外しない）。
        $nonZeroRowIndexes = [];
        $nullCount = 0;
        $zeroCount = 0;
        foreach ($rows as $i => $row) {
            $v = $row['order_amount_component'];
            if ($v === null) {
                $nullCount++;
            } elseif ($v == 0.0) {
                $zeroCount++;
            } else {
                $nonZeroRowIndexes[] = $i;
            }
        }

        if (count($nonZeroRowIndexes) === 0) {
            // 2026-09-04変更: 原因（空欄・0円・行数）を明示し、「孤立データ（関連行が無い空欄行）」なのか
            // それ以外なのかをユーザーが判別できるようにする（ユーザー報告により詳細化）
            $totalRows = count($rows);
            $errors[] = "受注No {$orderNumber}: 受注金額（N列）に0以外の値（正または負）がありません（全{$totalRows}行中、空欄{$nullCount}行・0円{$zeroCount}行）。";
        } elseif (count($nonZeroRowIndexes) > 1) {
            $posCount = count(array_filter($nonZeroRowIndexes, fn ($i) => $rows[$i]['order_amount_component'] > 0));
            $negCount = count($nonZeroRowIndexes) - $posCount;
            $errors[] = "受注No {$orderNumber}: 受注金額（N列）に0以外の値を持つ行が複数（{$posCount}行 正の値・{$negCount}行 負の値）あります。";
        } elseif (end($nonZeroRowIndexes) !== array_key_last($rows)) {
            $value = $rows[end($nonZeroRowIndexes)]['order_amount_component'];
            $sign = $value < 0 ? '負' : '正';
            $errors[] = "受注No {$orderNumber}: 受注金額（N列）の{$sign}の値（" . number_format($value) . '）が最後の行にありません。';
        }

        if (! empty($errors)) {
            return ['order' => null, 'errors' => $errors, 'warnings' => []];
        }

        if ($nullCount > 0) {
            $warnings[] = "受注No {$orderNumber}: 受注金額（N列）が空欄の行が{$nullCount}件あります（0として扱いました）。";
        }

        $orderAmount = (float) $rows[array_key_last($rows)]['order_amount_component'];

        // 未配賦額 = 受注金額（N列。正または負） − 明細内訳合計（M列。NULLは0として合算）
        $lineAmountSum = array_sum(array_map(fn ($r) => $r['line_amount'] ?? 0.0, $rows));
        $unallocatedAmount = $orderAmount - $lineAmountSum;

        if (abs($unallocatedAmount) > 0.01) {
            $warnings[] = "受注No {$orderNumber}: 明細金額合計（" . number_format($lineAmountSum) . '）と受注金額（' . number_format($orderAmount) . '）に差額（未配賦額 ' . number_format($unallocatedAmount) . '）があります。';
        }

        return [
            'order' => [
                'order_number' => $orderNumber,
                'client_name' => $clientName,
                'product_name' => $productName,
                'plate_date' => $plateDate,
                'sales_year' => (int) substr($plateDate, 0, 4),
                'sales_month' => (int) substr($plateDate, 5, 2),
                'order_amount' => $orderAmount,
                'unallocated_amount' => $unallocatedAmount,
                'details' => $rows,
            ],
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    /**
     * 現在有効な他月に同じ受注Noが存在しないかを確認する（sales DB接続）。
     *
     * @return array<string, string> 受注No => エラーメッセージ
     */
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

        $errorsByOrderNumber = [];
        foreach ($orders as $order) {
            $existingOrder = $existing->get($order['order_number']);
            if (! $existingOrder) {
                continue;
            }
            if ((int) $existingOrder->sales_year === $order['sales_year'] && (int) $existingOrder->sales_month === $order['sales_month']) {
                continue; // 同月の再取込（正常なケース）
            }
            $errorsByOrderNumber[$order['order_number']] = "受注No {$order['order_number']} は既に{$existingOrder->sales_year}年{$existingOrder->sales_month}月分として取込済みです。";
        }

        return $errorsByOrderNumber;
    }

    private function buildSummary(array $orders): array
    {
        return [
            'order_count' => count($orders),
            'detail_count' => array_sum(array_map(fn ($o) => count($o['details']), $orders)),
            'total_amount' => array_sum(array_column($orders, 'order_amount')),
            // 未配賦額の合計。0以外ならプレビュー・ダッシュボードで必ず提示する（隠さない）
            'total_unallocated_amount' => array_sum(array_column($orders, 'unallocated_amount')),
        ];
    }
}
