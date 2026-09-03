<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * 売上分析の集計クエリを一元化する。
 * すべてのクエリは sales_active_months に紐づく有効版のみを対象にする
 * （単純な created_at 最大値では判定しない）。
 */
class SalesQueryService
{
    public const FISCAL_MODE_CALENDAR = 'calendar';

    public const FISCAL_MODE_APRIL = 'fiscal_april';

    public function __construct(private SalesImportService $importService)
    {
    }

    /**
     * sales_active_months に紐づく（＝現在有効な版の）sales_orders だけを対象にする基底クエリ。
     * select句は呼び出し側で明示する（selectRaw()の集計と衝突しないよう、ここでは指定しない）。
     * $departmentKey に配列を渡すと複数部署（「全部署合計」用）を対象にできる。
     */
    private function activeOrdersQuery(string|array $departmentKey): Builder
    {
        return SalesOrder::query()
            ->join('sales_active_months', function ($join) use ($departmentKey) {
                $join->on('sales_orders.sales_import_id', '=', 'sales_active_months.sales_import_id')
                    ->on('sales_orders.sales_year', '=', 'sales_active_months.sales_year')
                    ->on('sales_orders.sales_month', '=', 'sales_active_months.sales_month');

                is_array($departmentKey)
                    ? $join->whereIn('sales_active_months.department_key', $departmentKey)
                    : $join->where('sales_active_months.department_key', $departmentKey);
            });
    }

    public function hasActiveMonth(string $departmentKey, int $year, int $month): bool
    {
        return SalesActiveMonth::where('department_key', $departmentKey)
            ->where('sales_year', $year)
            ->where('sales_month', $month)
            ->exists();
    }

    /** 単一月の合計金額・受注件数・平均受注金額。未取込月は null（0円と誤表示しない） */
    public function monthlyTotal(string $departmentKey, int $year, int $month): ?array
    {
        if (! $this->hasActiveMonth($departmentKey, $year, $month)) {
            return null;
        }

        $row = $this->activeOrdersQuery($departmentKey)
            ->where('sales_orders.sales_year', $year)
            ->where('sales_orders.sales_month', $month)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount, COALESCE(SUM(unallocated_amount), 0) as total_unallocated_amount')
            ->first();

        $orderCount = (int) $row->order_count;
        $totalAmount = (float) $row->total_amount;

        return [
            'year' => $year,
            'month' => $month,
            'order_count' => $orderCount,
            'total_amount' => $totalAmount,
            // M列合計とN列受注金額の差額（隠さず提示する。Codexレビュー6.2 Medium-1）
            'total_unallocated_amount' => (float) $row->total_unallocated_amount,
            'average_amount' => $orderCount > 0 ? round($totalAmount / $orderCount, 2) : 0.0,
        ];
    }

    /** 当月・前月・前年同月とそれぞれの増減額・増減率（比較対象がなければ null） */
    public function monthlyComparison(string $departmentKey, int $year, int $month): array
    {
        $current = $this->monthlyTotal($departmentKey, $year, $month);

        [$prevYear, $prevMonth] = $this->shiftMonth($year, $month, -1);
        $previous = $this->monthlyTotal($departmentKey, $prevYear, $prevMonth);

        $previousYearSame = $this->monthlyTotal($departmentKey, $year - 1, $month);

        return [
            'current' => $current,
            'previous' => $previous,
            'previous_year_same_month' => $previousYearSame,
            'vs_previous' => $this->compareAmount($current, $previous),
            'vs_previous_year' => $this->compareAmount($current, $previousYearSame),
        ];
    }

    private function compareAmount(?array $current, ?array $base): array
    {
        if ($current === null || $base === null) {
            return ['diff' => null, 'rate' => null];
        }

        $diff = $current['total_amount'] - $base['total_amount'];
        $rate = $base['total_amount'] > 0 ? round($diff / $base['total_amount'] * 100, 1) : null;

        return ['diff' => $diff, 'rate' => $rate];
    }

