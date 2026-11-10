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
        $clients = $this->departments->apply(Fact::query()->where('sales_month', $month), $dept)
            ->selectRaw('sales_year, client_key')->groupBy('sales_year', 'client_key')->havingRaw('SUM(amount) > 0')->get()
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
     * 部門別（課のある部は課まで）× 年。部門の絞り込みの影響は受けない（全部門の内訳を見せる）。
     *
     * @return array{years:array<int,int>, rows:array<int,array<string,mixed>>}
     */
    private function byDepartment(int $month, array $years): array
    {
        $labels = $this->departments->rowLabels();
        $order = $this->departments->order();
        $matrix = [];
        foreach (Fact::query()->where('sales_month', $month)->whereIn('sales_year', $years)
            ->selectRaw('sales_year, department_key, section_key, SUM(amount) as amount')
            ->groupBy('sales_year', 'department_key', 'section_key')->get() as $r) {
            $matrix[$r->section_key ?? $r->department_key][(int) $r->sales_year] = (float) $r->amount;
        }

        $rows = [];
        foreach ($matrix as $key => $byYear) {
            $amounts = [];
            foreach ($years as $y) {
                $amounts[$y] = $byYear[$y] ?? 0.0;
            }
            $rows[] = ['key' => (string) $key, 'label' => $labels[$key] ?? $key, 'amounts' => $amounts, '_order' => $order[$key] ?? 999];
        }
        usort($rows, fn ($a, $b) => $a['_order'] <=> $b['_order']);

        return [
            'years' => $years,
            'rows' => array_map(function ($r) {
                unset($r['_order']);

                return $r;
            }, $rows),
        ];
    }
}
