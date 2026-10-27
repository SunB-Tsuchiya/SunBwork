<?php

namespace App\Services\Billing\Transport;

use App\Models\ClerkCalendarHoliday;
use App\Models\ClerkCalendarYear;
use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use App\Models\TransportFare;
use App\Services\ClerkCalendarHolidays;
use Illuminate\Support\Carbon;

/**
 * 総務・経理の検算: 申請の明細ごとに、運賃マスタとの照合と、入力の内容だけで判定できる確認（重複・休日・金額の異常・前回との比較）を行う。
 * 照合の対象にできるのは、検査済みの運賃だけ。
 */
class TransportFareChecker
{
    /** @var array<int, array<string, TransportFare>> 会社 ID => [route_key => 運賃] */
    private array $fareCache = [];
    /** @var array<string, array<string, string>> "会社-年" => [日付 => 名称] */
    private array $holidayCache = [];

    public function __construct(private ClerkCalendarHolidays $holidays)
    {
    }

    /** 用件ごとの片道の回数（往復は 2、それ以外は 1） */
    public static function legs(string $purpose): int
    {
        return $purpose === 'round_trip' ? 2 : 1;
    }

    /** 申請の金額から、片道の金額を取り出す。わからなければ null（往復で、奇数の合計など） */
    public static function oneWay(TransportExpenseItem $it): ?int
    {
        if ($it->auto_double && $it->base_amount) {
            return (int) $it->base_amount;
        }
        if ($it->purpose === 'round_trip') {
            return $it->amount % 2 === 0 ? intdiv($it->amount, 2) : null;
        }
        return (int) $it->amount;
    }