    /**
     * 年度累計・前年同期累計。mode は self::FISCAL_MODE_* を指定。
     * 会計年度（4月始まり）は開始年を年度名として採用する（例: 2026年4月〜2027年3月＝「2026年度」）。
     */
    public function fiscalYearToDate(string $departmentKey, int $year, int $month, string $mode): array
    {
        $bounds = $this->fiscalYearBounds($year, $month, $mode);
        $current = $this->cumulativeTotal($departmentKey, $bounds['start_year'], $bounds['start_month'], $year, $month);

        $prevBounds = $this->fiscalYearBounds($year - 1, $month, $mode);
        $previous = $this->cumulativeTotal($departmentKey, $prevBounds['start_year'], $prevBounds['start_month'], $year - 1, $month);

        $diff = $current['total_amount'] - $previous['total_amount'];
        $rate = $previous['total_amount'] > 0 ? round($diff / $previous['total_amount'] * 100, 1) : null;

        return [
            'mode' => $mode,
            'fiscal_year' => $bounds['fiscal_year'],
            'current' => $current,
            'previous' => $previous,
            'diff' => $diff,
            'rate' => $rate,
        ];
    }

    private function fiscalYearBounds(int $year, int $month, string $mode): array
    {
        if ($mode === self::FISCAL_MODE_APRIL) {
            $fiscalYear = $month >= 4 ? $year : $year - 1;

            return ['fiscal_year' => $fiscalYear, 'start_year' => $fiscalYear, 'start_month' => 4];
        }

        return ['fiscal_year' => $year, 'start_year' => $year, 'start_month' => 1];
    }

    private function cumulativeTotal(string $departmentKey, int $startYear, int $startMonth, int $endYear, int $endMonth): array
    {
        $row = $this->activeOrdersQuery($departmentKey)
            ->where($this->periodFromCondition($startYear, $startMonth))
            ->where($this->periodToCondition($endYear, $endMonth))
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount')
            ->first();

        return [
            'order_count' => (int) $row->order_count,
            'total_amount' => (float) $row->total_amount,
        ];
    }

    private function periodFromCondition(int $startYear, int $startMonth): Closure
    {
        return function (Builder $q) use ($startYear, $startMonth) {
            $q->where('sales_orders.sales_year', '>', $startYear)
                ->orWhere(function (Builder $q2) use ($startYear, $startMonth) {
                    $q2->where('sales_orders.sales_year', $startYear)
                        ->where('sales_orders.sales_month', '>=', $startMonth);
                });
        };
    }

    private function periodToCondition(int $endYear, int $endMonth): Closure
    {
        return function (Builder $q) use ($endYear, $endMonth) {
            $q->where('sales_orders.sales_year', '<', $endYear)
                ->orWhere(function (Builder $q2) use ($endYear, $endMonth) {
                    $q2->where('sales_orders.sales_year', $endYear)
                        ->where('sales_orders.sales_month', '<=', $endMonth);
                });
        };
    }

    /**
     * 指定終了年月から遡って years 年分（既定5年）の月別推移。
     * 未取込月は total_amount/order_count が null になる。
     */
    public function monthlyTrend(string $departmentKey, int $endYear, int $endMonth, int $years = 5): array
    {
        [$startYear, $startMonth] = $this->shiftMonth($endYear, $endMonth, -($years * 12 - 1));

        $activeMonthKeys = SalesActiveMonth::where('department_key', $departmentKey)
            ->get(['sales_year', 'sales_month'])
            ->map(fn ($m) => "{$m->sales_year}-{$m->sales_month}")
            ->flip();

        $rows = $this->activeOrdersQuery($departmentKey)
            ->where($this->periodFromCondition($startYear, $startMonth))
            ->where($this->periodToCondition($endYear, $endMonth))
            ->selectRaw('sales_orders.sales_year as y, sales_orders.sales_month as m, COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount')
            ->groupBy('sales_orders.sales_year', 'sales_orders.sales_month')
            ->get()
            ->keyBy(fn ($r) => "{$r->y}-{$r->m}");

        $months = [];
        $cursor = [$startYear, $startMonth];
        for ($i = 0; $i < $years * 12; $i++) {
            $key = "{$cursor[0]}-{$cursor[1]}";
            $isActive = $activeMonthKeys->has($key);
            $row = $rows->get($key);

            $months[] = [
                'year' => $cursor[0],
                'month' => $cursor[1],
                'total_amount' => $isActive ? (float) ($row->total_amount ?? 0) : null,
                'order_count' => $isActive ? (int) ($row->order_count ?? 0) : null,
            ];

            $cursor = $this->shiftMonth($cursor[0], $cursor[1], 1);
        }

        return $months;
    }

