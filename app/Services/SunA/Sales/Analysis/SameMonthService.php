<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;

/**
 * 同月比較（SUNASALES_PLAN1.md §15）。選んだ月を、年どうしで比べる。その月に登録のない年は除外（0円ではなく「未登録」）。
 * 前年比は、前の年にもその月の登録があるときだけ出す。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class SameMonthService
{
    public function __construct(
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
        private PeriodAggregator $aggregator,
        private DepartmentTree $tree,
    ) {
    }

    /** @return array<int,int> 登録のある月（昇順）。画面の月の選択肢と、最初に開く月の決定に使う */
    public function monthsWithData(): array
    {
        return Fact::query()->distinct()->orderBy('sales_month')->pluck('sales_month')->map(fn ($m) => (int) $m)->all();
    }

    /** @return array<string, mixed> */
    public function summary(int $month, string $dept): array
    {
        $years = $this->periods->yearsWithMonth($month);
        if ($years === []) {
            return ['has_data' => false, 'month' => $month, 'years' => [], 'by_department' => ['years' => [], 'rows' => []]];
        }

        $rows = $this->departments->apply(Fact::query()->where('sales_month', $month), $dept)
            ->selectRaw('sales_year, SUM(amount) as amount, SUM(order_count) as orders')
            ->groupBy('sales_year')->get()->keyBy('sales_year');
        // 取引社数は名寄せ後の数（グループは1社）
        $clients = $this->departments->apply(EffectiveClient::join(Fact::query())->where('sales_month', $month), $dept)
            ->selectRaw('sales_year, ' . EffectiveClient::KEY . ' as ck')->groupBy('sales_year', 'ck')->havingRaw('SUM(suna_sl_facts.amount) > 0')->get()
            ->groupBy('sales_year')->map->count();

        $list = [];
        $byYear = [];
        foreach ($years as $y) {
            $amount = (float) ($rows->get($y)->amount ?? 0);
            $orders = (int) ($rows->get($y)->orders ?? 0);
            $byYear[$y] = $amount;
            $prevAmount = $byYear[$y - 1] ?? null; // 前の年にもこの月の登録があるときだけ
            $list[] = [
                'year' => $y,
                'amount' => $amount,
                'order_count' => $orders,
                'avg_order_price' => $orders > 0 ? round($amount / $orders) : null,
                'client_count' => (int) ($clients[$y] ?? 0),
                'diff' => $prevAmount !== null ? $amount - $prevAmount : null,
                'rate' => ($prevAmount !== null && $prevAmount > 0) ? round(($amount - $prevAmount) / $prevAmount * 100, 1) : null,
            ];
        }

        return [
            'has_data' => true,
            'month' => $month,
            'years' => $list,
            'by_department' => $this->byDepartment($month, $years),
        ];
    }

    /**
     * 部門別 × 年。部の行の直後に、課のある部は課・直轄の行が並ぶ（画面で開閉）。「第三部（全体）」は課・直轄の合計。
     * 部門の絞り込みの影響は受けない（全部門の内訳を見せる）。
     *
     * @return array{years:array<int,int>, rows:array<int,array<string,mixed>>}
     */
    private function byDepartment(int $month, array $years): array
    {
        $byYear = [];
        foreach ($years as $y) {
            $byYear[$y] = $this->tree->amounts($this->aggregator->departmentAmounts($y, [$month]));
        }

        $rows = [];
        foreach ($this->tree->nodes() as $n) {
            $amounts = [];
            foreach ($years as $y) {
                $amounts[$y] = $byYear[$y][$n['key']] ?? 0.0;
            }
            // どの年も売上が無い部門は出さない
            if (array_sum(array_map('abs', $amounts)) == 0.0) {
                continue;
            }
            $rows[] = $n + ['amounts' => $amounts];
        }

        return ['years' => $years, 'rows' => $rows];
    }
}
