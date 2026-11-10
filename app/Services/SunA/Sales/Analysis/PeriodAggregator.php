<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;

/**
 * 「ある年の、ある月の集まり」の集計（売上・受注件数・平均受注単価・取引社数・得意先別）。年次分析・左右比較で共通に使う。
 * 部門の絞り込みと、名寄せ（EffectiveClient）を反映する。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class PeriodAggregator
{
    public function __construct(private DepartmentFilter $departments)
    {
    }

    /**
     * 期間内の合計（売上・受注件数・平均受注単価・取引社数＝期間の売上が0円を超える得意先の数。名寄せ後）。
     *
     * @param  array<int,int>  $months
     * @return array{amount:float, order_count:int, avg_order_price:float|int|null, client_count:int}
     */
    public function totals(int $year, array $months, string $dept): array
    {
        if ($months === []) {
            return ['amount' => 0.0, 'order_count' => 0, 'avg_order_price' => null, 'client_count' => 0];
        }
        $base = $this->facts($year, $months, $dept);
        $sum = (clone $base)->selectRaw('COALESCE(SUM(suna_sl_facts.amount),0) as amount, COALESCE(SUM(suna_sl_facts.order_count),0) as orders')->first();
        $clients = (clone $base)->selectRaw(EffectiveClient::KEY . ' as ck')->groupBy('ck')->havingRaw('SUM(suna_sl_facts.amount) > 0')->get()->count();
        $amount = (float) $sum->amount;
        $orders = (int) $sum->orders;

        return [
            'amount' => $amount,
            'order_count' => $orders,
            'avg_order_price' => $orders > 0 ? round($amount / $orders) : null,
            'client_count' => $clients,
        ];
    }

    /**
     * 得意先（名寄せ後）ごとの期間の売上。
     *
     * @param  array<int,int>  $months
     * @return array<string, array{name:string, amount:float}> 実効キー → 名前と売上
     */
    public function clientTotals(int $year, array $months, string $dept): array
    {
        if ($months === []) {
            return [];
        }
        $out = [];
        foreach ($this->facts($year, $months, $dept)
            ->selectRaw(EffectiveClient::KEY . ' as ck, ' . EffectiveClient::NAME . ' as name, SUM(suna_sl_facts.amount) as amount')
            ->groupBy('ck')->get() as $r) {
            $out[$r->ck] = ['name' => (string) $r->name, 'amount' => (float) $r->amount];
        }

        return $out;
    }

    /**
     * 部門（部・課）ごとの売上。部門の絞り込みの影響は受けない（全部門の内訳）。
     *
     * @param  array<int,int>  $months
     * @return array<int, array{department_key:string, section_key:?string, amount:float}>
     */
    public function departmentAmounts(int $year, array $months): array
    {
        if ($months === []) {
            return [];
        }

        return Fact::query()->where('sales_year', $year)->whereIn('sales_month', $months)
            ->selectRaw('department_key, section_key, SUM(amount) as amount')->groupBy('department_key', 'section_key')->get()
            ->map(fn ($r) => ['department_key' => (string) $r->department_key, 'section_key' => $r->section_key, 'amount' => (float) $r->amount])->all();
    }

    /** 名寄せ後の得意先で絞り込み・結合した売上の行のクエリ */
    private function facts(int $year, array $months, string $dept)
    {
        $q = EffectiveClient::join(Fact::query())->where('sales_year', $year)->whereIn('sales_month', $months);

        return $this->departments->apply($q, $dept);
    }
}