    /** 得意先別ランキング。$consolidate=true で会社統合グループ名を使用する */
    public function clientRanking(string $departmentKey, int $year, int $month, bool $consolidate = false, ?int $limit = 10): array
    {
        if (! $this->hasActiveMonth($departmentKey, $year, $month)) {
            return ['has_data' => false, 'total_amount' => 0.0, 'ranking' => [], 'all_count' => 0];
        }

        $orders = $this->activeOrdersQuery($departmentKey)
            ->where('sales_orders.sales_year', $year)
            ->where('sales_orders.sales_month', $month)
            ->get(['client_name', 'order_amount']);

        $resolveName = $consolidate ? $this->clientDisplayNameResolver() : fn ($name) => $name;
        $total = (float) $orders->sum('order_amount');

        // 得意先名が空欄（NULL）の受注は「（得意先未設定）」としてまとめる（Codexレビュー6.2 High-2）
        $ranking = $orders->groupBy(fn ($o) => $o->client_name === null ? '（得意先未設定）' : $resolveName($o->client_name))
            ->map(function ($group, $name) use ($total) {
                $amount = (float) $group->sum('order_amount');

                return [
                    'name' => $name,
                    'amount' => $amount,
                    'share' => $total > 0 ? round($amount / $total * 100, 1) : null,
                    'order_count' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'has_data' => true,
            'total_amount' => $total,
            'ranking' => ($limit ? $ranking->take($limit) : $ranking)->all(),
            'all_count' => $ranking->count(),
        ];
    }

    private function clientDisplayNameResolver(): Closure
    {
        $map = SalesClientGroupMember::with('group')
            ->get()
            ->filter(fn ($m) => $m->group)
            ->mapWithKeys(fn ($m) => [$m->client_name => $m->group->name]);

        return fn (string $clientName) => $map->get($clientName, $clientName);
    }

    /** 分類別内訳（明細のcategoryをline_amountで集計） */
    public function categoryBreakdown(string $departmentKey, int $year, int $month): array
    {
        return $this->detailBreakdown($departmentKey, $year, $month, 'category');
    }

    /** 項目別内訳（明細のitem_nameをline_amountで集計） */
    public function itemBreakdown(string $departmentKey, int $year, int $month): array
    {
        return $this->detailBreakdown($departmentKey, $year, $month, 'item_name');
    }

    private function detailBreakdown(string $departmentKey, int $year, int $month, string $column): array
    {
        if (! $this->hasActiveMonth($departmentKey, $year, $month)) {
            return ['has_data' => false, 'total_amount' => 0.0, 'breakdown' => []];
        }

        $orderIds = $this->activeOrdersQuery($departmentKey)
            ->where('sales_orders.sales_year', $year)
            ->where('sales_orders.sales_month', $month)
            ->pluck('sales_orders.id');

        $rows = SalesOrderDetail::whereIn('sales_order_id', $orderIds)
            ->selectRaw("{$column} as label, COALESCE(SUM(line_amount), 0) as amount, COUNT(*) as detail_count")
            ->groupBy($column)
            ->get();

        $total = (float) $rows->sum('amount');

        // 分類・項目が空欄（NULL）の明細は「（未設定）」ラベルでまとめる（Codexレビュー6.2 High-2）
        $unsetLabel = $this->unsetLabelFor($column);
        $breakdown = $rows->map(fn ($r) => [
            'label' => $r->label ?? $unsetLabel,
            'amount' => (float) $r->amount,
            'share' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
            'detail_count' => $r->detail_count,
        ])->sortByDesc('amount')->values();

        return ['has_data' => true, 'total_amount' => $total, 'breakdown' => $breakdown->all()];
    }

    private function unsetLabelFor(string $column): string
    {
        return match ($column) {
            'category' => '（分類未設定）',
            'item_name' => '（項目未設定）',
            default => '（未設定）',
        };
    }

    /** 品名の部分一致検索（対象月の受注一覧を返す） */
    public function searchByProductName(string $departmentKey, int $year, int $month, string $keyword): array
    {
        if (! $this->hasActiveMonth($departmentKey, $year, $month)) {
            return [];
        }

        $escaped = addcslashes($keyword, '%_\\');

        return $this->activeOrdersQuery($departmentKey)
            ->where('sales_orders.sales_year', $year)
            ->where('sales_orders.sales_month', $month)
            ->where('product_name', 'like', "%{$escaped}%")
            ->orderByDesc('order_amount')
            ->get(['order_number', 'client_name', 'product_name', 'order_amount', 'plate_date'])
            ->map(fn ($o) => [
                'order_number' => $o->order_number,
                'client_name' => $o->client_name,
                'product_name' => $o->product_name,
                'order_amount' => (float) $o->order_amount,
                'plate_date' => $o->plate_date?->format('Y-m-d'),
            ])
            ->all();
    }

    /** @return array{0: int, 1: int} */
    private function shiftMonth(int $year, int $month, int $delta): array
    {
        $total = ($year * 12 + ($month - 1)) + $delta;

        return [(int) intdiv($total, 12), ($total % 12) + 1];
    }

    /**
     * 部署単位のデータ登録状況（年度×1〜12月）。データ登録状況画面で使用する。
     * ファイルの区切り（年次/半期/月次）に関係なく、月単位の登録有無・売上額・警告バッジを返す
     * （2026-09-03 Codexレビュー2回目 9〜13章）。
     *
     * @return array<int, array> 年度が新しい順の配列
     */
    public function registrationStatusByDepartment(string $departmentKey): array
    {
        $activeMonths = SalesActiveMonth::where('department_key', $departmentKey)->get();

        if ($activeMonths->isEmpty()) {
            return [];
        }

        $importIds = $activeMonths->pluck('sales_import_id')->unique();

        // 月別の受注合計・件数・未配賦額をまとめて取得する（年度×部署のループ内でN+1にしない）
        $orderTotals = SalesOrder::whereIn('sales_import_id', $importIds)
            ->selectRaw('sales_import_id, sales_year, sales_month, COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount, COALESCE(SUM(unallocated_amount), 0) as total_unallocated_amount')
            ->groupBy('sales_import_id', 'sales_year', 'sales_month')
            ->get()
            ->keyBy(fn ($r) => "{$r->sales_import_id}-{$r->sales_year}-{$r->sales_month}");

        $activeByYearMonth = $activeMonths->keyBy(fn ($m) => "{$m->sales_year}-{$m->sales_month}");

        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        $years = $activeMonths->pluck('sales_year')->unique()->sortDesc()->values();

        return $years->map(function ($year) use ($activeByYearMonth, $orderTotals, $activeMonths, $currentYear, $currentMonth) {
            $isCurrentYear = $year === $currentYear;
            $dueMonthCount = $isCurrentYear ? min($currentMonth, 12) : ($year > $currentYear ? 0 : 12);

            $registeredMonthCount = 0;
            $totalAmount = 0.0;
            $totalOrderCount = 0;
            $hasAnyIssue = false;
            $hasAnyNeedsReview = false;

            $months = [];
            for ($month = 1; $month <= 12; $month++) {
                $activeRow = $activeByYearMonth->get("{$year}-{$month}");
                $isFuture = $year > $currentYear || ($isCurrentYear && $month > $currentMonth);

                if (! $activeRow) {
                    $months[] = [
                        'month' => $month,
                        'state' => $isFuture ? 'future' : 'no_data',
                        'amount' => null,
                        'order_count' => null,
                        'needs_review' => false,
                        'has_issue' => false,
                    ];

                    continue;
                }

                $totals = $orderTotals->get("{$activeRow->sales_import_id}-{$year}-{$month}");
                $amount = $totals ? (float) $totals->total_amount : 0.0;
                $orderCount = $totals ? (int) $totals->order_count : 0;
                $unallocated = $totals ? (float) $totals->total_unallocated_amount : 0.0;

                // created_at と updated_at が異なる＝この月のactive pointerが再取込で切り替わったことがある
                // （新規カラムを追加せず既存タイムスタンプだけで「複数回取込あり」を判定できる）
                $needsReview = $activeRow->created_at && $activeRow->updated_at
                    && ! $activeRow->created_at->equalTo($activeRow->updated_at);
                $hasIssue = abs($unallocated) > 0.01;

                $months[] = [
                    'month' => $month,
                    'state' => $amount > 0 ? 'has_sales' : 'zero',
                    'amount' => $amount,
                    'order_count' => $orderCount,
                    'needs_review' => $needsReview,
                    'has_issue' => $hasIssue,
                    'issue_amount' => $hasIssue ? $unallocated : null,
                ];

                $registeredMonthCount++;
                $totalAmount += $amount;
                $totalOrderCount += $orderCount;
                $hasAnyIssue = $hasAnyIssue || $hasIssue;
                $hasAnyNeedsReview = $hasAnyNeedsReview || $needsReview;
            }

            $yearActiveMonths = $activeMonths->where('sales_year', $year);
            $fileCount = $yearActiveMonths->pluck('sales_import_id')->unique()->count();
            $latest = $yearActiveMonths->sortByDesc('activated_at')->first();
            $latestUserName = $latest ? User::find($latest->activated_by)?->name : null;

            return [
                'year' => $year,
                'is_current_year' => $isCurrentYear,
                'registered_month_count' => $registeredMonthCount,
                'total_due_month_count' => $dueMonthCount,
                'total_amount' => $totalAmount,
                'order_count' => $totalOrderCount,
                'file_count' => $fileCount,
                'latest_registration' => $latest ? [
                    'at' => optional($latest->activated_at)->toIso8601String(),
                    'by' => $latestUserName ?? '不明',
                ] : null,
                'has_any_issue' => $hasAnyIssue,
                'has_any_needs_review' => $hasAnyNeedsReview,
                'months' => $months,
            ];
        })->all();
    }

    /**
     * 指定部署・年度を構成するExcelファイル一覧（登録状況画面の年度行を開いたときに使う）。
     * active_month_count / total_month_count は「一部の月だけ有効」を可視化するために返す
     * （Codexレビュー2回目 8.1 Medium-2: is_activeが1ヶ月でも有効ならtrueになる問題への対応）。
     *
     * @return array<int, array>
     */
    public function registrationStatusFiles(string $departmentKey, int $year): array
    {
        $imports = SalesImport::where('department_key', $departmentKey)
            ->where('source_year', $year)
            ->orderByDesc('imported_at')
            ->get();

        if ($imports->isEmpty()) {
            return [];
        }

        $activeCounts = SalesActiveMonth::where('department_key', $departmentKey)
            ->whereIn('sales_import_id', $imports->pluck('id'))
            ->selectRaw('sales_import_id, COUNT(*) as active_count')
            ->groupBy('sales_import_id')
            ->pluck('active_count', 'sales_import_id');

        $userNames = User::whereIn('id', $imports->pluck('imported_by')->unique())->pluck('name', 'id');

        return $imports->map(function (SalesImport $import) use ($activeCounts, $userNames) {
            $totalMonthCount = $this->importService->targetMonths(
                $import->source_type,
                $import->source_year,
                $import->source_month,
                $import->source_month_end
            )->count();
            $activeMonthCount = (int) ($activeCounts->get($import->id) ?? 0);

            return [
                'sales_import_id' => $import->id,
                'original_filename' => $import->original_filename,
                'source_type' => $import->source_type,
                'period_label' => $this->periodLabel($import),
                'version' => $import->version,
                'active_month_count' => $activeMonthCount,
                'total_month_count' => $totalMonthCount,
                'is_fully_active' => $activeMonthCount === $totalMonthCount,
                'imported_at' => optional($import->imported_at)->toIso8601String(),
                'imported_by' => $userNames->get($import->imported_by, '不明'),
            ];
        })->values()->all();
    }

    private function periodLabel(SalesImport $import): string
    {
        return match ($import->source_type) {
            'annual' => '1〜12月',
            'range' => "{$import->source_month}〜{$import->source_month_end}月",
            default => "{$import->source_month}月",
        };
    }

    /**
     * 年次分析画面用の集計。$departmentKey に'all'を渡すと企画・制作・オンデマンドの3部署を合算する
     * （2026-09-03 Codexレビュー2回目 10.3/6C-2節）。
     *
     * 進行中の年（今年かつ12月まで登録されていない年）は、登録済みの最終月までを対象に前年の
     * 同期間と比較する（前年12ヶ月合計とは分母を分けない）。過去年でも一部の月が未登録なら
     * 同じ考え方で「登録済みの最終月まで」を対象にする。
     */
    public function annualSummary(string $departmentKey, int $year): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);

        $monthlyCurrent = $this->monthlyFiguresForYear($departmentKeys, $year);
        $monthlyPrior = $this->monthlyFiguresForYear($departmentKeys, $year - 1);

        $currentYear = (int) now()->format('Y');
        $currentMonthNow = (int) now()->format('n');
        $isCurrentYear = $year === $currentYear;

        $lastRegisteredMonth = 0;
        foreach ($monthlyCurrent as $m => $data) {
            if ($data !== null) {
                $lastRegisteredMonth = max($lastRegisteredMonth, $m);
            }
        }

        $comparisonMode = $lastRegisteredMonth >= 12 ? 'full' : 'partial';
        $comparisonRange = [1, max($lastRegisteredMonth, 1)];

        $periodAmount = 0.0;
        $priorPeriodAmount = 0.0;
        $orderCount = 0;
        $priorOrderCount = 0;
        $unallocatedAmount = 0.0;

        for ($m = 1; $m <= $lastRegisteredMonth; $m++) {
            if ($monthlyCurrent[$m]) {
                $periodAmount += $monthlyCurrent[$m]['amount'];
                $orderCount += $monthlyCurrent[$m]['order_count'];
                $unallocatedAmount += $monthlyCurrent[$m]['unallocated_amount'];
            }
            if ($monthlyPrior[$m]) {
                $priorPeriodAmount += $monthlyPrior[$m]['amount'];
                $priorOrderCount += $monthlyPrior[$m]['order_count'];
            }
        }

        $fullPriorYearAmount = array_sum(array_map(
            fn ($m) => $monthlyPrior[$m]['amount'] ?? 0.0,
            range(1, 12)
        ));

        $amountDiff = $periodAmount - $priorPeriodAmount;

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $cur = $monthlyCurrent[$m];
            $pri = $monthlyPrior[$m];
            $isFuture = $isCurrentYear && $m > $currentMonthNow;
            $state = $cur === null ? ($isFuture ? 'future' : 'no_data') : ($cur['amount'] > 0 ? 'has_sales' : 'zero');
            $diff = ($cur !== null && $pri !== null) ? $cur['amount'] - $pri['amount'] : null;
            $rate = ($diff !== null && $pri['amount'] > 0) ? round($diff / $pri['amount'] * 100, 1) : null;

            $monthly[] = [
                'month' => $m,
                'amount' => $cur['amount'] ?? null,
                'prior_year_amount' => $pri['amount'] ?? null,
                'diff' => $diff,
                'rate' => $rate,
                'order_count' => $cur['order_count'] ?? null,
                'avg_order_amount' => $cur !== null
                    ? ($cur['order_count'] > 0 ? round($cur['amount'] / $cur['order_count'], 2) : 0.0)
                    : null,
                'state' => $state,
                'needs_review' => $cur['needs_review'] ?? false,
                'has_issue' => $cur['has_issue'] ?? false,
            ];
        }

