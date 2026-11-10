<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;

/**
 * 年次分析（SUNASALES_PLAN1.md §15）。KPI・月別推移・部門別・得意先ランキング。
 * 比較は「その年の登録済みの月」を「前年の同じ月」と行う。部門の絞り込みは売上の行の部門（年ごとの担当部門）で行う。
 * 会社のコンテキスト（SunaCompanyContext）が必要。得意先名・金額はログに出さない。
 */
class AnnualAnalysisService
{
    public function __construct(
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(int $year, string $dept, int $yearsToShow = 2): array
    {
        $period = $this->periods->period($year);
        $prior = $this->periods->prior($year);
        if ($period['months'] === []) {
            return ['has_data' => false, 'period' => $period, 'prior' => $prior];
        }

        $cur = $this->totals($year, $period['months'], $dept);
        $pri = $prior['available'] ? $this->totals($year - 1, $period['months'], $dept) : null;

        return [
            'has_data' => true,
            'period' => $period,
            'prior' => $prior,
            'kpi' => [
                'amount' => $this->metric($cur['amount'], $pri['amount'] ?? null),
                'order_count' => $this->metric($cur['order_count'], $pri['order_count'] ?? null),
                'avg_order_price' => $this->metric($cur['avg_order_price'], $pri['avg_order_price'] ?? null),
                'client_count' => $this->metric($cur['client_count'], $pri['client_count'] ?? null),
            ],
            'monthly' => $this->monthly($year, $dept, $yearsToShow),
            'by_department' => $this->byDepartment($year, $period['months'], $prior['available']),
        ];
    }

    /**
     * 得意先ランキング。mode: all（今の期間に売上のある得意先）／increase／decrease／new／ended。
     * 前年比が使えないとき（最初の年など）は、all 以外は空。
     *
     * @return array{rows:array<int,array<string,mixed>>, total_count:int, total_amount:float, comparison_available:bool}
     */
    public function clients(int $year, string $dept, string $mode, string $sort, string $direction, string $keyword, int $page, int $limit): array
    {
        $period = $this->periods->period($year);
        $prior = $this->periods->prior($year);
        $cur = $this->clientTotals($year, $period['months'], $dept);
        $pri = $prior['available'] ? $this->clientTotals($year - 1, $period['months'], $dept) : [];
        $totalAmount = array_sum(array_column($cur, 'amount'));

        $rows = [];
        foreach ($cur + $pri as $key => $_) {
            $c = $cur[$key]['amount'] ?? 0.0;
            $p = $pri[$key]['amount'] ?? 0.0;
            $name = $cur[$key]['name'] ?? $pri[$key]['name'];
            $status = null;
            if ($prior['available']) {
                $status = $p <= 0 && $c > 0 ? 'new' : ($p > 0 && $c <= 0 ? 'ended' : 'continuing');
            }
            $rows[] = [
                'client_key' => (string) $key,
                'label' => $name,
                'amount' => $c,
                'prior_amount' => $prior['available'] ? $p : null,
                'diff' => $prior['available'] ? $c - $p : null,
                'rate' => ($prior['available'] && $p > 0) ? round(($c - $p) / $p * 100, 1) : null,
                'share_pct' => ($totalAmount > 0 && $c > 0) ? round($c / $totalAmount * 100, 1) : null,
                'status' => $status,
            ];
        }

        $rows = array_values(array_filter($rows, function ($r) use ($mode, $prior) {
            if ($mode === 'all') {
                return $r['amount'] > 0;
            }
            if (! $prior['available']) {
                return false;
            }

            return match ($mode) {
                'new' => $r['status'] === 'new',
                'ended' => $r['status'] === 'ended',
                'increase' => $r['status'] === 'continuing' && $r['diff'] > 0,
                'decrease' => $r['status'] === 'continuing' && $r['diff'] < 0,
                default => false,
            };
        }));

        if ($keyword !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => mb_stripos((string) $r['label'], $keyword) !== false));
        }

        $field = $sort === 'diff' ? 'diff' : 'amount';
        $sign = $direction === 'asc' ? 1 : -1;
        usort($rows, fn ($a, $b) => $sign * (($a[$field] ?? 0) <=> ($b[$field] ?? 0)) ?: strcmp((string) $a['label'], (string) $b['label']));

        $limit = max(1, min(100, $limit));
        $page = max(1, $page);

