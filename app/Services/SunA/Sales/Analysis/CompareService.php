<?php

namespace App\Services\SunA\Sales\Analysis;

/**
 * 左右比較（SUNASALES_PLAN1.md §18）。2つの期間 A・B を並べて比べる。差・増減率は A に対する B（B − A）。
 *  - mode=year: 年と年。**両方の年に登録のある月だけ**を集計する（片方が途中の年でも公平に比べる）
 *  - mode=month: 月と月（年・月は各自）。どちらかが未登録なら、比べない
 * 得意先は名寄せ後で数える。部門別は、部門の絞り込みの影響を受けない（全部門の内訳）。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class CompareService
{
    private const TOP_CLIENTS = 15;

    private const ENDED_CLIENTS = 10;

    public function __construct(
        private PeriodResolver $periods,
        private PeriodAggregator $aggregator,
        private DepartmentTree $tree,
    ) {
    }

    /**
     * @param  array{year:int, month?:int}  $a
     * @param  array{year:int, month?:int}  $b
     * @return array<string, mixed>
     */
    public function summary(string $mode, array $a, array $b, string $dept): array
    {
        if ($mode === 'year') {
            $common = array_values(array_intersect($this->periods->months($a['year']), $this->periods->months($b['year'])));
            if ($common === []) {
                return $this->unavailable($mode, "{$a['year']}年と{$b['year']}年の両方に登録のある月がないため、比べられません。");
            }
            $monthsA = $monthsB = $common;
            $labelA = "{$a['year']}年 " . $this->periods->label($common);
            $labelB = "{$b['year']}年 " . $this->periods->label($common);
        } else {
            foreach (['A' => $a, 'B' => $b] as $name => $p) {
                if (! in_array($p['month'], $this->periods->months($p['year']), true)) {
                    return $this->unavailable($mode, "期間{$name}（{$p['year']}年{$p['month']}月）は売上が登録されていないため、比べられません。");
                }
            }
            $monthsA = [$a['month']];
            $monthsB = [$b['month']];
            $labelA = "{$a['year']}年{$a['month']}月";
            $labelB = "{$b['year']}年{$b['month']}月";
        }

        $ta = $this->aggregator->totals($a['year'], $monthsA, $dept);
        $tb = $this->aggregator->totals($b['year'], $monthsB, $dept);

        return [
            'has_data' => true,
            'mode' => $mode,
            'a' => ['year' => $a['year'], 'months' => $monthsA, 'label' => $labelA],
            'b' => ['year' => $b['year'], 'months' => $monthsB, 'label' => $labelB],
            'kpi' => [
                'amount' => $this->metric($ta['amount'], $tb['amount']),
                'order_count' => $this->metric($ta['order_count'], $tb['order_count']),
                'avg_order_price' => $this->metric($ta['avg_order_price'], $tb['avg_order_price']),
                'client_count' => $this->metric($ta['client_count'], $tb['client_count']),
            ],
            'by_department' => $this->byDepartment($a['year'], $monthsA, $b['year'], $monthsB),
            'clients' => $this->clients($a['year'], $monthsA, $b['year'], $monthsB, $dept),
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $mode, string $reason): array
    {
        return ['has_data' => false, 'mode' => $mode, 'reason' => $reason];
    }

    /** @return array{a:float|int|null, b:float|int|null, diff:float|int|null, rate:float|null} */
    private function metric($a, $b): array
    {
        return [
            'a' => $a,
            'b' => $b,
            'diff' => ($a !== null && $b !== null) ? $b - $a : null,
            'rate' => ($a !== null && $b !== null && $a > 0) ? round(($b - $a) / $a * 100, 1) : null,
        ];
    }

    /** @return array<int, array<string, mixed>> 部の行の直後に課の行が並ぶ（画面で開閉） */
    private function byDepartment(int $yearA, array $monthsA, int $yearB, array $monthsB): array
    {
        $amountsA = $this->tree->amounts($this->aggregator->departmentAmounts($yearA, $monthsA));
        $amountsB = $this->tree->amounts($this->aggregator->departmentAmounts($yearB, $monthsB));
        $nodes = $this->tree->nodes();
        $totalA = array_sum(array_map(fn ($n) => $n['level'] === 0 ? ($amountsA[$n['key']] ?? 0) : 0, $nodes));
        $totalB = array_sum(array_map(fn ($n) => $n['level'] === 0 ? ($amountsB[$n['key']] ?? 0) : 0, $nodes));

        $rows = [['key' => 'total', 'label' => '全部門合計', 'level' => 0, 'parent' => null, 'has_children' => false] + $this->metric((float) $totalA, (float) $totalB)];
        foreach ($nodes as $n) {
            $rows[] = $n + $this->metric($amountsA[$n['key']] ?? 0.0, $amountsB[$n['key']] ?? 0.0);
        }

        return $rows;
    }

    /**
     * 得意先別。Bの金額の多い順に上位15社＋その他。Bが0円でAにあった得意先（終了）は、別に上位10社を返す。
     *
     * @return array{top:array<int,array<string,mixed>>, others:?array<string,mixed>, ended:array<int,array<string,mixed>>, total_clients:int}
     */
    private function clients(int $yearA, array $monthsA, int $yearB, array $monthsB, string $dept): array
    {
        $ca = $this->aggregator->clientTotals($yearA, $monthsA, $dept);
        $cb = $this->aggregator->clientTotals($yearB, $monthsB, $dept);

        $rows = [];
        foreach ($ca + $cb as $key => $_) {
            $a = $ca[$key]['amount'] ?? 0.0;
            $b = $cb[$key]['amount'] ?? 0.0;
            $rows[] = [
                'client_key' => (string) $key,
                'label' => $cb[$key]['name'] ?? $ca[$key]['name'],
                'a' => $a, 'b' => $b, 'diff' => $b - $a,
                'rate' => $a > 0 ? round(($b - $a) / $a * 100, 1) : null,
                'status' => ($a <= 0 && $b > 0) ? 'new' : (($a > 0 && $b <= 0) ? 'ended' : 'continuing'),
            ];
        }

        $inB = array_values(array_filter($rows, fn ($r) => $r['b'] > 0));
        usort($inB, fn ($x, $y) => $y['b'] <=> $x['b'] ?: strcmp($x['label'], $y['label']));
        $top = array_slice($inB, 0, self::TOP_CLIENTS);
        $rest = array_slice($inB, self::TOP_CLIENTS);

        $others = null;
        if ($rest !== []) {
            $a = array_sum(array_column($rest, 'a'));
            $b = array_sum(array_column($rest, 'b'));
            $others = ['label' => 'その他', 'count' => count($rest), 'a' => (float) $a, 'b' => (float) $b, 'diff' => (float) ($b - $a), 'rate' => $a > 0 ? round(($b - $a) / $a * 100, 1) : null];
        }

        $ended = array_values(array_filter($rows, fn ($r) => $r['status'] === 'ended'));
        usort($ended, fn ($x, $y) => $y['a'] <=> $x['a'] ?: strcmp($x['label'], $y['label']));

        return [
            'top' => $top,
            'others' => $others,
            'ended' => array_slice($ended, 0, self::ENDED_CLIENTS),
            'ended_total' => count($ended),
            'total_clients' => count($inB),
        ];
    }
}
