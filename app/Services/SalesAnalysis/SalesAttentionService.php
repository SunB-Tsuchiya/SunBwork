<?php

namespace App\Services\SalesAnalysis;

/**
 * サン・ブレーン売上分析の「注目ポイント」（SBATTN_PLAN1.md）。大きく増減した得意先・新規・休眠。
 *  - 増加／減少: 年次分析と同じ集計（選んだ年の登録済みの月を、前年の同じ月と比べる）で、前年との差額が大きい順に上位 TOP 社ずつ。
 *    前年も今年も売上がある得意先が対象（新規・離脱は含めない）。
 *  - 新規: 前年の同じ月に売上がなく、今年は売上がある得意先（今年の売上の大きい順）。
 *  - 休眠: 基準月（選んだ部署の最新の登録月。全部署は3部署すべてに登録がある最新の月）から N か月売上がなく、
 *    その前の12か月には売上があった得意先（前の12か月の売上の大きい順）。選んだ部署の売上だけで判定する。年の選択には影響されない。
 * 得意先統合がONのときは、グループを1社として数える。「（得意先未設定）」は、対応できないので一覧に出さない。
 * 会社は forCompany() で設定する（SalesQueryService と同じ流儀）。得意先名・金額はログに出さない。
 */
class SalesAttentionService
{
    public const TOP = 10;

    public const LIST_LIMIT = 30;

    public const DORMANT_MONTHS = [3, 6, 12];

    private const PRIOR_WINDOW = 12;

    private const UNSET_LABEL = '（得意先未設定）';

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function forCompany(int $companyId): self
    {
        $this->queryService->forCompany($companyId);

        return $this;
    }

    /**
     * @param  ?int  $limit  新規・休眠の一覧の最大件数（null = 全件。Excel出力用）
     * @return array<string, mixed>
     */
    public function summary(string $departmentKey, int $year, bool $consolidate, int $dormantMonths, ?int $limit = self::LIST_LIMIT): array
    {
        $lastMonth = $this->queryService->lastRegisteredMonthOfYear($departmentKey, $year);
        if ($lastMonth < 1) {
            return ['has_data' => false, 'year' => $year, 'last_month' => 0];
        }

        $comparison = $this->queryService->lastRegisteredMonthOfYear($departmentKey, $year - 1) > 0;
        $increase = $decrease = $new = ['rows' => [], 'total_count' => 0];
        if ($comparison) {
            $rows = $this->clientRows($departmentKey, $year, $lastMonth, $consolidate);
            $increase = $this->top($rows, fn ($r) => $r['prior_amount'] > 0 && $r['amount'] > 0 && $r['diff'] > 0, 'diff', 'desc', self::TOP);
            $decrease = $this->top($rows, fn ($r) => $r['prior_amount'] > 0 && $r['amount'] > 0 && $r['diff'] < 0, 'diff', 'asc', self::TOP);
            $new = $this->top($rows, fn ($r) => $r['prior_amount'] <= 0 && $r['amount'] > 0, 'amount', 'desc', $limit);
        }

        return [
            'has_data' => true,
            'year' => $year,
            'last_month' => $lastMonth,
            'comparison_available' => $comparison,
            'prior_year' => $year - 1,
            'increase' => $increase,
            'decrease' => $decrease,
            'new' => $new,
            'dormant' => $this->dormant($departmentKey, $dormantMonths, $consolidate, $limit),
        ];
    }

