<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Fact;

/**
 * 売上分析の「期間」の決め方（SUNASALES_PLAN1.md §15）。
 *  - 年は暦年（1月〜12月）。登録のある月は、その年に売上の行がある月（部門を問わない）。
 *  - 当年が途中でも、**登録済みの月だけ**を、前年の**同じ月**と比べる（途中の年が減って見えないように）。
 *  - 前年比が使えるのは、前の年にもデータがあるとき。**データの最初の年は使えない**（新規・終了も判定しない）。
 * 会社のコンテキスト（SunaCompanyContext）が必要。
 */
class PeriodResolver
{
    /** @return array<int,int> データのある年（昇順） */
    public function years(): array
    {
        return Fact::query()->distinct()->orderBy('sales_year')->pluck('sales_year')->map(fn ($y) => (int) $y)->all();
    }

    public function latestYear(): ?int
    {
        $years = $this->years();

        return $years === [] ? null : end($years);
    }

    /** @return array<int,int> その年に登録のある月（昇順） */
    public function months(int $year): array
    {
        return Fact::query()->where('sales_year', $year)->distinct()->orderBy('sales_month')->pluck('sales_month')->map(fn ($m) => (int) $m)->all();
    }

    /** @return array<int,int> 指定した月に登録のある年（昇順） */
    public function yearsWithMonth(int $month): array
    {
        return Fact::query()->where('sales_month', $month)->distinct()->orderBy('sales_year')->pluck('sales_year')->map(fn ($y) => (int) $y)->all();
    }

    /**
     * 分析する期間（その年の登録済みの月）と、表示用の言葉。
     *
     * @return array{year:int, months:array<int,int>, last_month:?int, is_partial:bool, label:string}
     */
    public function period(int $year): array
    {
        $months = $this->months($year);

        return [
            'year' => $year,
            'months' => $months,
            'last_month' => $months === [] ? null : max($months),
            'is_partial' => $months !== [] && count($months) < 12,
            'label' => $this->label($months),
        ];
    }

    /**
     * 前年との比較ができるか。最初の年、または前の年にデータが無いときは、できない。
     *
     * @return array{year:int, available:bool}
     */
    public function prior(int $year): array
    {
        $years = $this->years();
        $available = $years !== [] && $year > $years[0] && in_array($year - 1, $years, true);

        return ['year' => $year - 1, 'available' => $available];
    }

    /** @param array<int,int> $months */
    public function label(array $months): string
    {
        if ($months === []) {
            return '登録なし';
        }
        $contiguous = $months === range(1, max($months));
        if (count($months) === 12) {
            return '1〜12月（通年）';
        }

        return $contiguous
            ? '1〜' . max($months) . '月累計'
            : '登録済みの月（' . implode('・', $months) . '月）の累計';
    }
}
