<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;
use App\Services\SalesAnalysis\ClientNameNormalizer;

/**
 * 得意先分析（SUNASALES_PLAN1.md §15）。得意先を検索して選び、年別・月別の推移と担当部門の履歴を見る。
 * 前年比は「その年の登録済みの月」を「前年の同じ月」と比べる（年次分析と同じ考え方）。
 * 会社のコンテキスト（SunaCompanyContext）が必要。
 */
class ClientAnalysisService
{
    public function __construct(
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
    ) {
    }

    /**
     * 得意先の検索（名前の一部。全角半角・空白の違いは無視）。売上の多い順。
     *
     * @return array<int, array{client_key:string, name:string, total_amount:float, last_year:int}>
     */
    public function search(string $keyword, int $limit = 20): array
    {
        $q = Fact::query()->selectRaw('client_key, MAX(client_name) as name, SUM(amount) as total, MAX(sales_year) as last_year')->groupBy('client_key');
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $keyLike = '%' . addcslashes(ClientNameNormalizer::normalize($keyword), '%_\\') . '%';
            $q->where(fn ($w) => $w->where('client_name', 'like', $like)->orWhere('client_key', 'like', $keyLike));
        }

        return $q->orderByDesc('total')->orderBy('client_key')->limit(max(1, min(50, $limit)))->get()
            ->map(fn ($r) => ['client_key' => (string) $r->client_key, 'name' => (string) $r->name, 'total_amount' => (float) $r->total, 'last_year' => (int) $r->last_year])
            ->all();
    }

    /** @return array<string, mixed>|null 得意先が無ければ null */
    public function detail(string $clientKey): ?array
    {
        $facts = Fact::query()->where('client_key', $clientKey)->orderBy('sales_year')->orderBy('sales_month')->get();
        if ($facts->isEmpty()) {
            return null;
        }

        $labels = $this->departments->rowLabels();
        $years = [];
        $previous = null;
        foreach ($facts->groupBy('sales_year') as $year => $rows) {
            $year = (int) $year;
            $periodMonths = $this->periods->months($year);
            $byMonth = $rows->groupBy('sales_month');
            $months = [];
            foreach (range(1, 12) as $m) {
                $months[$m] = $byMonth->has($m)
                    ? ['amount' => (float) $byMonth[$m]->sum('amount'), 'order_count' => (int) $byMonth[$m]->sum('order_count')]
                    : null;
            }
            $amount = (float) $rows->sum('amount');
            $orders = (int) $rows->sum('order_count');

            // 前年比: その年の登録済みの月（全体）を、前年の同じ月と比べる。前の年にこの得意先の行が無ければ 0 円
            $prior = $this->periods->prior($year);
            $priorAmount = null;
            if ($prior['available']) {
                $priorAmount = (float) $facts->where('sales_year', $year - 1)->whereIn('sales_month', $periodMonths)->sum('amount');
            }
            $curSame = (float) $rows->whereIn('sales_month', $periodMonths)->sum('amount');

            $keys = $rows->map(fn ($r) => $r->section_key ?? $r->department_key)->unique()->values()->all();
            $years[] = [
                'year' => $year,
                'amount' => $amount,
                'order_count' => $orders,
                'avg_order_price' => $orders > 0 ? round($amount / $orders) : null,
                'registered_months' => $byMonth->keys()->map(fn ($m) => (int) $m)->sort()->values()->all(),
                'months' => $months,
                'departments' => array_map(fn ($k) => $labels[$k] ?? $k, $keys),
                'prior_amount' => $priorAmount,
                'diff' => $priorAmount !== null ? $curSame - $priorAmount : null,
                'rate' => ($priorAmount !== null && $priorAmount > 0) ? round(($curSame - $priorAmount) / $priorAmount * 100, 1) : null,
                'compare_label' => $this->periods->label($periodMonths),
            ];
        }

        // 担当部門の履歴（年ごと。前の年から変わったら changed）
        $history = [];
        foreach ($years as $y) {
            $label = implode('・', $y['departments']);
            $history[] = ['year' => $y['year'], 'label' => $label, 'changed' => $previous !== null && $previous !== $label];
            $previous = $label;
        }

        return [
            'client_key' => $clientKey,
            'name' => (string) $facts->last()->client_name,
            'total_amount' => (float) $facts->sum('amount'),
            'first_year' => $years[0]['year'],
            'last_year' => end($years)['year'],
            'years' => $years,
            'department_history' => $history,
        ];
    }
}