        return [
            'department_key' => $departmentKey,
            'year' => $year,
            'is_current_year' => $isCurrentYear,
            'months_registered' => $lastRegisteredMonth,
            'comparison_year' => $year - 1,
            'comparison_mode' => $comparisonMode,
            'comparison_month_range' => $comparisonRange,
            'kpi' => [
                'period_amount' => $periodAmount,
                'prior_period_amount' => $priorPeriodAmount,
                'amount_diff' => $amountDiff,
                'amount_rate' => $priorPeriodAmount > 0 ? round($amountDiff / $priorPeriodAmount * 100, 1) : null,
                'order_count' => $orderCount,
                'prior_order_count' => $priorOrderCount,
                'avg_order_amount' => $orderCount > 0 ? round($periodAmount / $orderCount, 2) : 0.0,
                'unallocated_amount' => $unallocatedAmount,
                'full_prior_year_amount' => $fullPriorYearAmount,
            ],
            'monthly' => $monthly,
            'top_clients' => $this->periodClientRanking($departmentKeys, $year, 1, $lastRegisteredMonth, $year - 1),
            'categories' => $this->periodDetailBreakdown($departmentKeys, $year, 1, $lastRegisteredMonth, 'category'),
            'items' => $this->periodDetailBreakdown($departmentKeys, $year, 1, $lastRegisteredMonth, 'item_name'),
        ];
    }

    /** 品名の部分一致検索（年間、登録済みの月すべてを対象にする）。年次分析の品名検索で使用する */
    public function searchByProductNameForYear(string $departmentKey, int $year, string $keyword): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $escaped = addcslashes($keyword, '%_\\');

        return $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->where('sales_orders.product_name', 'like', "%{$escaped}%")
            ->orderByDesc('order_amount')
            ->limit(200)
            ->get(['order_number', 'client_name', 'product_name', 'order_amount', 'plate_date', 'sales_orders.sales_month as sales_month'])
            ->map(fn ($o) => [
                'order_number' => $o->order_number,
                'client_name' => $o->client_name,
                'product_name' => $o->product_name,
                'order_amount' => (float) $o->order_amount,
                'plate_date' => $o->plate_date?->format('Y-m-d'),
                'sales_month' => $o->sales_month,
            ])
            ->all();
    }

    /** @return array<int, string> 'all'は企画・制作・オンデマンドの3部署に展開する */
    private function resolveDepartmentKeys(string $departmentKey): array
    {
        return $departmentKey === 'all' ? SalesDepartments::ENABLED_KEYS : [$departmentKey];
    }

    /**
     * 指定年の1〜12月について、月ごとの受注合計・件数・未配賦額・複数取込フラグをまとめて返す
     * （$departmentKeysが複数の場合は「全部署合計」として合算する）。登録の無い月はnull。
     *
     * @param  array<int, string>  $departmentKeys
     * @return array<int, array{amount: float, order_count: int, unallocated_amount: float, needs_review: bool, has_issue: bool}|null> 1〜12のキー
     */
    private function monthlyFiguresForYear(array $departmentKeys, int $year): array
    {
        $result = array_fill(1, 12, null);

        $activeMonths = SalesActiveMonth::whereIn('department_key', $departmentKeys)
            ->where('sales_year', $year)
            ->get();

        if ($activeMonths->isEmpty()) {
            return $result;
        }

        $importIds = $activeMonths->pluck('sales_import_id')->unique();

        $orderTotals = SalesOrder::whereIn('sales_import_id', $importIds)
            ->where('sales_year', $year)
            ->selectRaw('sales_import_id, sales_month, COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount, COALESCE(SUM(unallocated_amount), 0) as total_unallocated_amount')
            ->groupBy('sales_import_id', 'sales_month')
            ->get()
            ->keyBy(fn ($r) => "{$r->sales_import_id}-{$r->sales_month}");

        foreach ($activeMonths->groupBy('sales_month') as $month => $rowsForMonth) {
            $amount = 0.0;
            $orderCount = 0;
            $unallocated = 0.0;
            $needsReview = false;

            foreach ($rowsForMonth as $row) {
                $totals = $orderTotals->get("{$row->sales_import_id}-{$month}");
                if ($totals) {
                    $amount += (float) $totals->total_amount;
                    $orderCount += (int) $totals->order_count;
                    $unallocated += (float) $totals->total_unallocated_amount;
                }
                if ($row->created_at && $row->updated_at && ! $row->created_at->equalTo($row->updated_at)) {
                    $needsReview = true;
                }
            }

            $result[(int) $month] = [
                'amount' => $amount,
                'order_count' => $orderCount,
                'unallocated_amount' => $unallocated,
                'needs_review' => $needsReview,
                'has_issue' => abs($unallocated) > 0.01,
            ];
        }

        return $result;
    }

    /**
     * 期間（$startMonth〜$endMonth）の得意先別ランキング。前年同期間との差分付き。
     *
     * @param  array<int, string>  $departmentKeys
     */
    private function periodClientRanking(array $departmentKeys, int $year, int $startMonth, int $endMonth, int $priorYear, int $limit = 10): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $current = $this->periodOrdersGroupedByClient($departmentKeys, $year, $startMonth, $endMonth);
        $prior = $this->periodOrdersGroupedByClient($departmentKeys, $priorYear, $startMonth, $endMonth);
        $total = array_sum(array_column($current, 'amount'));

        $ranking = collect($current)
            ->map(function ($row, $name) use ($prior, $total) {
                $priorAmount = $prior[$name]['amount'] ?? 0.0;
                $diff = $row['amount'] - $priorAmount;

                return [
                    'client_name' => $name,
                    'amount' => $row['amount'],
                    'share_pct' => $total > 0 ? round($row['amount'] / $total * 100, 1) : null,
                    'prior_year_amount' => $priorAmount,
                    'diff' => $diff,
                    'rate' => $priorAmount > 0 ? round($diff / $priorAmount * 100, 1) : null,
                ];
            })
            ->sortByDesc('amount')
            ->take($limit)
            ->values();

        return $ranking->all();
    }

    /**
     * @param  array<int, string>  $departmentKeys
     * @return array<string, array{amount: float}> 得意先名 => 合計（全部署合計時は得意先名で横断合算する）
     */
    private function periodOrdersGroupedByClient(array $departmentKeys, int $year, int $startMonth, int $endMonth): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        return $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->get(['client_name', 'order_amount'])
            ->groupBy(fn ($o) => $o->client_name ?? '（得意先未設定）')
            ->map(fn ($group) => ['amount' => (float) $group->sum('order_amount')])
            ->all();
    }

    /**
     * @param  array<int, string>  $departmentKeys
     */
    private function periodDetailBreakdown(array $departmentKeys, int $year, int $startMonth, int $endMonth, string $column): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $orderIds = $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->pluck('sales_orders.id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        $rows = SalesOrderDetail::whereIn('sales_order_id', $orderIds)
            ->selectRaw("{$column} as label, COALESCE(SUM(line_amount), 0) as amount, COUNT(*) as detail_count")
            ->groupBy($column)
            ->get();

        $total = (float) $rows->sum('amount');
        $unsetLabel = $this->unsetLabelFor($column);

        return $rows->map(fn ($r) => [
            'label' => $r->label ?? $unsetLabel,
            'amount' => (float) $r->amount,
            'share' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
            'detail_count' => $r->detail_count,
        ])->sortByDesc('amount')->values()->all();
    }
}
