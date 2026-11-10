<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;

/**
 * 注目ポイント（SUNASALES_PLAN1.md §20）。大きく増減した得意先・新規・休眠。
 *  - 増加／減少: 年次分析と同じ集計（AnnualAnalysisService::clientRows）で、前年との差額が大きい順に上位 TOP 社ずつ。
 *  - 新規: 年次分析と同じ（前年に売上がなく、今の期間に売上がある）。今年の売上の大きい順。
 *  - 休眠: 直近の登録月から数えて N か月間、売上（全部門）がなく、その前の12か月には売上があった得意先。
 *    前の12か月の売上が大きい順。部門の絞り込みは「前の12か月に計上された部門」で判定する。年の選択には影響されない。
 * 名寄せ後の得意先で数える。会社のコンテキスト（SunaCompanyContext）が必要。得意先名・金額はログに出さない。
 */
class AttentionService
{
    public const TOP = 10;
    public const LIST_LIMIT = 30;
    public const DORMANT_MONTHS = [3, 6, 12];
    private const PRIOR_WINDOW = 12;

    public function __construct(
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
        private AnnualAnalysisService $annual,
    ) {
    }

    /**
     * @param  ?int  $limit  新規・休眠の一覧の最大件数（null = 全件。Excel出力用）
     * @return array<string, mixed>
     */
    public function summary(int $year, string $dept, int $dormantMonths, ?int $limit = self::LIST_LIMIT): array
    {
        $period = $this->periods->period($year);
        $prior = $this->periods->prior($year);
        if ($period['months'] === []) {
            return ['has_data' => false, 'period' => $period, 'prior' => $prior];
        }

        $comparison = $prior['available'];
        $increase = $decrease = $new = [];
        if ($comparison) {
            $rows = $this->annual->clientRows($year, $dept)['rows'];
            $increase = $this->top($rows, fn ($r) => $r['status'] === 'continuing' && $r['diff'] > 0, 'diff', 'desc', self::TOP);
            $decrease = $this->top($rows, fn ($r) => $r['status'] === 'continuing' && $r['diff'] < 0, 'diff', 'asc', self::TOP);
            $new = $this->top($rows, fn ($r) => $r['status'] === 'new', 'amount', 'desc', $limit);
        }

        return [
            'has_data' => true,
            'period' => $period,
            'prior' => $prior,
            'comparison_available' => $comparison,
            'increase' => $increase,
            'decrease' => $decrease,
            'new' => $new,
            'dormant' => $this->dormant($dormantMonths, $dept, $limit),
        ];
    }

    /**
     * 休眠の得意先。登録月が足りないときは available=false とメッセージを返す。
     *
     * @return array<string, mixed>
     */
    public function dormant(int $months, string $dept, ?int $limit = self::LIST_LIMIT): array
    {
        $ord = 'suna_sl_facts.sales_year * 12 + suna_sl_facts.sales_month';
        $range = Fact::query()->selectRaw("MIN({$ord}) as first_ord, MAX({$ord}) as last_ord")->first();
        if ($range === null || $range->last_ord === null) {
            return $this->unavailable($months, 'まだ売上データが取り込まれていません。');
        }
        $latest = (int) $range->last_ord;
        $first = (int) $range->first_ord;
        $silentFrom = $latest - $months + 1;
        $priorFrom = $silentFrom - self::PRIOR_WINDOW;
        $priorTo = $silentFrom - 1;

        $base = [
            'months' => $months,
            'latest_label' => $this->label($latest),
            'silent_label' => $this->label($silentFrom) . '〜' . $this->label($latest),
            'prior_label' => $this->label($priorFrom) . '〜' . $this->label($priorTo),
        ];
        if ($first > $priorFrom) {
            return $this->unavailable($months, "データが足りません。休眠を判定するには、{$this->label($priorFrom)}から{$this->label($latest)}までの売上が必要です（登録は{$this->label($first)}から）。", $base);
        }

        // 止まっている期間に売上のある得意先（全部門）は除く
        $active = [];
        foreach (EffectiveClient::join(Fact::query())->whereRaw("{$ord} between ? and ?", [$silentFrom, $latest])
            ->selectRaw(EffectiveClient::KEY . ' as ck')->groupBy('ck')->havingRaw('SUM(suna_sl_facts.amount) > 0')->get() as $r) {
            $active[$r->ck] = true;
        }

        $q = EffectiveClient::join(Fact::query())->whereRaw("{$ord} between ? and ?", [$priorFrom, $priorTo]);
        $rows = [];
        foreach ($this->departments->apply($q, $dept)
            ->selectRaw(EffectiveClient::KEY . ' as ck, ' . EffectiveClient::NAME . " as name, SUM(suna_sl_facts.amount) as amount, MAX(CASE WHEN suna_sl_facts.amount > 0 THEN {$ord} END) as last_ord")
            ->groupBy('ck')->havingRaw('SUM(suna_sl_facts.amount) > 0')->get() as $r) {
            if (isset($active[$r->ck])) {
                continue;
            }
            $last = (int) $r->last_ord;
            $rows[] = [
                'client_key' => (string) $r->ck,
                'label' => (string) $r->name,
                'prior_amount' => (float) $r->amount,
                'last_label' => $this->label($last),
                'silent_months' => $latest - $last,
            ];
        }
        usort($rows, fn ($a, $b) => $b['prior_amount'] <=> $a['prior_amount'] ?: strcmp($a['label'], $b['label']));

        return $base + [
            'available' => true,
            'message' => null,
            'total_count' => count($rows),
            'rows' => $limit === null ? $rows : array_slice($rows, 0, $limit),
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>, total_count:int} */
    private function top(array $rows, callable $filter, string $field, string $direction, ?int $limit): array
    {
        $rows = array_values(array_filter($rows, $filter));
        $sign = $direction === 'asc' ? 1 : -1;
        usort($rows, fn ($a, $b) => $sign * ($a[$field] <=> $b[$field]) ?: strcmp((string) $a['label'], (string) $b['label']));

        return ['rows' => $limit === null ? $rows : array_slice($rows, 0, $limit), 'total_count' => count($rows)];
    }

    private function unavailable(int $months, string $message, array $base = []): array
    {
        return $base + ['months' => $months, 'available' => false, 'message' => $message, 'total_count' => 0, 'rows' => []];
    }

    /** 通し番号（年×12＋月）→ 「2026年5月」 */
    private function label(int $ordinal): string
    {
        $y = intdiv($ordinal - 1, 12);
        $m = $ordinal - $y * 12;

        return "{$y}年{$m}月";
    }
}
