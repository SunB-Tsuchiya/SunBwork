<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;

/**
 * 部門比較（SUNASALES_PLAN1.md §18）。部・課の売上を、年ごとに並べる。
 *  - 基準 same_month（既定）= 最新年の登録済みの月に、全ての年を揃えて集計する（途中の年が小さく見えないように）
 *  - 基準 year = 通年（1〜12月）。途中の年・一部の月が未登録の年は、印をつけて返す
 *  - 「第三部（全体）」の行は課・直轄の合計。課・直轄の行は、その下に並ぶ（画面で開閉する）
 * 部門は売上の行の部門（年ごとの担当部門）。名寄せの影響は受けない。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class DepartmentComparisonService
{
    public function __construct(
        private PeriodResolver $periods,
        private PeriodAggregator $aggregator,
        private DepartmentTree $tree,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(string $basis): array
    {
        $years = $this->periods->years();
        if ($years === []) {
            return ['has_data' => false, 'basis' => $basis, 'years' => [], 'rows' => []];
        }
        $latest = end($years);
        $months = $basis === 'year' ? range(1, 12) : $this->periods->months($latest);

        // 年ごとの登録済みの月（基準の月のうち）と、金額
        $registered = Fact::query()->whereIn('sales_month', $months)->select('sales_year', 'sales_month')->distinct()->get()
            ->groupBy('sales_year')->map(fn ($g) => $g->pluck('sales_month')->map(fn ($m) => (int) $m)->sort()->values()->all());

        $info = [];
        $amountsByYear = [];
        foreach ($years as $y) {
            $reg = $registered->get($y, []);
            $missing = array_values(array_diff($months, $reg));
            $info[] = [
                'year' => $y,
                'has_data' => $reg !== [],
                'registered_months' => $reg,
                'missing_months' => $missing,
                // 基準の月のうち未登録の月がある年（通年なら「途中」、同じ月までなら「一部の月が未登録」）
                'is_partial' => $reg !== [] && $missing !== [],
                'partial_label' => $reg !== [] && $missing !== [] ? $this->periods->label($reg) : null,
            ];
            $amountsByYear[$y] = $reg === [] ? null : $this->tree->amounts($this->aggregator->departmentAmounts($y, $months));
        }

        $rows = [];
        // 配列の + は数値キーが重なって先頭の部が消えるため、array_merge でつなぐ
        foreach (array_merge([['key' => 'total', 'label' => '全部門合計', 'level' => 0, 'parent' => null, 'has_children' => false]], $this->tree->nodes()) as $node) {
            $amounts = [];
            $shares = [];
            $yoy = [];
            foreach ($years as $i => $y) {
                $cur = $this->value($amountsByYear[$y], $node['key']);
                $total = $this->value($amountsByYear[$y], 'total');
                $amounts[$y] = $cur;
                $shares[$y] = ($cur !== null && $total > 0) ? round($cur / $total * 100, 1) : null;
                $prev = $i > 0 ? $this->value($amountsByYear[$years[$i - 1]], $node['key']) : null;
                $yoy[$y] = ($cur !== null && $prev !== null && $prev > 0) ? round(($cur - $prev) / $prev * 100, 1) : null;
            }
            $rows[] = $node + ['amounts' => $amounts, 'shares' => $shares, 'yoy' => $yoy];
        }

        return [
            'has_data' => true,
            'basis' => $basis,
            'months' => $months,
            'label' => $basis === 'year' ? '通年（1〜12月）' : '各年 ' . $this->periods->label($months),
            'years' => $info,
            'rows' => $rows,
        ];
    }

    /** ノードの金額。全部門合計（total）は、ノードの部（level 0）の合計。年にデータが無ければ null */
    private function value(?array $amounts, string $key): ?float
    {
        if ($amounts === null) {
            return null;
        }
        if ($key === 'total') {
            $sum = 0.0;
            foreach ($this->tree->nodes() as $n) {
                if ($n['level'] === 0) {
                    $sum += $amounts[$n['key']] ?? 0.0;
                }
            }

            return $sum;
        }

        return $amounts[$key] ?? 0.0;
    }
}
