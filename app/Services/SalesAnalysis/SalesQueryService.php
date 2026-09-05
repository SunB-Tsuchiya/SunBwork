<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesClientGroup;
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
        return $this->monthSeries($departmentKey, $endYear, $endMonth, $years * 12);
    }

    /**
     * 月次分析「月の推移グラフ」用。選択月までの直近$countヶ月（既定13ヶ月）の売上推移に、
     * 3ヶ月移動平均（`moving_avg_3m`、直近3ヶ月がすべて登録済みの場合のみ算出）を付与する
     * （REVIEW3 13.2-B、2026-09-04 Phase 12）。
     */
    public function recentMonthlyTrend(string $departmentKey, int $endYear, int $endMonth, int $count = 13): array
    {
        return $this->monthSeries($departmentKey, $endYear, $endMonth, $count);
    }

    /**
     * 月次分析「同月の複数年比較」用。選択月（$month）だけを対象に、$endYearを終点とする
     * 直近$years年分の推移を返す（REVIEW3 13.2-C、2026-09-04 Phase 12）。3年平均線・
     * 「同月3年平均との差」KPIはフロント側でこの配列から算出する（未登録年はnull）。
     */
    public function sameMonthAcrossYears(string $departmentKey, int $month, int $endYear, int $years = 5): array
    {
        $rows = [];

        for ($i = $years - 1; $i >= 0; $i--) {
            $year = $endYear - $i;
            $hasData = $this->hasActiveMonth($departmentKey, $year, $month);
            $figures = $hasData ? $this->rangeFigures([$departmentKey], $year, $month, $month) : null;

            $rows[] = [
                'year' => $year,
                'amount' => $figures['amount'] ?? null,
                'order_count' => $figures['order_count'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * 期間ナビゲーターの「未登録月」表示用。指定年月にデータがあるか、
     * 無い場合は前後で最も近い登録済み年月を返す（REVIEW3 13.1、2026-09-04 Phase 12）。
     */
    /** 期間ナビゲーターの「最新登録月」ボタン用。指定部署の最新登録年月を返す（無ければnull） */
    public function latestRegisteredMonth(string $departmentKey): ?array
    {
        $row = SalesActiveMonth::where('department_key', $departmentKey)
            ->orderByDesc('sales_year')
            ->orderByDesc('sales_month')
            ->first();

        return $row ? ['year' => (int) $row->sales_year, 'month' => (int) $row->sales_month] : null;
    }

    /**
     * 同月比較画面の期間ナビゲーター用。年を持たず1〜12月だけを巡回する画面向けに、
     * 最新登録データの「月」部分だけを返す（'all'対応、Phase 13）。
     */
    public function latestRegisteredMonthNumber(string $departmentKey): ?int
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $row = SalesActiveMonth::whereIn('department_key', $departmentKeys)
            ->orderByDesc('sales_year')
            ->orderByDesc('sales_month')
            ->first();

        return $row ? (int) $row->sales_month : null;
    }

    public function nearestRegisteredMonths(string $departmentKey, int $year, int $month): array
    {
        $target = $year * 100 + $month;

        $before = SalesActiveMonth::where('department_key', $departmentKey)
            ->selectRaw('sales_year, sales_month')
            ->whereRaw('(sales_year * 100 + sales_month) < ?', [$target])
            ->orderByRaw('(sales_year * 100 + sales_month) DESC')
            ->first();

        $after = SalesActiveMonth::where('department_key', $departmentKey)
            ->selectRaw('sales_year, sales_month')
            ->whereRaw('(sales_year * 100 + sales_month) > ?', [$target])
            ->orderByRaw('(sales_year * 100 + sales_month) ASC')
            ->first();

        return [
            'has_data' => $this->hasActiveMonth($departmentKey, $year, $month),
            'nearest_before' => $before ? ['year' => (int) $before->sales_year, 'month' => (int) $before->sales_month] : null,
            'nearest_after' => $after ? ['year' => (int) $after->sales_year, 'month' => (int) $after->sales_month] : null,
        ];
    }

    /** `monthlyTrend()`/`recentMonthlyTrend()`共通の月次系列生成（3ヶ月移動平均つき） */
    private function monthSeries(string $departmentKey, int $endYear, int $endMonth, int $count): array
    {
        [$startYear, $startMonth] = $this->shiftMonth($endYear, $endMonth, -($count - 1));

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
        for ($i = 0; $i < $count; $i++) {
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

        foreach ($months as $i => &$m) {
            $m['moving_avg_3m'] = null;
            if ($i >= 2) {
                $a = $months[$i - 2]['total_amount'];
                $b = $months[$i - 1]['total_amount'];
                $c = $months[$i]['total_amount'];
                if ($a !== null && $b !== null && $c !== null) {
                    $m['moving_avg_3m'] = round(($a + $b + $c) / 3, 2);
                }
            }
        }
        unset($m);

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

    /**
     * 月次分析「得意先比較」パネル用。mode='current'は当月金額順、'vs_previous'/'vs_previous_year'は
     * 前月・前年同月との差額（発散棒グラフ用）を返す。Top10/20表示・全件詳細ドロワーの両方が
     * この1メソッドを共用する（REVIEW3 13.2-D・15.1、2026-09-04 Phase 12）。
     */
    public function monthlyClientPanel(string $departmentKey, int $year, int $month, string $mode, bool $consolidate, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $deptKeys = [$departmentKey];
        $current = $this->monthOrdersGroupedByClient($deptKeys, $year, $month, $consolidate);

        if ($mode === 'current') {
            $rows = collect($current)
                ->map(fn ($amount, $name) => ['label' => $name, 'amount' => $amount, 'diff' => null, 'rate' => null])
                ->values()
                ->all();
        } else {
            [$otherYear, $otherMonth] = $mode === 'vs_previous'
                ? $this->shiftMonth($year, $month, -1)
                : [$year - 1, $month];
            $other = $this->monthOrdersGroupedByClient($deptKeys, $otherYear, $otherMonth, $consolidate);

            $rows = collect($this->combineSideBySideRows($other, $current))
                ->map(fn ($r) => ['label' => $r['label'], 'amount' => $r['amount_b'], 'diff' => $r['diff'], 'rate' => $r['rate']])
                ->all();
        }

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, array_sum($current));
    }

    /**
     * 月次分析「内訳（分類/項目）」パネル用。`monthlyClientPanel()`と同じTop10/20+全件詳細ドロワー契約を
     * 分類・項目でも共用する（REVIEW3 13.2-E・15.1、2026-09-04 Phase 12）。
     */
    public function monthlyBreakdownPanel(string $departmentKey, int $year, int $month, string $dimension, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $column = $dimension === 'category' ? 'category' : 'item_name';
        $map = $this->detailBreakdownMap($departmentKey, $year, $month, $column);

        $rows = collect($map)
            ->map(fn ($amount, $label) => ['label' => $label, 'amount' => $amount, 'diff' => null, 'rate' => null])
            ->values()
            ->all();

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, array_sum($map));
    }

    /** @return array<string, float> ラベル（未設定は「（分類未設定）」等）=>金額合計 */
    private function detailBreakdownMap(string $departmentKey, int $year, int $month, string $column): array
    {
        if (! $this->hasActiveMonth($departmentKey, $year, $month)) {
            return [];
        }

        $orderIds = $this->activeOrdersQuery($departmentKey)
            ->where('sales_orders.sales_year', $year)
            ->where('sales_orders.sales_month', $month)
            ->pluck('sales_orders.id');

        $unsetLabel = $this->unsetLabelFor($column);

        return SalesOrderDetail::whereIn('sales_order_id', $orderIds)
            ->selectRaw("{$column} as label, COALESCE(SUM(line_amount), 0) as amount")
            ->groupBy($column)
            ->get()
            ->reduce(function (array $carry, $r) use ($unsetLabel) {
                $label = $r->label ?? $unsetLabel;
                $carry[$label] = ($carry[$label] ?? 0.0) + (float) $r->amount;

                return $carry;
            }, []);
    }

    /**
     * 得意先/分類/項目パネル共通のキーワード絞り込み・並べ替え・ページングを行う
     * （Top10/20表示と全件詳細ドロワーが同じ形状のレスポンスを受け取れるようにする、Phase 12）。
     *
     * @param  array<int, array{label: string, amount: float, diff: ?float, rate: ?float}>  $rows
     */
    private function paginateRankingRows(array $rows, ?string $keyword, string $sort, string $direction, int $limit, int $page, float $totalAmountForShare): array
    {
        if ($keyword !== null && $keyword !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => mb_stripos($r['label'], $keyword) !== false));
        }

        $sortField = in_array($sort, ['amount', 'diff', 'rate', 'label'], true) ? $sort : 'amount';
        $sorted = collect($rows)->sortBy($sortField, SORT_REGULAR, $direction === 'desc')->values();

        $total = $sorted->count();
        $offset = max($page - 1, 0) * $limit;

        $pageRows = $sorted->slice($offset, $limit)
            ->map(fn ($r) => [
                'label' => $r['label'],
                'amount' => $r['amount'],
                'diff' => $r['diff'],
                'rate' => $r['rate'],
                'share_pct' => $totalAmountForShare > 0 ? round($r['amount'] / $totalAmountForShare * 100, 1) : null,
            ])
            ->values()
            ->all();

        return [
            'rows' => $pageRows,
            'total_count' => $total,
            'total_amount' => $totalAmountForShare,
            'page' => $page,
            'limit' => $limit,
        ];
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
     *
     * $consolidateClientsがtrueのとき、top_clientsは`sales_client_group_members`の統合後名称で
     * 集計する（2026-09-04追加。他画面の得意先統合トグルと同じ`clientDisplayNameResolver()`を使う）。
     */
    public function annualSummary(string $departmentKey, int $year, bool $consolidateClients = false): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);

        $monthlyCurrent = $this->monthlyFiguresForYear($departmentKeys, $year);
        $monthlyPrior = $this->monthlyFiguresForYear($departmentKeys, $year - 1);

        $currentYear = (int) now()->format('Y');
        $currentMonthNow = (int) now()->format('n');
        $isCurrentYear = $year === $currentYear;

        $registeredMonths = [];
        foreach ($monthlyCurrent as $m => $data) {
            if ($data !== null) {
                $registeredMonths[] = $m;
            }
        }
        $lastRegisteredMonth = $registeredMonths === [] ? 0 : max($registeredMonths);
        $missingMonths = $lastRegisteredMonth > 0
            ? array_values(array_diff(range(1, $lastRegisteredMonth), $registeredMonths))
            : [];

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
                'coverage' => $cur['coverage'] ?? null,
            ];
        }

        return [
            'department_key' => $departmentKey,
            'year' => $year,
            'is_current_year' => $isCurrentYear,
            'registered_months' => $registeredMonths,
            'missing_months' => $missingMonths,
            'months_registered' => count($registeredMonths),
            'last_registered_month' => $lastRegisteredMonth,
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
            'consolidate_clients' => $consolidateClients,
            'top_clients' => $this->periodClientRanking($departmentKeys, $year, 1, $lastRegisteredMonth, $year - 1, 10, $consolidateClients),
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

    /** 期間ナビゲーターの「最新年」ボタン用。指定部署（'all'可）の最新登録年を返す（無ければnull） */
    public function latestRegisteredYear(string $departmentKey): ?int
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $row = SalesActiveMonth::whereIn('department_key', $departmentKeys)->orderByDesc('sales_year')->first();

        return $row ? (int) $row->sales_year : null;
    }

    // ==================================================================
    // 期別分析（4月始まり・翌3月終わりの会計年度）
    // 「年次分析」（暦年）とは別画面として実機フィードバックで要望された（2026-09-04）。
    // 年またぎ集計が中心となるため、暦年版（annualSummary等）とは意図的に別メソッドとして
    // 実装し、既存の暦年ロジックには手を入れない。
    // ==================================================================

    /**
     * 期別分析のメイン集計。$fiscalYearは期の開始暦年（例: 2026なら2026年4月〜2027年3月）。
     * 構造はannualSummary()と対応させているが、キー名は`fiscal_year`/`comparison_fiscal_year`、
     * 月配列は`fiscal_month`（1=4月〜12=翌3月）＋実際の`calendar_year`/`calendar_month`を持つ。
     */
    public function fiscalYearSummary(string $departmentKey, int $fiscalYear, bool $consolidateClients = false): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);

        $monthlyCurrent = $this->fiscalMonthlyFigures($departmentKeys, $fiscalYear);
        $monthlyPrior = $this->fiscalMonthlyFigures($departmentKeys, $fiscalYear - 1);

        $now = now();
        $isCurrentFiscalYear = $now->month >= 4 ? $now->year === $fiscalYear : ($now->year - 1) === $fiscalYear;
        $currentFiscalMonthIndex = $now->month >= 4 ? $now->month - 3 : $now->month + 9;

        $registeredMonths = [];
        foreach ($monthlyCurrent as $i => $data) {
            if ($data !== null) {
                $registeredMonths[] = $i + 1;
            }
        }
        $lastRegisteredMonth = $registeredMonths === [] ? 0 : max($registeredMonths);
        $missingMonths = $lastRegisteredMonth > 0
            ? array_values(array_diff(range(1, $lastRegisteredMonth), $registeredMonths))
            : [];

        $comparisonMode = $lastRegisteredMonth >= 12 ? 'full' : 'partial';
        $comparisonRange = [1, max($lastRegisteredMonth, 1)];

        $periodAmount = 0.0;
        $priorPeriodAmount = 0.0;
        $orderCount = 0;
        $priorOrderCount = 0;
        $unallocatedAmount = 0.0;

        for ($i = 0; $i < $lastRegisteredMonth; $i++) {
            if ($monthlyCurrent[$i]) {
                $periodAmount += $monthlyCurrent[$i]['amount'];
                $orderCount += $monthlyCurrent[$i]['order_count'];
                $unallocatedAmount += $monthlyCurrent[$i]['unallocated_amount'];
            }
            if ($monthlyPrior[$i]) {
                $priorPeriodAmount += $monthlyPrior[$i]['amount'];
                $priorOrderCount += $monthlyPrior[$i]['order_count'];
            }
        }

        $fullPriorYearAmount = array_sum(array_map(fn ($d) => $d['amount'] ?? 0.0, $monthlyPrior));
        $amountDiff = $periodAmount - $priorPeriodAmount;

        $monthly = [];
        for ($i = 0; $i < 12; $i++) {
            $fiscalMonth = $i + 1;
            [$calYear, $calMonth] = $this->fiscalMonthToCalendar($fiscalYear, $fiscalMonth);
            $cur = $monthlyCurrent[$i];
            $pri = $monthlyPrior[$i];
            $isFuture = $isCurrentFiscalYear && $fiscalMonth > $currentFiscalMonthIndex;
            $state = $cur === null ? ($isFuture ? 'future' : 'no_data') : ($cur['amount'] > 0 ? 'has_sales' : 'zero');
            $diff = ($cur !== null && $pri !== null) ? $cur['amount'] - $pri['amount'] : null;
            $rate = ($diff !== null && $pri['amount'] > 0) ? round($diff / $pri['amount'] * 100, 1) : null;

            $monthly[] = [
                'fiscal_month' => $fiscalMonth,
                'calendar_year' => $calYear,
                'calendar_month' => $calMonth,
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
                'coverage' => $cur['coverage'] ?? null,
            ];
        }

        [$startCalYear, $startCalMonth] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endCalYear, $endCalMonth] = $this->fiscalMonthToCalendar($fiscalYear, max($lastRegisteredMonth, 1));

        return [
            'department_key' => $departmentKey,
            'fiscal_year' => $fiscalYear,
            'comparison_fiscal_year' => $fiscalYear - 1,
            'is_current_fiscal_year' => $isCurrentFiscalYear,
            'registered_months' => $registeredMonths,
            'missing_months' => $missingMonths,
            'months_registered' => count($registeredMonths),
            'last_registered_month' => $lastRegisteredMonth,
            'comparison_mode' => $comparisonMode,
            'comparison_month_range' => $comparisonRange,
            'period_start' => ['year' => $startCalYear, 'month' => $startCalMonth],
            'period_end' => ['year' => $endCalYear, 'month' => $endCalMonth],
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
            'consolidate_clients' => $consolidateClients,
            'top_clients' => $this->fiscalYearTopClients($departmentKeys, $fiscalYear, $lastRegisteredMonth, $consolidateClients),
            'categories' => $this->fiscalYearTopBreakdown($departmentKeys, $fiscalYear, $lastRegisteredMonth, 'category'),
            'items' => $this->fiscalYearTopBreakdown($departmentKeys, $fiscalYear, $lastRegisteredMonth, 'item_name'),
        ];
    }

    /** 期別分析の品名検索（期内全月対象） */
    public function searchByProductNameForFiscalYear(string $departmentKey, int $fiscalYear, string $keyword): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        [$startY, $startM] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endY, $endM] = $this->fiscalMonthToCalendar($fiscalYear, 12);
        $escaped = addcslashes($keyword, '%_\\');
        $startYm = $startY * 100 + $startM;
        $endYm = $endY * 100 + $endM;

        return $this->activeOrdersQuery($departmentKeys)
            ->whereRaw('(sales_orders.sales_year * 100 + sales_orders.sales_month) BETWEEN ? AND ?', [$startYm, $endYm])
            ->where('sales_orders.product_name', 'like', "%{$escaped}%")
            ->orderByDesc('order_amount')
            ->limit(200)
            ->get(['order_number', 'client_name', 'product_name', 'order_amount', 'plate_date', 'sales_orders.sales_year as sales_year', 'sales_orders.sales_month as sales_month'])
            ->map(fn ($o) => [
                'order_number' => $o->order_number,
                'client_name' => $o->client_name,
                'product_name' => $o->product_name,
                'order_amount' => (float) $o->order_amount,
                'plate_date' => $o->plate_date?->format('Y-m-d'),
                'sales_year' => $o->sales_year,
                'sales_month' => $o->sales_month,
            ])
            ->all();
    }

    /** 期別分析Excel出力「該当明細」シート用。期首(4月)〜$lastFiscalMonth目までの受注一覧 */
    public function fiscalYearOrders(string $departmentKey, int $fiscalYear, int $lastFiscalMonth): array
    {
        if ($lastFiscalMonth < 1) {
            return [];
        }

        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        [$startY, $startM] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endY, $endM] = $this->fiscalMonthToCalendar($fiscalYear, $lastFiscalMonth);
        $startYm = $startY * 100 + $startM;
        $endYm = $endY * 100 + $endM;

        return $this->activeOrdersQuery($departmentKeys)
            ->whereRaw('(sales_orders.sales_year * 100 + sales_orders.sales_month) BETWEEN ? AND ?', [$startYm, $endYm])
            ->orderBy('sales_orders.sales_year')
            ->orderBy('sales_orders.sales_month')
            ->orderBy('sales_orders.order_number')
            ->get(['sales_orders.sales_year as sales_year', 'sales_orders.sales_month as sales_month', 'order_number', 'client_name', 'product_name', 'order_amount', 'plate_date'])
            ->map(fn ($o) => [
                'sales_year' => $o->sales_year,
                'sales_month' => $o->sales_month,
                'order_number' => $o->order_number,
                'client_name' => $o->client_name,
                'product_name' => $o->product_name,
                'order_amount' => (float) $o->order_amount,
                'plate_date' => $o->plate_date?->format('Y-m-d'),
            ])
            ->all();
    }

    /** 期別分析の期間ナビゲーター「最新期」ボタン用。最新登録年月が属する期の開始暦年を返す */
    public function latestRegisteredFiscalYear(string $departmentKey): ?int
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $row = SalesActiveMonth::whereIn('department_key', $departmentKeys)
            ->orderByDesc('sales_year')
            ->orderByDesc('sales_month')
            ->first();

        if (! $row) {
            return null;
        }

        return $row->sales_month >= 4 ? (int) $row->sales_year : (int) $row->sales_year - 1;
    }

    /** 期別分析「得意先別」Top10/20+全件詳細ドロワー用（前年同期間との差額込み） */
    public function fiscalYearClientPanel(string $departmentKey, int $fiscalYear, bool $consolidate, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $lastMonth = $this->lastRegisteredFiscalMonth($departmentKeys, $fiscalYear);

        if ($lastMonth < 1) {
            return ['rows' => [], 'total_count' => 0, 'total_amount' => 0.0, 'page' => $page, 'limit' => $limit];
        }

        [$startY, $startM] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endY, $endM] = $this->fiscalMonthToCalendar($fiscalYear, $lastMonth);
        [$priorStartY, $priorStartM] = $this->fiscalMonthToCalendar($fiscalYear - 1, 1);
        [$priorEndY, $priorEndM] = $this->fiscalMonthToCalendar($fiscalYear - 1, $lastMonth);

        $current = $this->mergeClientAggregatesForRange($departmentKeys, $startY, $startM, $endY, $endM, $consolidate);
        $prior = $this->mergeClientAggregatesForRange($departmentKeys, $priorStartY, $priorStartM, $priorEndY, $priorEndM, $consolidate);

        $rows = collect($current)
            ->map(function ($data, $name) use ($prior) {
                $priorAmount = $prior[$name]['amount'] ?? 0.0;
                $diff = $data['amount'] - $priorAmount;

                return ['label' => $name, 'amount' => $data['amount'], 'diff' => $diff, 'rate' => $priorAmount > 0 ? round($diff / $priorAmount * 100, 1) : null];
            })
            ->values()
            ->all();

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, array_sum(array_column($current, 'amount')));
    }

    /** 期別分析「分類/項目」Top10/20+全件詳細ドロワー用 */
    public function fiscalYearBreakdownPanel(string $departmentKey, int $fiscalYear, string $dimension, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $lastMonth = $this->lastRegisteredFiscalMonth($departmentKeys, $fiscalYear);

        if ($lastMonth < 1) {
            return ['rows' => [], 'total_count' => 0, 'total_amount' => 0.0, 'page' => $page, 'limit' => $limit];
        }

        [$startY, $startM] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endY, $endM] = $this->fiscalMonthToCalendar($fiscalYear, $lastMonth);
        $column = $dimension === 'category' ? 'category' : 'item_name';
        $merged = $this->mergeDetailBreakdownForRange($departmentKeys, $startY, $startM, $endY, $endM, $column);

        $rows = collect($merged)->map(fn ($amount, $label) => ['label' => $label, 'amount' => $amount, 'diff' => null, 'rate' => null])->values()->all();

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, array_sum($merged));
    }

    /** 期別分析「月別売上」の複数期重ね表示用（$endFiscalYearを終点に$years期分） */
    public function multiYearFiscalMonthlySeries(string $departmentKey, int $endFiscalYear, int $years): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $series = [];

        for ($i = $years - 1; $i >= 0; $i--) {
            $fiscalYear = $endFiscalYear - $i;
            $monthly = $this->fiscalMonthlyFigures($departmentKeys, $fiscalYear);

            $series[] = [
                'fiscal_year' => $fiscalYear,
                'months' => array_map(fn ($d) => $d['amount'] ?? null, $monthly),
            ];
        }

        return $series;
    }

    /**
     * 期（4月始まり）の1〜12月目（fiscal month）を、0始まりの配列でmonthlyFiguresForYear()相当の
     * データとして返す。index 0 = 4月 … index 11 = 翌3月。
     *
     * @param  array<int, string>  $departmentKeys
     */
    private function fiscalMonthlyFigures(array $departmentKeys, int $fiscalYear): array
    {
        $firstHalf = $this->monthlyFiguresForYear($departmentKeys, $fiscalYear); // 1〜12月キー、4〜12月を使う
        $secondHalf = $this->monthlyFiguresForYear($departmentKeys, $fiscalYear + 1); // 1〜3月を使う

        $result = [];
        foreach (range(4, 12) as $m) {
            $result[] = $firstHalf[$m];
        }
        foreach (range(1, 3) as $m) {
            $result[] = $secondHalf[$m];
        }

        return $result;
    }

    /** 期（$fiscalYear年4月始まり）のfiscal month（1〜12）を実際の暦年・暦月へ変換する */
    private function fiscalMonthToCalendar(int $fiscalYear, int $fiscalMonth): array
    {
        if ($fiscalMonth <= 9) {
            return [$fiscalYear, $fiscalMonth + 3];
        }

        return [$fiscalYear + 1, $fiscalMonth - 9];
    }

    private function lastRegisteredFiscalMonth(array $departmentKeys, int $fiscalYear): int
    {
        $monthly = $this->fiscalMonthlyFigures($departmentKeys, $fiscalYear);
        $last = 0;
        foreach ($monthly as $i => $data) {
            if ($data !== null) {
                $last = max($last, $i + 1);
            }
        }

        return $last;
    }

    /** 得意先別Top10（前年同期間との差額込み）。annualSummary()内のtop_clients生成ロジックの期別版 */
    private function fiscalYearTopClients(array $departmentKeys, int $fiscalYear, int $lastRegisteredMonth, bool $consolidate): array
    {
        if ($lastRegisteredMonth < 1) {
            return [];
        }

        [$startY, $startM] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endY, $endM] = $this->fiscalMonthToCalendar($fiscalYear, $lastRegisteredMonth);
        [$priorStartY, $priorStartM] = $this->fiscalMonthToCalendar($fiscalYear - 1, 1);
        [$priorEndY, $priorEndM] = $this->fiscalMonthToCalendar($fiscalYear - 1, $lastRegisteredMonth);

        $current = $this->mergeClientAggregatesForRange($departmentKeys, $startY, $startM, $endY, $endM, $consolidate);
        $prior = $this->mergeClientAggregatesForRange($departmentKeys, $priorStartY, $priorStartM, $priorEndY, $priorEndM, $consolidate);
        $total = array_sum(array_column($current, 'amount'));

        return collect($current)
            ->map(function ($data, $name) use ($prior, $total) {
                $priorAmount = $prior[$name]['amount'] ?? 0.0;
                $diff = $data['amount'] - $priorAmount;

                return [
                    'client_name' => $name,
                    'amount' => $data['amount'],
                    'share_pct' => $total > 0 ? round($data['amount'] / $total * 100, 1) : null,
                    'prior_year_amount' => $priorAmount,
                    'diff' => $diff,
                    'rate' => $priorAmount > 0 ? round($diff / $priorAmount * 100, 1) : null,
                ];
            })
            ->sortByDesc('amount')
            ->take(10)
            ->values()
            ->all();
    }

    /** 分類/項目別Top10。annualSummary()内のcategories/items生成ロジックの期別版 */
    private function fiscalYearTopBreakdown(array $departmentKeys, int $fiscalYear, int $lastRegisteredMonth, string $column): array
    {
        if ($lastRegisteredMonth < 1) {
            return [];
        }

        [$startY, $startM] = $this->fiscalMonthToCalendar($fiscalYear, 1);
        [$endY, $endM] = $this->fiscalMonthToCalendar($fiscalYear, $lastRegisteredMonth);

        $merged = $this->mergeDetailBreakdownForRange($departmentKeys, $startY, $startM, $endY, $endM, $column);
        $total = array_sum($merged);

        return collect($merged)
            ->map(fn ($amount, $label) => ['label' => $label, 'amount' => $amount, 'share' => $total > 0 ? round($amount / $total * 100, 1) : null])
            ->sortByDesc('amount')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * 年またぎの分類/項目内訳を合算する（期別分析専用。periodDetailBreakdown()は単一年のみ対応のため）。
     *
     * @return array<string, float> ラベル=>金額合計
     */
    private function mergeDetailBreakdownForRange(array $departmentKeys, int $startYear, int $startMonth, int $endYear, int $endMonth, string $column): array
    {
        $merged = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $rangeStart = $year === $startYear ? $startMonth : 1;
            $rangeEnd = $year === $endYear ? $endMonth : 12;

            if ($rangeEnd < $rangeStart) {
                continue;
            }

            foreach ($this->periodDetailBreakdown($departmentKeys, $year, $rangeStart, $rangeEnd, $column) as $row) {
                $merged[$row['label']] = ($merged[$row['label']] ?? 0.0) + $row['amount'];
            }
        }

        return $merged;
    }

    /**
     * 年次分析「月別売上」グラフ用。$endYearを終点とする直近$years年分の月別金額を返す
     * （未登録・将来月はnull。1〜12月の固定軸で複数年を重ねて表示する用途、
     * 実機フィードバック対応: 対前年の2年比較だけでなく3年/5年でも見たい、2026-09-04）。
     *
     * @return array<int, array{year: int, months: array<int, float|null>}> 古い年→新しい年の順
     */
    public function multiYearMonthlySeries(string $departmentKey, int $endYear, int $years): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $series = [];

        for ($i = $years - 1; $i >= 0; $i--) {
            $year = $endYear - $i;
            $monthly = $this->monthlyFiguresForYear($departmentKeys, $year);

            $series[] = [
                'year' => $year,
                'months' => array_map(fn ($m) => $monthly[$m]['amount'] ?? null, range(1, 12)),
            ];
        }

        return $series;
    }

    /**
     * 年次分析「得意先比較」パネル用。`monthlyClientPanel()`と同じTop10/20+全件詳細ドロワー契約。
     * 対象月範囲は`annualSummary()`と同じく「1月〜その年の最終登録月」（欠落があってもそのまま合算、
     * Phase 11の確定方針を踏襲）。診断済みの前年同期間との差額（`diff`/`rate`）を常に返す
     * （REVIEW3 14章Priority A「得意先別の増減寄与」、2026-09-04 Phase 13）。
     */
    public function annualClientPanel(string $departmentKey, int $year, bool $consolidate, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $lastMonth = $this->lastRegisteredMonthForYear($departmentKeys, $year);

        if ($lastMonth < 1) {
            return ['rows' => [], 'total_count' => 0, 'total_amount' => 0.0, 'page' => $page, 'limit' => $limit];
        }

        $current = $this->periodOrdersGroupedByClient($departmentKeys, $year, 1, $lastMonth, $consolidate);
        $prior = $this->periodOrdersGroupedByClient($departmentKeys, $year - 1, 1, $lastMonth, $consolidate);

        $rows = collect($current)
            ->map(function ($data, $name) use ($prior) {
                $priorAmount = $prior[$name]['amount'] ?? 0.0;
                $diff = $data['amount'] - $priorAmount;

                return [
                    'label' => $name,
                    'amount' => $data['amount'],
                    'diff' => $diff,
                    'rate' => $priorAmount > 0 ? round($diff / $priorAmount * 100, 1) : null,
                ];
            })
            ->values()
            ->all();

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, array_sum(array_column($current, 'amount')));
    }

    /** 年次分析「内訳（分類/項目）」パネル用。`monthlyBreakdownPanel()`と同じ契約（Phase 13） */
    public function annualBreakdownPanel(string $departmentKey, int $year, string $dimension, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $lastMonth = $this->lastRegisteredMonthForYear($departmentKeys, $year);

        if ($lastMonth < 1) {
            return ['rows' => [], 'total_count' => 0, 'total_amount' => 0.0, 'page' => $page, 'limit' => $limit];
        }

        $column = $dimension === 'category' ? 'category' : 'item_name';
        $breakdown = $this->periodDetailBreakdown($departmentKeys, $year, 1, $lastMonth, $column);

        $rows = collect($breakdown)
            ->map(fn ($r) => ['label' => $r['label'], 'amount' => $r['amount'], 'diff' => null, 'rate' => null])
            ->all();

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, array_sum(array_column($breakdown, 'amount')));
    }

    /** @param  array<int, string>  $departmentKeys */
    private function lastRegisteredMonthForYear(array $departmentKeys, int $year): int
    {
        $monthly = $this->monthlyFiguresForYear($departmentKeys, $year);
        $last = 0;
        foreach ($monthly as $m => $data) {
            if ($data !== null) {
                $last = max($last, $m);
            }
        }

        return $last;
    }

    /** @return array<int, string> 'all'は企画・制作・オンデマンドの3部署に展開する */
    private function resolveDepartmentKeys(string $departmentKey): array
    {
        return $departmentKey === 'all' ? SalesDepartments::ENABLED_KEYS : [$departmentKey];
    }

    /**
     * 指定年の1〜12月について、月ごとの受注合計・件数・未配賦額・複数取込フラグをまとめて返す
     * （$departmentKeysが複数の場合は「全部署合計」として合算する）。登録の無い月はnull。
     * `coverage`には、その月に実際にデータが登録されていた部署（registered_departments）と、
     * 期待される部署一覧（expected_departments = $departmentKeys）を返す。複数部署のうち
     * 一部だけ登録されている月でも合算した金額を返すため（REVIEW3 11.2節High-3、2026-09-04）、
     * 呼び出し側は`coverage`を見て「一部登録」であることを利用者へ明示する必要がある。
     *
     * @param  array<int, string>  $departmentKeys
     * @return array<int, array{amount: float, order_count: int, unallocated_amount: float, needs_review: bool, has_issue: bool, coverage: array{registered_departments: array<int, string>, expected_departments: array<int, string>, is_complete: bool}}|null> 1〜12のキー
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
            $registeredDepartments = [];

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
                $registeredDepartments[$row->department_key] = true;
            }

            $registeredDepartments = array_values(array_intersect($departmentKeys, array_keys($registeredDepartments)));

            $result[(int) $month] = [
                'amount' => $amount,
                'order_count' => $orderCount,
                'unallocated_amount' => $unallocated,
                'needs_review' => $needsReview,
                'has_issue' => abs($unallocated) > 0.01,
                'coverage' => [
                    'registered_departments' => $registeredDepartments,
                    'expected_departments' => $departmentKeys,
                    'is_complete' => count($registeredDepartments) === count($departmentKeys),
                ],
            ];
        }

        return $result;
    }

    /**
     * 期間（$startMonth〜$endMonth）の得意先別ランキング。前年同期間との差分付き。
     *
     * @param  array<int, string>  $departmentKeys
     */
    private function periodClientRanking(array $departmentKeys, int $year, int $startMonth, int $endMonth, int $priorYear, int $limit = 10, bool $consolidate = false): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $current = $this->periodOrdersGroupedByClient($departmentKeys, $year, $startMonth, $endMonth, $consolidate);
        $prior = $this->periodOrdersGroupedByClient($departmentKeys, $priorYear, $startMonth, $endMonth, $consolidate);
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
    private function periodOrdersGroupedByClient(array $departmentKeys, int $year, int $startMonth, int $endMonth, bool $consolidate = false): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $resolveName = $consolidate ? $this->clientDisplayNameResolver() : fn (string $name) => $name;

        return $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->get(['client_name', 'order_amount'])
            ->groupBy(fn ($o) => $o->client_name === null ? '（得意先未設定）' : $resolveName($o->client_name))
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

    /**
     * 同月比較画面用の集計。対象月（例: 9月）について、今年を終点に$yearsRequested（5または10）年分を
     * 横並びで比較する（2026-09-04 PLAN1.md 6D節）。$departmentKeyに'all'を渡すと3部署合算になる。
     * 得意先軸は「表示年のうち最新の登録年」対「その前年」の2年間で新規/離脱・増減額上位を判定する。
     * 分類・項目は年次マトリクスにせず、基準年（最新登録年）に対し1・3・5年前の3点だけ比較する
     * （ユーザー確認: 「去年だけよかった時などあるので、できれば1・3・5年比較があるとよい」）。
     */
    public function sameMonthComparison(string $departmentKey, int $month, int $yearsRequested = 5, bool $consolidateClients = false): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $currentYear = (int) now()->format('Y');
        $currentMonthNow = (int) now()->format('n');

        $years = range($currentYear - $yearsRequested + 1, $currentYear);
        // 表示年のうち最も古い年の「前年差」も出せるよう、1年分多く figures を取得する
        $yearsForFigures = $years;
        array_unshift($yearsForFigures, $years[0] - 1);

        $figuresByYear = [];
        foreach ($yearsForFigures as $y) {
            $figuresByYear[$y] = $this->monthFigures($departmentKeys, $y, $month);
        }

        $latestRegisteredYear = null;
        foreach (array_reverse($years) as $y) {
            if ($figuresByYear[$y] !== null) {
                $latestRegisteredYear = $y;
                break;
            }
        }

        $yearly = array_map(function ($year) use ($figuresByYear, $currentYear, $currentMonthNow, $month) {
            $cur = $figuresByYear[$year];
            $pri = $figuresByYear[$year - 1] ?? null;
            $isFuture = $year === $currentYear && $month > $currentMonthNow;
            $state = $cur === null ? ($isFuture ? 'future' : 'no_data') : ($cur['amount'] > 0 ? 'has_sales' : 'zero');
            $diff = ($cur !== null && $pri !== null) ? $cur['amount'] - $pri['amount'] : null;
            $rate = ($diff !== null && $pri['amount'] > 0) ? round($diff / $pri['amount'] * 100, 1) : null;

            return [
                'year' => $year,
                'state' => $state,
                'amount' => $cur['amount'] ?? null,
                'order_count' => $cur['order_count'] ?? null,
                'avg_order_amount' => $cur !== null
                    ? ($cur['order_count'] > 0 ? round($cur['amount'] / $cur['order_count'], 2) : 0.0)
                    : null,
                'prior_year_diff' => $diff,
                'prior_year_rate' => $rate,
                'needs_review' => $cur['needs_review'] ?? false,
                'has_issue' => $cur['has_issue'] ?? false,
                'issue_amount' => ($cur !== null && $cur['has_issue']) ? $cur['unallocated_amount'] : null,
            ];
        }, $years);

        $clientsByYear = [];
        foreach ($yearsForFigures as $y) {
            $clientsByYear[$y] = $this->monthOrdersGroupedByClient($departmentKeys, $y, $month, $consolidateClients);
        }

        $clientComparison = $this->buildSameMonthClientComparison($years, $clientsByYear, $figuresByYear, $latestRegisteredYear);

        return [
            'department_key' => $departmentKey,
            'month' => $month,
            'years_requested' => $yearsRequested,
            'years' => $years,
            'consolidate_clients' => $consolidateClients,
            'yearly' => $yearly,
            'client_matrix' => $clientComparison['client_matrix'],
            'new_clients' => $clientComparison['new_clients'],
            'departed_clients' => $clientComparison['departed_clients'],
            'top_increase' => $clientComparison['top_increase'],
            'top_decrease' => $clientComparison['top_decrease'],
            'category_item_comparison' => [
                'reference_year' => $latestRegisteredYear,
                'compare_offsets' => [1, 3, 5],
                'categories' => $latestRegisteredYear !== null
                    ? $this->monthOffsetComparison($departmentKeys, $latestRegisteredYear, $month, 'category')
                    : [],
                'items' => $latestRegisteredYear !== null
                    ? $this->monthOffsetComparison($departmentKeys, $latestRegisteredYear, $month, 'item_name')
                    : [],
            ],
        ];
    }

    /**
     * 指定部署群・単一年月の合計金額・受注件数・未配賦額・複数取込フラグ。未登録月はnull。
     *
     * @param  array<int, string>  $departmentKeys
     */
    private function monthFigures(array $departmentKeys, int $year, int $month): ?array
    {
        return $this->rangeFigures($departmentKeys, $year, $month, $month);
    }

    /**
     * 指定部署群・年内の月範囲（$startMonth〜$endMonth）の合計金額・受注件数・未配賦額・複数取込フラグ。
     * 範囲内に登録済みの月が一つも無ければnull。範囲内の非登録月はactiveOrdersQueryのjoinで
     * 自動的に除外されるため、月が飛び飛び（gapあり）でも安全に合算できる。
     *
     * @param  array<int, string>  $departmentKeys
     */
    private function rangeFigures(array $departmentKeys, int $year, int $startMonth, int $endMonth): ?array
    {
        $activeRows = SalesActiveMonth::whereIn('department_key', $departmentKeys)
            ->where('sales_year', $year)
            ->whereBetween('sales_month', [$startMonth, $endMonth])
            ->get();

        if ($activeRows->isEmpty()) {
            return null;
        }

        $row = $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount, COALESCE(SUM(unallocated_amount), 0) as total_unallocated_amount')
            ->first();

        $unallocated = (float) $row->total_unallocated_amount;

        return [
            'amount' => (float) $row->total_amount,
            'order_count' => (int) $row->order_count,
            'unallocated_amount' => $unallocated,
            'needs_review' => $activeRows->contains(
                fn ($m) => $m->created_at && $m->updated_at && ! $m->created_at->equalTo($m->updated_at)
            ),
            'has_issue' => abs($unallocated) > 0.01,
        ];
    }

    /**
     * 指定部署群・単一年月の得意先別金額合計。$consolidateがtrueなら会社統合後の名称で集計する。
     *
     * @param  array<int, string>  $departmentKeys
     * @return array<string, float> 得意先名（未設定は「（得意先未設定）」）=>金額合計
     */
    private function monthOrdersGroupedByClient(array $departmentKeys, int $year, int $month, bool $consolidate): array
    {
        return $this->rangeOrdersGroupedByClient($departmentKeys, $year, $month, $month, $consolidate);
    }

    /**
     * 指定部署群・年内の月範囲（$startMonth〜$endMonth）の得意先別金額合計。
     * $consolidateがtrueなら会社統合後の名称で集計する（同月比較・左右比較で共用）。
     *
     * @param  array<int, string>  $departmentKeys
     * @return array<string, float> 得意先名（未設定は「（得意先未設定）」）=>金額合計
     */
    private function rangeOrdersGroupedByClient(array $departmentKeys, int $year, int $startMonth, int $endMonth, bool $consolidate): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $resolveName = $consolidate ? $this->clientDisplayNameResolver() : fn (string $name) => $name;

        return $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->get(['client_name', 'order_amount'])
            ->groupBy(fn ($o) => $o->client_name === null ? '（得意先未設定）' : $resolveName($o->client_name))
            ->map(fn ($group) => (float) $group->sum('order_amount'))
            ->all();
    }

    /**
     * 得意先軸の同月比較（年次マトリクス・新規/離脱・増減額上位）を組み立てる。
     * 新規/離脱/増減額上位は「表示年のうち最新の登録年」対「その前年」の2年間だけで判定する
     * （3年以上のペアワイズ比較は行わない）。
     *
     * @param  array<int, int>  $years
     * @param  array<int, array<string, float>>  $clientsByYear
     * @param  array<int, array|null>  $figuresByYear  年が未登録かどうかの判定に使う（nullなら未登録）
     */
    private function buildSameMonthClientComparison(array $years, array $clientsByYear, array $figuresByYear, ?int $latestRegisteredYear): array
    {
        $priorYear = $latestRegisteredYear !== null ? $latestRegisteredYear - 1 : null;

        $allClientNames = collect($years)
            ->flatMap(fn ($y) => array_keys($clientsByYear[$y]))
            ->unique()
            ->values();

        $matrixRows = $allClientNames->map(function ($name) use ($years, $clientsByYear, $figuresByYear, $latestRegisteredYear, $priorYear) {
            $amounts = [];
            foreach ($years as $y) {
                // その年自体が未登録（no_data/future）ならnull、登録済みだが当該得意先の受注が無ければ0円
                $amounts[(string) $y] = $figuresByYear[$y] === null ? null : ($clientsByYear[$y][$name] ?? 0.0);
            }

            $latestAmount = $latestRegisteredYear !== null ? ($clientsByYear[$latestRegisteredYear][$name] ?? 0.0) : null;
            $priorAmount = $priorYear !== null ? ($clientsByYear[$priorYear][$name] ?? 0.0) : null;
            $diff = ($latestAmount !== null && $priorAmount !== null) ? $latestAmount - $priorAmount : null;
            $rate = ($diff !== null && $priorAmount > 0) ? round($diff / $priorAmount * 100, 1) : null;

            return [
                'client_name' => $name,
                'amounts' => $amounts,
                'latest_amount' => $latestAmount,
                'prior_year_amount' => $priorAmount,
                'diff' => $diff,
                'rate' => $rate,
            ];
        })->sortByDesc(fn ($r) => $r['latest_amount'] ?? -INF)->values();

        $topRows = $matrixRows->take(15);
        $othersAmount = $matrixRows->slice(15)->sum(fn ($r) => $r['latest_amount'] ?? 0.0);

        $hasComparisonPair = $latestRegisteredYear !== null && $priorYear !== null;

        $newClients = $hasComparisonPair
            ? $matrixRows->filter(fn ($r) => ($r['latest_amount'] ?? 0.0) > 0 && ($r['prior_year_amount'] ?? 0.0) == 0.0)
                ->sortByDesc('latest_amount')
                ->take(10)
                ->map(fn ($r) => ['client_name' => $r['client_name'], 'amount' => $r['latest_amount']])
                ->values()->all()
            : [];

        $departedClients = $hasComparisonPair
            ? $matrixRows->filter(fn ($r) => ($r['latest_amount'] ?? 0.0) == 0.0 && ($r['prior_year_amount'] ?? 0.0) > 0)
                ->sortByDesc('prior_year_amount')
                ->take(10)
                ->map(fn ($r) => ['client_name' => $r['client_name'], 'prior_year_amount' => $r['prior_year_amount']])
                ->values()->all()
            : [];

        $comparable = $matrixRows->filter(fn ($r) => $r['diff'] !== null);

        $topIncrease = $comparable->sortByDesc('diff')->take(10)
            ->map(fn ($r) => [
                'client_name' => $r['client_name'],
                'diff' => $r['diff'],
                'rate' => $r['rate'],
                'current_amount' => $r['latest_amount'],
                'prior_year_amount' => $r['prior_year_amount'],
            ])->values()->all();

        $topDecrease = $comparable->sortBy('diff')->take(10)
            ->map(fn ($r) => [
                'client_name' => $r['client_name'],
                'diff' => $r['diff'],
                'rate' => $r['rate'],
                'current_amount' => $r['latest_amount'],
                'prior_year_amount' => $r['prior_year_amount'],
            ])->values()->all();

        return [
            'client_matrix' => [
                'years' => $years,
                'clients' => $topRows->all(),
                'others_amount' => $othersAmount,
            ],
            'new_clients' => $newClients,
            'departed_clients' => $departedClients,
            'top_increase' => $topIncrease,
            'top_decrease' => $topDecrease,
        ];
    }

    /**
     * 分類・項目の「基準年（直近登録年）に対し1・3・5年前」比較。年次マトリクスは作らない。
     * 比較年が未登録の場合は amount/diff/rate を null にする（0円と誤表示しない）。
     *
     * @param  array<int, string>  $departmentKeys
     */
    private function monthOffsetComparison(array $departmentKeys, int $referenceYear, int $month, string $column): array
    {
        $reference = collect($this->periodDetailBreakdown($departmentKeys, $referenceYear, $month, $month, $column))
            ->keyBy('label');

        $offsets = [1, 3, 5];
        $compareBreakdowns = [];
        foreach ($offsets as $offset) {
            $compareYear = $referenceYear - $offset;
            $registered = SalesActiveMonth::whereIn('department_key', $departmentKeys)
                ->where('sales_year', $compareYear)
                ->where('sales_month', $month)
                ->exists();

            $compareBreakdowns[$offset] = $registered
                ? collect($this->periodDetailBreakdown($departmentKeys, $compareYear, $month, $month, $column))->keyBy('label')
                : null;
        }

        return $reference->map(function ($row, $label) use ($offsets, $compareBreakdowns, $referenceYear) {
            $comparisons = array_map(function ($offset) use ($compareBreakdowns, $label, $row, $referenceYear) {
                $breakdown = $compareBreakdowns[$offset];
                $compareAmount = $breakdown === null ? null : (float) ($breakdown->get($label)['amount'] ?? 0.0);
                $diff = $compareAmount !== null ? $row['amount'] - $compareAmount : null;
                $rate = ($diff !== null && $compareAmount > 0) ? round($diff / $compareAmount * 100, 1) : null;

                return [
                    'years_ago' => $offset,
                    'compare_year' => $referenceYear - $offset,
                    'amount' => $compareAmount,
                    'diff' => $diff,
                    'rate' => $rate,
                ];
            }, $offsets);

            return [
                'label' => $label,
                'amount' => $row['amount'],
                'comparisons' => $comparisons,
            ];
        })->sortByDesc('amount')->values()->all();
    }

    /**
     * 左右比較画面用の集計。任意の期間A/B（年 または 年月）を選び、差額・増減率を比較する
     * （2026-09-04 PLAN1.md 6E節）。$periodA/$periodBは['type'=>'year','year'=>int]または
     * ['type'=>'month','year'=>int,'month'=>int]の形。$departmentKeyに'all'を渡すと3部署合算になる。
     * AとBの登録済み期間の長さが異なっていても揃えず、それぞれの実績をそのまま合算する
     * （ユーザー確認: 「揃えず、それぞれの実績をそのまま出す」）。
     */
    public function sideBySideComparison(string $departmentKey, array $periodA, array $periodB, bool $consolidateClients = false): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);

        $a = $this->resolveSideBySidePeriod($departmentKeys, $periodA);
        $b = $this->resolveSideBySidePeriod($departmentKeys, $periodB);

        $amountA = $a['figures']['amount'];
        $amountB = $b['figures']['amount'];
        $orderCountA = $a['figures']['order_count'];
        $orderCountB = $b['figures']['order_count'];
        $avgA = $a['figures']['avg_order_amount'];
        $avgB = $b['figures']['avg_order_amount'];

        $diffAmount = ($amountA !== null && $amountB !== null) ? $amountB - $amountA : null;
        $diffRate = ($diffAmount !== null && $amountA > 0) ? round($diffAmount / $amountA * 100, 1) : null;

        $clientsA = $a['range'] !== null ? $this->rangeOrdersGroupedByClient($departmentKeys, $a['figures']['year'], $a['range'][0], $a['range'][1], $consolidateClients) : [];
        $clientsB = $b['range'] !== null ? $this->rangeOrdersGroupedByClient($departmentKeys, $b['figures']['year'], $b['range'][0], $b['range'][1], $consolidateClients) : [];
        $clientRows = collect($this->combineSideBySideRows($clientsA, $clientsB))
            ->map(fn ($row) => ['client_name' => $row['label'], ...array_diff_key($row, ['label' => null])])
            ->all();

        $topClientRows = collect($clientRows)->take(15);
        $othersA = collect($clientRows)->slice(15)->sum('amount_a');
        $othersB = collect($clientRows)->slice(15)->sum('amount_b');

        $categoriesA = $a['range'] !== null ? collect($this->periodDetailBreakdown($departmentKeys, $a['figures']['year'], $a['range'][0], $a['range'][1], 'category'))->pluck('amount', 'label')->all() : [];
        $categoriesB = $b['range'] !== null ? collect($this->periodDetailBreakdown($departmentKeys, $b['figures']['year'], $b['range'][0], $b['range'][1], 'category'))->pluck('amount', 'label')->all() : [];

        $itemsA = $a['range'] !== null ? collect($this->periodDetailBreakdown($departmentKeys, $a['figures']['year'], $a['range'][0], $a['range'][1], 'item_name'))->pluck('amount', 'label')->all() : [];
        $itemsB = $b['range'] !== null ? collect($this->periodDetailBreakdown($departmentKeys, $b['figures']['year'], $b['range'][0], $b['range'][1], 'item_name'))->pluck('amount', 'label')->all() : [];

        return [
            'department_key' => $departmentKey,
            'consolidate_clients' => $consolidateClients,
            'period_a' => $a['figures'],
            'period_b' => $b['figures'],
            'diff' => [
                'amount' => $diffAmount,
                'rate' => $diffRate,
                'order_count' => ($orderCountA !== null && $orderCountB !== null) ? $orderCountB - $orderCountA : null,
                'avg_order_amount' => ($avgA !== null && $avgB !== null) ? round($avgB - $avgA, 2) : null,
            ],
            'clients' => [
                'rows' => $topClientRows->values()->all(),
                'others_amount_a' => (float) $othersA,
                'others_amount_b' => (float) $othersB,
                'all_count' => count($clientRows),
            ],
            'categories' => $this->combineSideBySideRows($categoriesA, $categoriesB),
            'items' => $this->combineSideBySideRows($itemsA, $itemsB),
        ];
    }

    /**
     * 左右比較の期間A/Bを解決する。年型は「その年のうち登録済みの月」だけを合算し
     * （0埋めしない。annualSummaryと同じ考え方）、AとBの期間長は揃えない。
     * 一件も登録が無い期間はamount等をすべてnullにする。
     *
     * @param  array<int, string>  $departmentKeys
     * @param  array{type: string, year: int, month?: int}  $period
     * @return array{figures: array, range: array{0: int, 1: int}|null}
     */
    private function resolveSideBySidePeriod(array $departmentKeys, array $period): array
    {
        $type = $period['type'];
        $year = (int) $period['year'];

        if ($type === 'month') {
            $month = (int) $period['month'];
            $figures = $this->monthFigures($departmentKeys, $year, $month);
            $registeredMonthCount = $figures !== null ? 1 : 0;
            $totalMonthCount = 1;
            $range = $figures !== null ? [$month, $month] : null;
            $label = "{$year}年{$month}月";
        } else {
            $month = null;
            $registeredMonths = SalesActiveMonth::whereIn('department_key', $departmentKeys)
                ->where('sales_year', $year)
                ->pluck('sales_month')
                ->unique();

            $registeredMonthCount = $registeredMonths->count();
            $totalMonthCount = 12;

            if ($registeredMonthCount === 0) {
                $figures = null;
                $range = null;
            } else {
                $range = [1, (int) $registeredMonths->max()];
                $figures = $this->rangeFigures($departmentKeys, $year, $range[0], $range[1]);
            }
            $label = "{$year}年";
        }

        return [
            'figures' => [
                'type' => $type,
                'year' => $year,
                'month' => $month,
                'label' => $label,
                'amount' => $figures['amount'] ?? null,
                'order_count' => $figures['order_count'] ?? null,
                'avg_order_amount' => $figures !== null
                    ? ($figures['order_count'] > 0 ? round($figures['amount'] / $figures['order_count'], 2) : 0.0)
                    : null,
                'registered_month_count' => $registeredMonthCount,
                'total_month_count' => $totalMonthCount,
                'unallocated_amount' => $figures['unallocated_amount'] ?? null,
                'needs_review' => $figures['needs_review'] ?? false,
                'has_issue' => $figures['has_issue'] ?? false,
            ],
            'range' => $range,
        ];
    }

    /**
     * 2つの得意先名/ラベル=>金額のマップを突き合わせ、片方にしか無い名称も0円で残す。
     * Bの金額降順で返す（左右比較画面の既定ソート順）。
     *
     * @param  array<string, float>  $mapA
     * @param  array<string, float>  $mapB
     */
    private function combineSideBySideRows(array $mapA, array $mapB): array
    {
        $names = collect(array_keys($mapA))->merge(array_keys($mapB))->unique()->values();

        return $names->map(function ($name) use ($mapA, $mapB) {
            $amountA = (float) ($mapA[$name] ?? 0.0);
            $amountB = (float) ($mapB[$name] ?? 0.0);
            $diff = $amountB - $amountA;

            return [
                'label' => $name,
                'amount_a' => $amountA,
                'amount_b' => $amountB,
                'diff' => $diff,
                'rate' => $amountA > 0 ? round($diff / $amountA * 100, 1) : null,
            ];
        })->sortByDesc('amount_b')->values()->all();
    }

    /**
     * 得意先分析画面用。任意の期間（年月〜年月、複数年またぎ可）の得意先別ランキング。
     * $keywordを指定すると部分一致で絞り込むが、share_pctの分母は絞り込み前の期間内合計のまま固定する
     * （2026-09-04 PLAN1.md「Phase 7-0」）。
     */
    public function clientRankingForPeriod(string $departmentKey, int $startYear, int $startMonth, int $endYear, int $endMonth, bool $consolidateClients, ?string $keyword = null): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $merged = $this->mergeClientAggregatesForRange($departmentKeys, $startYear, $startMonth, $endYear, $endMonth, $consolidateClients);
        $total = array_sum(array_column($merged, 'amount'));

        if ($keyword !== null && $keyword !== '') {
            $merged = array_filter($merged, fn ($name) => mb_stripos($name, $keyword) !== false, ARRAY_FILTER_USE_KEY);
        }

        $ranking = collect($merged)
            ->map(fn ($data, $name) => [
                'client_name' => $name,
                'amount' => $data['amount'],
                'share_pct' => $total > 0 ? round($data['amount'] / $total * 100, 1) : null,
                'order_count' => $data['order_count'],
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'total_amount' => $total,
            'ranking' => $ranking->all(),
        ];
    }

    /**
     * 得意先分析画面用。`clientRankingForPeriod()`と同じ期間集計だが、月次/年次と同じ
     * Top10/20+全件詳細ドロワー契約（`{rows,total_count,total_amount,page,limit}`）で返す
     * （REVIEW3 14章Priority A「Top10/20＋その他＋詳細」、2026-09-04 Phase 15）。
     */
    public function clientAnalysisPanel(string $departmentKey, int $startYear, int $startMonth, int $endYear, int $endMonth, bool $consolidateClients, ?string $keyword, string $sort, string $direction, int $limit, int $page): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $merged = $this->mergeClientAggregatesForRange($departmentKeys, $startYear, $startMonth, $endYear, $endMonth, $consolidateClients);
        $total = array_sum(array_column($merged, 'amount'));

        $rows = collect($merged)
            ->map(fn ($data, $name) => ['label' => $name, 'amount' => $data['amount'], 'diff' => null, 'rate' => null])
            ->values()
            ->all();

        return $this->paginateRankingRows($rows, $keyword, $sort, $direction, $limit, $page, $total);
    }

    /**
     * @param  array<int, string>  $departmentKeys
     * @return array<string, array{amount: float, order_count: int}>
     */
    private function mergeClientAggregatesForRange(array $departmentKeys, int $startYear, int $startMonth, int $endYear, int $endMonth, bool $consolidate): array
    {
        $merged = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $rangeStart = $year === $startYear ? $startMonth : 1;
            $rangeEnd = $year === $endYear ? $endMonth : 12;

            if ($rangeEnd < $rangeStart) {
                continue;
            }

            foreach ($this->rangeClientAggregates($departmentKeys, $year, $rangeStart, $rangeEnd, $consolidate) as $name => $data) {
                $merged[$name] ??= ['amount' => 0.0, 'order_count' => 0];
                $merged[$name]['amount'] += $data['amount'];
                $merged[$name]['order_count'] += $data['order_count'];
            }
        }

        return $merged;
    }

    /**
     * 指定部署群・年内の月範囲の得意先別 金額合計・受注件数（得意先分析専用。同月比較/左右比較の
     * rangeOrdersGroupedByClient()は金額のみを返す簡易版のため、既存呼び出し元の形を変えずに別メソッドとする）。
     *
     * @param  array<int, string>  $departmentKeys
     * @return array<string, array{amount: float, order_count: int}>
     */
    private function rangeClientAggregates(array $departmentKeys, int $year, int $startMonth, int $endMonth, bool $consolidate): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $resolveName = $consolidate ? $this->clientDisplayNameResolver() : fn (string $name) => $name;

        return $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->get(['client_name', 'order_amount'])
            ->groupBy(fn ($o) => $o->client_name === null ? '（得意先未設定）' : $resolveName($o->client_name))
            ->map(fn ($group) => ['amount' => (float) $group->sum('order_amount'), 'order_count' => $group->count()])
            ->all();
    }

    /**
     * 得意先分析画面用。1得意先（統合ON時は統合後名称、OFF時は原名）を選んだときの年別推移・受注一覧。
     * 開始年月〜終了年月で厳密に絞り込む（境界年は該当月のみ、中間年は通期）。年が未登録の場合は
     * amount/order_countをnullにする（0円と誤表示しない）。受注一覧は新しい順・最大200件。
     */
    public function clientDetail(string $departmentKey, string $clientName, int $startYear, int $startMonth, int $endYear, int $endMonth, bool $consolidateClients): array
    {
        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);
        $rawNames = $consolidateClients ? $this->rawNamesForDisplayName($clientName) : [$clientName];

        $yearly = [];
        $priorAmount = null;

        for ($year = $startYear; $year <= $endYear; $year++) {
            $rangeStart = $year === $startYear ? $startMonth : 1;
            $rangeEnd = $year === $endYear ? $endMonth : 12;

            $amount = null;
            $orderCount = null;
            $companyAmount = null;

            if ($rangeEnd >= $rangeStart) {
                $yearIsRegistered = SalesActiveMonth::whereIn('department_key', $departmentKeys)
                    ->where('sales_year', $year)
                    ->whereBetween('sales_month', [$rangeStart, $rangeEnd])
                    ->exists();

                if ($yearIsRegistered) {
                    $row = $this->activeOrdersQuery($departmentKeys)
                        ->where('sales_orders.sales_year', $year)
                        ->whereBetween('sales_orders.sales_month', [$rangeStart, $rangeEnd])
                        ->whereIn('sales_orders.client_name', $rawNames)
                        ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(order_amount), 0) as total_amount')
                        ->first();

                    $amount = (float) $row->total_amount;
                    $orderCount = (int) $row->order_count;
                    $companyAmount = $this->rangeFigures($departmentKeys, $year, $rangeStart, $rangeEnd)['amount'] ?? 0.0;
                }
            }

            $diff = ($amount !== null && $priorAmount !== null) ? $amount - $priorAmount : null;
            $rate = ($diff !== null && $priorAmount > 0) ? round($diff / $priorAmount * 100, 1) : null;

            $yearly[] = [
                'year' => $year,
                'amount' => $amount,
                'order_count' => $orderCount,
                'prior_year_diff' => $diff,
                'prior_year_rate' => $rate,
                // 得意先分析画面のグラフ用（全体に対する割合表示、2026-09-04実機フィードバック対応）
                'company_amount' => $companyAmount,
                'share_pct' => ($amount !== null && $companyAmount !== null && $companyAmount > 0) ? round($amount / $companyAmount * 100, 1) : null,
            ];

            $priorAmount = $amount;
        }

        $startYm = $startYear * 100 + $startMonth;
        $endYm = $endYear * 100 + $endMonth;

        $orders = $this->activeOrdersQuery($departmentKeys)
            ->whereIn('sales_orders.client_name', $rawNames)
            ->whereRaw('(sales_orders.sales_year * 100 + sales_orders.sales_month) BETWEEN ? AND ?', [$startYm, $endYm])
            ->orderByDesc('sales_orders.sales_year')
            ->orderByDesc('sales_orders.sales_month')
            ->orderByDesc('sales_orders.plate_date')
            ->limit(200)
            ->get(['order_number', 'product_name', 'order_amount', 'plate_date', 'sales_orders.sales_year as sales_year', 'sales_orders.sales_month as sales_month'])
            ->map(fn ($o) => [
                'sales_year' => $o->sales_year,
                'sales_month' => $o->sales_month,
                'order_number' => $o->order_number,
                'product_name' => $o->product_name,
                'order_amount' => (float) $o->order_amount,
                'plate_date' => $o->plate_date?->format('Y-m-d'),
            ])
            ->all();

        return [
            'client_name' => $clientName,
            'yearly' => $yearly,
            'orders' => $orders,
        ];
    }

    /** 統合後表示名から、その統合グループに属する原名称の一覧を返す。グループが無ければ原名そのものを1件返す */
    private function rawNamesForDisplayName(string $displayName): array
    {
        $group = SalesClientGroup::where('name', $displayName)->with('members')->first();

        return $group ? $group->members->pluck('client_name')->all() : [$displayName];
    }

    /**
     * 年次分析Excel出力の「該当明細」シート用。指定年・月範囲の受注一覧を月・受注No順で返す
     * （2026-09-04 Phase 8）。$departmentKeyに'all'を渡すと3部署合算になる。
     */
    public function periodOrders(string $departmentKey, int $year, int $startMonth, int $endMonth): array
    {
        if ($endMonth < $startMonth) {
            return [];
        }

        $departmentKeys = $this->resolveDepartmentKeys($departmentKey);

        return $this->activeOrdersQuery($departmentKeys)
            ->where('sales_orders.sales_year', $year)
            ->whereBetween('sales_orders.sales_month', [$startMonth, $endMonth])
            ->orderBy('sales_orders.sales_month')
            ->orderBy('sales_orders.order_number')
            ->get(['sales_orders.sales_month as sales_month', 'order_number', 'client_name', 'product_name', 'order_amount', 'plate_date'])
            ->map(fn ($o) => [
                'sales_month' => $o->sales_month,
                'order_number' => $o->order_number,
                'client_name' => $o->client_name,
                'product_name' => $o->product_name,
                'order_amount' => (float) $o->order_amount,
                'plate_date' => $o->plate_date?->format('Y-m-d'),
            ])
            ->all();
    }
}