    /**
     * 申請の確認結果。
     *
     * @param bool $full true なら、前回の同じ経路との金額の比較（本人の履歴の検索）も行う（詳細画面用。一覧は false で軽く）
     * @return array{items: array<int, array>, summary: array}
     */
    public function check(TransportExpense $expense, bool $full = true): array
    {
        $expense->loadMissing('items');
        $items = TransportExpenseExporter::sortedItems($expense);
        $fares = $this->fares((int) $expense->company_id);
        $previous = $full ? $this->previousFares($expense) : [];

        // 重複の判定用: 日付＋経路＋金額が同じ行、往復と、往路／帰路が同じ日に重なる行
        $sameCount = [];
        $roundDays = [];
        $halfDays = [];
        foreach ($items as $it) {
            $k = TransportFare::routeKey($it->station_from, $it->station_to);
            $d = $it->occurrence_date?->toDateString();
            $sameCount[$d . '|' . $k . '|' . $it->amount] = ($sameCount[$d . '|' . $k . '|' . $it->amount] ?? 0) + 1;
            if ($it->purpose === 'round_trip') {
                $roundDays[$d . '|' . $k] = true;
            } elseif (in_array($it->purpose, ['outbound', 'return'], true)) {
                $halfDays[$d . '|' . $k] = true;
            }
        }

        $out = [];
        $summary = ['total' => $items->count(), 'diff' => 0, 'none' => 0, 'unverified' => 0, 'warn_rows' => 0, 'unchecked' => 0, 'match' => 0,
            // 台帳の運賃で自動計算した合計（電卓の代わり）: 計算できた行の、台帳の合計と申請の合計。計算できない行（未検査）の件数と金額
            'computed_rows' => 0, 'expected_total' => 0, 'claimed_total' => 0, 'uncomputed_rows' => 0, 'uncomputed_amount' => 0];

        foreach ($items->values() as $i => $it) {
            $key = TransportFare::routeKey($it->station_from, $it->station_to);
            $date = $it->occurrence_date;
            $d = $date?->toDateString();
            $flags = [];

            // ── 区間の入力 ──
            $from = TransportFare::normalizeStation($it->station_from);
            $to = TransportFare::normalizeStation($it->station_to);
            $routeOk = $from !== '' && $to !== '';
            if (!$routeOk) {
                $flags[] = $this->flag('route', 'warn', '区間が未入力', '出発駅・到着駅のどちらかが空です。');
            } elseif ($from === $to) {
                $flags[] = $this->flag('route', 'warn', '出発と到着が同じ駅', "「{$it->station_from}」から「{$it->station_to}」になっています。");
            }

            // ── 重複 ──
            if ($routeOk && ($sameCount[$d . '|' . $key . '|' . $it->amount] ?? 0) > 1) {
                $flags[] = $this->flag('duplicate', 'warn', '重複の疑い', '同じ日・同じ区間・同じ金額の行が、ほかにもあります。');
            }
            if ($routeOk && $it->purpose === 'round_trip' && isset($halfDays[$d . '|' . $key])) {
                $flags[] = $this->flag('duplicate', 'warn', '往復と往路・帰路が重複', '同じ日・同じ区間に、往路または帰路の行もあります。');
            } elseif ($routeOk && in_array($it->purpose, ['outbound', 'return'], true) && isset($roundDays[$d . '|' . $key])) {
                $flags[] = $this->flag('duplicate', 'warn', '往復と往路・帰路が重複', '同じ日・同じ区間に、往復の行もあります。');
            }

            // ── 休日 ──
            if ($date) {
                $holiday = $this->holidayName((int) $expense->company_id, $date);
                if ($holiday) {
                    $flags[] = $this->flag('holiday', 'warn', '休日', "{$d} は {$holiday} です。");
                }
            }

            // ── 金額の異常 ──
            $max = (int) config('transport.max_row_amount', 5000);
            if ($it->amount <= 0) {
                $flags[] = $this->flag('amount', 'warn', '金額が 0 円', '金額が入っていません。');
            } elseif ($max > 0 && $it->amount > $max) {
                $flags[] = $this->flag('amount', 'warn', '金額が大きい', number_format($it->amount) . '円（目安の上限 ' . number_format($max) . '円）を超えています。');
            }

            // ── 運賃マスタとの照合 ──
            $fare = ['state' => 'skip', 'expected' => null, 'registered' => null, 'diff' => null, 'fare_id' => null, 'recheck' => false, 'status' => null, 'reported' => null];
            if ($routeOk && $from !== $to) {
                $r = $this->routeFare($it, $fares);
                $fare['state'] = $r['state'];
                $fare['fare_id'] = $r['fare_id'];
                $fare['status'] = $r['status'];
                $fare['recheck'] = $r['recheck'];
                $fare['reported'] = $r['reported'];
                $fare['legs'] = $r['legs'];
                $fare['via_used'] = $r['via_used'];
                if ($r['one'] !== null) {
                    $expected = $r['one'] * (self::legs($it->purpose) === 2 || ($it->purpose === 'other' && $it->auto_double) ? 2 : 1);
                    $fare['registered'] = $r['one'];
                    $fare['expected'] = $expected;
                    $fare['diff'] = $it->amount - $expected;
                    $fare['state'] = $it->amount === $expected ? 'match' : 'diff';
                }
            }

            // ── 前回の同じ経路との金額の比較（本人の履歴） ──
            if ($full && $routeOk && ($prev = $previous[$key] ?? null)) {
                $now = self::oneWay($it);
                if ($now !== null && $prev['one_way'] !== null && $now !== $prev['one_way']) {
                    $flags[] = $this->flag('previous', 'info', '前回と金額が違う',
                        "同じ区間の前回（{$prev['month']}）は片道 " . number_format($prev['one_way']) . '円、今回は片道 ' . number_format($now) . '円です。');
                }
            }

            if ($flags) {
                $summary['warn_rows'] += (int) (bool) array_filter($flags, fn ($f) => $f['level'] === 'warn');
            }
            if (in_array($fare['state'], ['match', 'diff'], true)) {
                $summary['computed_rows']++;
                $summary['expected_total'] += $fare['expected'];
                $summary['claimed_total'] += (int) $it->amount;
            } else {
                $summary['uncomputed_rows']++;
                $summary['uncomputed_amount'] += (int) $it->amount;
            }
            if ($fare['state'] === 'diff') $summary['diff']++;
            elseif ($fare['state'] === 'match') $summary['match']++;
            elseif ($fare['state'] === 'unverified') $summary['unverified']++;
            elseif ($fare['state'] === 'none') $summary['none']++;
            if (!$it->checked_at) $summary['unchecked']++;

            $out[] = ['index' => $i, 'id' => $it->id, 'fare' => $fare, 'flags' => $flags, 'checked' => (bool) $it->checked_at];
        }

        return ['items' => $out, 'summary' => $summary];
    }

