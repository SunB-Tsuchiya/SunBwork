<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;

/**
 * 部署別経費データ（Phase 21）の集計。売上とは別の集計軸のため`SalesQueryService`とは
 * 独立させている。粗利計算は行わない（売上と並べて表示する参考情報としての集計のみ）。
 */
class SalesExpenseQueryService
{
    private ?int $companyId = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExpenseQueryService: forCompany()が呼ばれる前にクエリメソッドが実行されました。');
        }

        return $this->companyId;
    }

    /** 指定会社・会計年度で現在有効な版のID（無ければnull） */
    private function activeImportId(int $fiscalYear): ?int
    {
        return SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->where('fiscal_year', $fiscalYear)
            ->orderByDesc('version')
            ->value('id');
    }

    /** その会計年度に経費データが1件でもあるか */
    public function hasData(int $fiscalYear): bool
    {
        return $this->activeImportId($fiscalYear) !== null;
    }

    /**
     * 指定会計年度に実データがある部署一覧（企画/制作/オンデマンド/共通費のうち該当するもの）。
     * @return array<int, string>
     */
    public function availableDepartments(int $fiscalYear): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return [];
        }

        return SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->distinct()
            ->pluck('department_key')
            ->all();
    }

    /**
     * 部署×科目×月の金額表。会計年度の4月始まり順で返す。
     *
     * @return array{has_data: bool, categories: array<int, array>, total_amount: float}
     */
    public function departmentMonthlyTotals(int $fiscalYear, string $departmentKey): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return ['has_data' => false, 'categories' => [], 'total_amount' => 0.0];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->selectRaw('category, calendar_year, calendar_month, MIN(category_order) as category_order, SUM(amount) as amount')
            ->groupBy('category', 'calendar_year', 'calendar_month')
            ->get();

        // インポート自体は存在しても、その部署に該当する行が無い場合は「データ無し」として扱う
        // （例: 共有シートの経費が無いまま企画/制作/オンデマンドだけ取り込まれているケース）
        if ($rows->isEmpty()) {
            return ['has_data' => false, 'categories' => [], 'total_amount' => 0.0];
        }

        $splitCategories = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->where('is_shared_split', true)
            ->distinct()
            ->pluck('category')
            ->all();

        $sequence = $this->fiscalMonthSequence($fiscalYear);

        $categories = $rows->groupBy('category')->map(function ($categoryRows, $category) use ($sequence, $splitCategories) {
            $byCalendar = $categoryRows->keyBy(fn ($r) => "{$r->calendar_year}-{$r->calendar_month}");

            $total = 0.0;
            $months = [];
            foreach ($sequence as $i => [$calendarYear, $calendarMonth]) {
                $row = $byCalendar->get("{$calendarYear}-{$calendarMonth}");
                $amount = $row ? (float) $row->amount : 0.0;
                $months[] = [
                    'fiscal_month_index' => $i + 1,
                    'calendar_year' => $calendarYear,
                    'calendar_month' => $calendarMonth,
                    'amount' => $amount,
                ];
                $total += $amount;
            }

            return [
                'category' => $category,
                'category_order' => (int) $categoryRows->first()->category_order,
                'months' => $months,
                'total_amount' => $total,
                'is_shared_split' => in_array($category, $splitCategories, true),
            ];
        })->sortBy('category_order')->values()->map(function ($c) {
            unset($c['category_order']);

            return $c;
        })->all();

        return [
            'has_data' => true,
            'categories' => $categories,
            'total_amount' => array_sum(array_column($categories, 'total_amount')),
        ];
    }

    /**
     * 指定科目の会社名（支払先）別内訳。「どこにどれだけ払っているか」の確認用。
     * Excel上に小計（小計①等）の区切りがあった科目は、その区切りをそのままグループとして
     * 再現する（グループはExcel登場順、グループ内は金額の大きい順）。区切りが無かった
     * 科目は常にグループ数1になり、`subtotal_label`はnullのまま（小計行は表示しない）。
     *
     * @return array{has_data: bool, groups: array<int, array{vendors: array<int, array>, subtotal_label: ?string, subtotal_amount: float}>, total_amount: float, is_shared_split: bool}
     */
    public function vendorBreakdown(int $fiscalYear, string $departmentKey, string $category): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return ['has_data' => false, 'groups' => [], 'total_amount' => 0.0, 'is_shared_split' => false];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->where('category', $category)
            ->selectRaw('vendor_name, subtotal_group, MAX(subtotal_label) as subtotal_label, SUM(amount) as amount, MAX(is_shared_split) as is_shared_split')
            ->groupBy('vendor_name', 'subtotal_group')
            ->get();

        if ($rows->isEmpty()) {
            return ['has_data' => false, 'groups' => [], 'total_amount' => 0.0, 'is_shared_split' => false];
        }

        $total = (float) $rows->sum('amount');
        $isSplit = $rows->contains(fn ($r) => (bool) $r->is_shared_split);

        $groups = $rows->groupBy('subtotal_group')->sortKeys()->map(function ($groupRows) use ($total) {
            $vendors = $groupRows->map(fn ($r) => [
                'vendor_name' => $r->vendor_name ?? '（内訳未記入）',
                'amount' => (float) $r->amount,
                'share_pct' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
            ])->sortByDesc('amount')->values()->all();

            return [
                'vendors' => $vendors,
                'subtotal_label' => $groupRows->first()->subtotal_label,
                'subtotal_amount' => array_sum(array_column($vendors, 'amount')),
            ];
        })->values()->all();

        return ['has_data' => true, 'groups' => $groups, 'total_amount' => $total, 'is_shared_split' => $isSplit];
    }

    /** 会計年度（開始暦年）の4月始まり12ヶ月を [暦年, 暦月] の配列で返す */
    private function fiscalMonthSequence(int $fiscalYear): array
    {
        $sequence = [];
        foreach (range(4, 12) as $m) {
            $sequence[] = [$fiscalYear, $m];
        }
        foreach (range(1, 3) as $m) {
            $sequence[] = [$fiscalYear + 1, $m];
        }

        return $sequence;
    }

    /** 対象会社で経費取込が存在する会計年度一覧（昇順。Phase22: 経費比較） */
    public function availableFiscalYears(): array
    {
        return SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->distinct()
            ->orderBy('fiscal_year')
            ->pluck('fiscal_year')
            ->all();
    }

    /**
     * 直近で取込実績がある会計年度の科目一覧（category_order順）。
     * 経費比較画面の科目セレクタ用（Phase22）。
     *
     * @return array<int, string>
     */
    public function categoriesForDepartment(string $departmentKey): array
    {
        foreach (array_reverse($this->availableFiscalYears()) as $fiscalYear) {
            $result = $this->departmentMonthlyTotals($fiscalYear, $departmentKey);
            if ($result['has_data']) {
                return array_column($result['categories'], 'category');
            }
        }

        return [];
    }

    /**
     * 選んだ科目の金額を、取込実績がある会計年度すべてで集計する（複数年推移。Phase22）。
     * その部署の総経費に占める割合もあわせて返す。
     *
     * @return array{years: array<int, array{fiscal_year:int, amount:float, department_total:float, share_pct:?float, is_shared_split:bool}>}
     */
    public function categoryMultiYearTrend(string $departmentKey, string $category): array
    {
        $years = [];
        foreach ($this->availableFiscalYears() as $fiscalYear) {
            $result = $this->departmentMonthlyTotals($fiscalYear, $departmentKey);
            $categoryRow = collect($result['categories'])->firstWhere('category', $category);
            $amount = $categoryRow['total_amount'] ?? 0.0;
            $departmentTotal = $result['total_amount'];

            $years[] = [
                'fiscal_year' => $fiscalYear,
                'amount' => $amount,
                'department_total' => $departmentTotal,
                'share_pct' => $departmentTotal > 0 ? round($amount / $departmentTotal * 100, 1) : null,
                'is_shared_split' => $categoryRow['is_shared_split'] ?? false,
            ];
        }

        return ['years' => $years];
    }

    /**
     * 支払先別の金額をExcelの小計区切りを無視してフラットに集計する（依存度ランキング用。Phase22）。
     * `vendorBreakdown()`はExcel構造の再現が目的のため別メソッドとして分離している。
     *
     * @return array{has_data: bool, vendors: array<int, array{vendor_name:string, amount:float, share_pct:?float}>, total_amount: float}
     */
    public function vendorRanking(int $fiscalYear, string $departmentKey, string $category): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return ['has_data' => false, 'vendors' => [], 'total_amount' => 0.0];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->where('category', $category)
            ->selectRaw('vendor_name, SUM(amount) as amount')
            ->groupBy('vendor_name')
            ->get();

        if ($rows->isEmpty()) {
            return ['has_data' => false, 'vendors' => [], 'total_amount' => 0.0];
        }

        $total = (float) $rows->sum('amount');
        $vendors = $rows->map(fn ($r) => [
            'vendor_name' => $r->vendor_name ?? '（内訳未記入）',
            'amount' => (float) $r->amount,
            'share_pct' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
        ])->sortByDesc('amount')->values()->all();

        return ['has_data' => true, 'vendors' => $vendors, 'total_amount' => $total];
    }

    /**
     * 選んだ会計年度の支払先ランキングに、前年度の同じ支払先の金額・増減を付加する（Phase22）。
     * 前年に無かった支払先はprevious_amountがnullになる。前年に存在し今年消えた支払先は
     * 「ざっくりで良い」という要件に合わせ、複雑な離脱判定はせず一覧に含めない。
     *
     * @return array{has_data: bool, vendors: array<int, array>, total_amount: float, previous_fiscal_year: int, previous_has_data: bool}
     */
    public function vendorComparison(int $fiscalYear, string $departmentKey, string $category): array
    {
        $current = $this->vendorRanking($fiscalYear, $departmentKey, $category);
        $previous = $this->vendorRanking($fiscalYear - 1, $departmentKey, $category);
        $previousByVendor = collect($previous['vendors'])->keyBy('vendor_name');

        $vendors = collect($current['vendors'])->map(function ($v) use ($previousByVendor) {
            $prev = $previousByVendor->get($v['vendor_name']);
            $prevAmount = $prev['amount'] ?? null;
            $diff = $prevAmount !== null ? $v['amount'] - $prevAmount : null;

            return array_merge($v, [
                'previous_amount' => $prevAmount,
                'diff' => $diff,
                'diff_pct' => ($prevAmount !== null && $prevAmount > 0) ? round($diff / $prevAmount * 100, 1) : null,
            ]);
        })->values()->all();

        return [
            'has_data' => $current['has_data'],
            'vendors' => $vendors,
            'total_amount' => $current['total_amount'],
            'previous_fiscal_year' => $fiscalYear - 1,
            'previous_has_data' => $previous['has_data'],
        ];
    }
}