    /**
     * 休眠の得意先。登録月が足りないときは available=false とメッセージを返す。
     *
     * @return array<string, mixed>
     */
    public function dormant(string $departmentKey, int $months, bool $consolidate, ?int $limit = self::LIST_LIMIT): array
    {
        $range = $this->queryService->registeredRange($departmentKey);
        if ($range === null) {
            return $this->unavailable($months, 'まだ売上データが取り込まれていません。');
        }

        $latest = $range['last'];
        $silentFrom = $latest - $months + 1;
        $priorFrom = $silentFrom - self::PRIOR_WINDOW;
        $priorTo = $silentFrom - 1;

        $base = [
            'months' => $months,
            'latest_label' => $this->label($latest),
            'silent_label' => $this->label($silentFrom) . '〜' . $this->label($latest),
            'prior_label' => $this->label($priorFrom) . '〜' . $this->label($priorTo),
        ];
        if ($range['first'] > $priorFrom) {
            return $this->unavailable($months, "データが足りません。休眠を判定するには、{$this->label($priorFrom)}から{$this->label($latest)}までの売上が必要です（登録は{$this->label($range['first'])}から）。", $base);
        }

        // 止まっている期間に売上のある得意先（選んだ部署の売上で判定）は除く
        $active = [];
        foreach ($this->monthlyTotals($departmentKey, $silentFrom, $latest, $consolidate) as $names) {
            foreach ($names as $name => $amount) {
                $active[$name] = ($active[$name] ?? 0.0) + $amount;
            }
        }

        $prior = [];   // 名前 => ['amount' => 合計, 'last' => 売上のあった最後の月（通し番号）]
        foreach ($this->monthlyTotals($departmentKey, $priorFrom, $priorTo, $consolidate) as $ord => $names) {
            foreach ($names as $name => $amount) {
                $prior[$name]['amount'] = ($prior[$name]['amount'] ?? 0.0) + $amount;
                if ($amount > 0) {
                    $prior[$name]['last'] = max($prior[$name]['last'] ?? 0, $ord);
                }
            }
        }

        $rows = [];
        foreach ($prior as $name => $p) {
            if ($name === self::UNSET_LABEL || ($p['amount'] ?? 0) <= 0 || (($active[$name] ?? 0) > 0)) {
                continue;
            }
            $last = (int) $p['last'];
            $rows[] = [
                'label' => (string) $name,
                'prior_amount' => (float) $p['amount'],
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

    /**
     * 得意先ごとの、今年（その年の登録済みの月）と前年の同じ月の売上。
     *
     * @return array<int, array{label:string, amount:float, prior_amount:float, diff:float, rate:?float}>
     */
    private function clientRows(string $departmentKey, int $year, int $lastMonth, bool $consolidate): array
    {
        $current = $this->queryService->clientTotalsForPeriod($departmentKey, $year, 1, $lastMonth, $consolidate);
        $prior = $this->queryService->clientTotalsForPeriod($departmentKey, $year - 1, 1, $lastMonth, $consolidate);

        $rows = [];
        foreach (array_unique(array_merge(array_keys($current), array_keys($prior))) as $name) {
            $name = (string) $name;
            if ($name === self::UNSET_LABEL) {
                continue;
            }
            $amount = (float) ($current[$name]['amount'] ?? 0.0);
            $priorAmount = (float) ($prior[$name]['amount'] ?? 0.0);
            $diff = $amount - $priorAmount;
            $rows[] = [
                'label' => $name,
                'amount' => $amount,
                'prior_amount' => $priorAmount,
                'diff' => $diff,
                'rate' => $priorAmount > 0 ? round($diff / $priorAmount * 100, 1) : null,
            ];
        }

        return $rows;
    }

    /**
     * 通し番号（年×12＋月）の範囲を、月ごとに、得意先ごとの売上にする。
     *
     * @return array<int, array<string, float>>
     */
    private function monthlyTotals(string $departmentKey, int $fromOrd, int $toOrd, bool $consolidate): array
    {
        $out = [];
        for ($ord = $fromOrd; $ord <= $toOrd; $ord++) {
            $year = intdiv($ord - 1, 12);
            $month = $ord - $year * 12;
            $names = [];
            foreach ($this->queryService->clientTotalsForPeriod($departmentKey, $year, $month, $month, $consolidate) as $name => $v) {
                $names[(string) $name] = (float) $v['amount'];
            }
            $out[$ord] = $names;
        }

        return $out;
    }

    /** @return array{rows:array<int,array<string,mixed>>, total_count:int} */
    private function top(array $rows, callable $filter, string $field, string $direction, ?int $limit): array
    {
        $rows = array_values(array_filter($rows, $filter));
        $sign = $direction === 'asc' ? 1 : -1;
        usort($rows, fn ($a, $b) => $sign * ($a[$field] <=> $b[$field]) ?: strcmp($a['label'], $b['label']));

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