        return [
            'rows' => array_slice($rows, ($page - 1) * $limit, $limit),
            'total_count' => count($rows),
            'total_amount' => (float) $totalAmount,
            'comparison_available' => $prior['available'],
        ];
    }

    /** 期間内の合計（売上・受注件数・平均受注単価・取引社数＝期間の売上が0円を超える得意先の数） */
    private function totals(int $year, array $months, string $dept): array
    {
        $base = $this->facts($year, $months, $dept);
        $sum = (clone $base)->selectRaw('COALESCE(SUM(amount),0) as amount, COALESCE(SUM(order_count),0) as orders')->first();
        $clients = (clone $base)->selectRaw('client_key')->groupBy('client_key')->havingRaw('SUM(amount) > 0')->get()->count();
        $amount = (float) $sum->amount;
        $orders = (int) $sum->orders;

        return [
            'amount' => $amount,
            'order_count' => $orders,
            'avg_order_price' => $orders > 0 ? round($amount / $orders) : null,
            'client_count' => $clients,
        ];
    }

    /** @return array<string, array{name:string, amount:float}> 得意先キー → 名前と期間の売上 */
    private function clientTotals(int $year, array $months, string $dept): array
    {
        if ($months === []) {
            return [];
        }
        $out = [];
        foreach ($this->facts($year, $months, $dept)->selectRaw('client_key, MAX(client_name) as name, SUM(amount) as amount')->groupBy('client_key')->get() as $r) {
            $out[$r->client_key] = ['name' => (string) $r->name, 'amount' => (float) $r->amount];
        }

        return $out;
    }

    private function facts(int $year, array $months, string $dept)
    {
        $q = Fact::query()->where('sales_year', $year)->whereIn('sales_month', $months);

        return $this->departments->apply($q, $dept);
    }

    /** @return array{value:float|int|null, prior:float|int|null, diff:float|int|null, rate:float|null} */
    private function metric($value, $prior): array
    {
        return [
            'value' => $value,
            'prior' => $prior,
            'diff' => ($value !== null && $prior !== null) ? $value - $prior : null,
            'rate' => ($value !== null && $prior !== null && $prior > 0) ? round(($value - $prior) / $prior * 100, 1) : null,
        ];
    }

    /**
     * 月別の売上（当年を含む直近 N 年。登録のない月は null）。
     *
     * @return array<int, array{year:int, months:array<int,float|null>}>
     */
    private function monthly(int $year, string $dept, int $yearsToShow): array
    {
        $yearsToShow = max(1, min(5, $yearsToShow));
        $dataYears = $this->periods->years();
        $years = array_values(array_filter(range($year - $yearsToShow + 1, $year), fn ($y) => in_array($y, $dataYears, true)));
        if ($years === []) {
            return [];
        }

        $sums = $this->departments->apply(Fact::query()->whereIn('sales_year', $years), $dept)
            ->selectRaw('sales_year, sales_month, SUM(amount) as amount')->groupBy('sales_year', 'sales_month')->get()
            ->groupBy('sales_year');

        $out = [];
        foreach ($years as $y) {
            $registered = $this->periods->months($y);
            $byMonth = ($sums->get($y) ?? collect())->keyBy('sales_month');
            $months = [];
            foreach (range(1, 12) as $m) {
                $months[$m] = in_array($m, $registered, true) ? (float) ($byMonth->get($m)->amount ?? 0) : null;
            }
            $out[] = ['year' => $y, 'months' => $months];
        }

        return $out;
    }

    /**
     * 部門別（課のある部は課まで）。部門の絞り込みの影響は受けない（全部門の内訳を見せる）。
     *
     * @return array<int, array<string,mixed>>
     */
    private function byDepartment(int $year, array $months, bool $priorAvailable): array
    {
        $labels = $this->departments->rowLabels();
        $order = $this->departments->order();
        $collect = function (int $y) use ($months) {
            $out = [];
            foreach (Fact::query()->where('sales_year', $y)->whereIn('sales_month', $months)
                ->selectRaw('department_key, section_key, SUM(amount) as amount, COUNT(DISTINCT client_key) as clients')
                ->groupBy('department_key', 'section_key')->get() as $r) {
                $out[$r->section_key ?? $r->department_key] = ['amount' => (float) $r->amount, 'clients' => (int) $r->clients];
            }

            return $out;
        };
        $cur = $collect($year);
        $pri = $priorAvailable ? $collect($year - 1) : [];
        $total = array_sum(array_column($cur, 'amount'));

        $rows = [];
        foreach (array_keys($cur + $pri) as $key) {
            $c = $cur[$key]['amount'] ?? 0.0;
            $p = $priorAvailable ? ($pri[$key]['amount'] ?? 0.0) : null;
            $rows[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'amount' => $c,
                'prior_amount' => $p,
                'diff' => $p !== null ? $c - $p : null,
                'rate' => ($p !== null && $p > 0) ? round(($c - $p) / $p * 100, 1) : null,
                'share_pct' => $total > 0 ? round($c / $total * 100, 1) : null,
                'client_count' => $cur[$key]['clients'] ?? 0,
                '_order' => $order[$key] ?? 999,
            ];
        }
        usort($rows, fn ($a, $b) => $a['_order'] <=> $b['_order']);

        return array_map(function ($r) {
            unset($r['_order']);

            return $r;
        }, $rows);
    }
}