    /**
     * 片道の運賃を台帳から求める。通しの区間（出発－到着）が検査済みならそれを使い、なければ、経由地で区切った区間の運賃を足す。
     * 戻り値: state（match/diff の判定前の none・unverified）、one（片道。求められなければ null）、legs（区間ごとの内訳）、via_used（区間の合計を使ったか）ほか
     */
    private function routeFare(TransportExpenseItem $it, array $fares): array
    {
        $out = ['state' => 'none', 'one' => null, 'fare_id' => null, 'status' => null, 'recheck' => false, 'reported' => null, 'legs' => [], 'via_used' => false];
        $through = $fares[TransportFare::routeKey($it->station_from, $it->station_to)] ?? null;
        if ($through) {
            $out['fare_id'] = $through->id;
            $out['status'] = $through->status;
            $out['recheck'] = $through->needsRecheck();
            $out['reported'] = $through->reported_fare;
            $one = $through->status === TransportFare::STATUS_VERIFIED ? $through->fareFor($it->fare_type) : null;
            if ($one !== null) {
                $out['one'] = $one;
                return $out;
            }
            $out['state'] = $through->status === TransportFare::STATUS_VERIFIED ? 'none' : 'unverified';   // 検査済みでも、この種別（IC/切符）の運賃が無い
        }

        // 経由地があれば、区間ごとに台帳から探して足す（通しが使えないとき）
        $points = $it->routePoints();
        if (count($points) <= 2) {
            return $out;
        }
        $sum = 0;
        $missing = false;
        $unverified = false;
        $recheck = false;
        for ($k = 0; $k < count($points) - 1; $k++) {
            $a = $points[$k];
            $b = $points[$k + 1];
            if (TransportFare::normalizeStation($a) === TransportFare::normalizeStation($b)) {
                continue;
            }
            $m = $fares[TransportFare::routeKey($a, $b)] ?? null;
            $leg = ['from' => $a, 'to' => $b, 'fare' => null, 'state' => 'none'];
            if ($m) {
                $f = $m->status === TransportFare::STATUS_VERIFIED ? $m->fareFor($it->fare_type) : null;
                if ($f !== null) {
                    $leg['fare'] = $f;
                    $leg['state'] = 'ok';
                    $sum += $f;
                    $recheck = $recheck || $m->needsRecheck();
                } else {
                    $leg['state'] = $m->status === TransportFare::STATUS_VERIFIED ? 'none' : 'unverified';
                    $unverified = $unverified || $leg['state'] === 'unverified';
                    $missing = $missing || $leg['state'] === 'none';
                }
            } else {
                $missing = true;
            }
            $out['legs'][] = $leg;
        }
        if (!$out['legs']) {
            return $out;
        }
        $out['via_used'] = true;
        if (!$missing && !$unverified) {
            $out['one'] = $sum;
            $out['recheck'] = $recheck;
            $out['state'] = 'match';
        } else {
            $out['state'] = $missing ? 'none' : 'unverified';
        }
        return $out;
    }

    private function flag(string $type, string $level, string $label, string $detail): array
    {
        return ['type' => $type, 'level' => $level, 'label' => $label, 'detail' => $detail];
    }

    /** 会社の運賃マスタ（route_key で引く）。同じリクエスト内では 1 回だけ読む */
    private function fares(int $companyId): array
    {
        return $this->fareCache[$companyId] ??= $companyId
            ? TransportFare::where('company_id', $companyId)->get()->keyBy('route_key')->all()
            : [];
    }

    /** 本人の、この申請より前の同じ経路の金額（経路ごとに最新 1 件） */
    private function previousFares(TransportExpense $expense): array
    {
        $out = [];
        TransportExpenseItem::query()
            ->join('transport_expenses', 'transport_expenses.id', '=', 'transport_expense_items.transport_expense_id')
            ->where('transport_expenses.user_id', $expense->user_id)
            ->where('transport_expenses.billing_month', '<', $expense->billing_month)
            ->where('transport_expenses.billing_month', '>=', Carbon::createFromFormat('Y-m-d', $expense->billing_month . '-01')->subMonths(12)->format('Y-m'))
            ->orderByDesc('transport_expenses.billing_month')->orderByDesc('transport_expense_items.occurrence_date')
            ->get(['transport_expense_items.*', 'transport_expenses.billing_month as _month'])
            ->each(function ($it) use (&$out) {
                $k = TransportFare::routeKey($it->station_from, $it->station_to);
                if ($k !== '|' && !isset($out[$k])) {
                    $out[$k] = ['one_way' => self::oneWay($it), 'month' => $it->_month];
                }
            });

        return $out;
    }

    /** 休日の名称（土曜・日曜・会社の休日カレンダーの祝日など）。休日でなければ null */
    private function holidayName(int $companyId, Carbon $date): ?string
    {
        $name = $this->holidayMap($companyId, $date->year)[$date->toDateString()] ?? null;
        if ($name) {
            return $name;
        }
        return match ($date->dayOfWeek) {
            Carbon::SATURDAY => '土曜日',
            Carbon::SUNDAY => '日曜日',
            default => null,
        };
    }

    /** その年の祝日（読み取りだけ。未初期化の年は、元の祝日データから読む。DB は書き換えない） */
    private function holidayMap(int $companyId, int $year): array
    {
        return $this->holidayCache["{$companyId}-{$year}"] ??= (function () use ($companyId, $year) {
            if ($companyId && ClerkCalendarYear::where('company_id', $companyId)->where('year', $year)->whereNotNull('initialized_at')->exists()) {
                return ClerkCalendarHoliday::where('company_id', $companyId)->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
                    ->get()->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();
            }
            try {
                return $this->holidays->defaults()['years'][$year] ?? [];
            } catch (\Throwable $e) {
                return [];
            }
        })();
    }
}
