<?php

namespace App\Services\Billing\Transport;

use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use App\Models\TransportExpenseItemCompanion;
use App\Models\TransportFare;
use Illuminate\Support\Collection;

/**
 * 同行者の突き合わせ（総務・経理の確認用）。請求は、同行した二人がそれぞれ別に出す。
 *  - 「選んだ側」: この申請の行で同行者に選んだ人の、同じ日付・同じ行先の行を探して並べる
 *  - 「選ばれた側」: 他の人が、この申請の本人を同行者に選んでいる行（日付・行先が同じもの）を、情報として出す（本人が相手を選んでいなくても出す）
 * 判定: match（区間も金額も同じ）／amount_diff（区間は同じで金額が違う）／route_diff（区間が違う。家の最寄り駅が違うなど、正当なこともあるので確認扱い）／
 *       candidate（同じ日付で行先だけ違う行）／none（同じ日付の行がない）／no_expense（その月の申請がない）／draft（まだ下書き。中身は見せない）
 */
class TransportCompanionMatcher
{
    public function __construct(private TransportFareChecker $checker)
    {
    }

    /**
     * @return array<int, array{companions: array, named_by: array}> 明細 ID => 突き合わせ結果
     */
    public function forExpense(TransportExpense $expense): array
    {
        $expense->loadMissing(['items.companions.user:id,name']);
        $items = $expense->items;
        $out = [];
        foreach ($items as $it) {
            $out[$it->id] = ['companions' => [], 'named_by' => []];
        }

        // ── 選んだ側 ──
        $companionIds = $items->flatMap(fn ($it) => $it->companions->pluck('user_id'))->unique()->values();
        $theirs = $companionIds->isEmpty() ? collect() : TransportExpense::whereIn('user_id', $companionIds)
            ->where('billing_month', $expense->billing_month)->with('items')->get()->keyBy('user_id');
        $theirChecks = [];
        foreach ($theirs as $uid => $te) {
            if ($te->status !== TransportExpense::STATUS_DRAFT) {
                $theirChecks[$uid] = collect($this->checker->check($te, false)['items'])->keyBy('id');
            }
        }
        foreach ($items as $it) {
            foreach ($it->companions as $c) {
                $out[$it->id]['companions'][] = $this->matchOne($it, $c->user_id, $c->user?->name ?? '（退職・削除）', $theirs->get($c->user_id), $theirChecks[$c->user_id] ?? collect());
            }
        }

        // ── 選ばれた側 ──
        $named = TransportExpenseItemCompanion::query()
            ->where('user_id', $expense->user_id)
            ->whereHas('item.expense', fn ($q) => $q->where('billing_month', $expense->billing_month)
                ->where('user_id', '!=', $expense->user_id)
                ->where('status', '!=', TransportExpense::STATUS_DRAFT)
                ->when($expense->company_id, fn ($qq) => $qq->where('company_id', $expense->company_id)))
            ->with(['item.expense.user:id,name'])
            ->get();
        foreach ($items as $it) {
            $d = $it->occurrence_date?->toDateString();
            if (!$d) {
                continue;
            }
            foreach ($named as $n) {
                $row = $n->item;
                if (!$row || $row->occurrence_date?->toDateString() !== $d) {
                    continue;
                }
                $exact = self::normText($row->destination) === self::normText($it->destination);
                $out[$it->id]['named_by'][] = [
                    'applicant_id'   => $row->expense->user_id,
                    'applicant_name' => $row->expense->user?->name ?? '（退職・削除）',
                    'expense_id'     => $row->expense->id,
                    'status'         => $row->expense->status,
                    'verdict'        => $exact ? $this->verdict($it, $row) : 'candidate',
                    'row'            => $this->rowInfo($row, null),
                ];
            }
        }

        return $out;
    }

    /** 同行者 1 人ぶん */
    private function matchOne(TransportExpenseItem $it, int $userId, string $name, ?TransportExpense $theirs, Collection $checks): array
    {
        $base = ['user_id' => $userId, 'name' => $name, 'verdict' => 'no_expense', 'status' => null, 'expense_id' => null, 'rows' => []];
        if (!$theirs) {
            return $base;
        }
        $base['status'] = $theirs->status;
        $base['expense_id'] = $theirs->status === TransportExpense::STATUS_DRAFT ? null : $theirs->id;
        if ($theirs->status === TransportExpense::STATUS_DRAFT) {
            $base['verdict'] = 'draft';   // 下書きは、管理側に見せない
            return $base;
        }

        $d = $it->occurrence_date?->toDateString();
        $sameDay = $d ? $theirs->items->filter(fn ($r) => $r->occurrence_date?->toDateString() === $d) : collect();
        if ($sameDay->isEmpty()) {
            $base['verdict'] = 'none';
            return $base;
        }
        $exact = $sameDay->filter(fn ($r) => self::normText($r->destination) === self::normText($it->destination));
        if ($exact->isEmpty()) {
            $base['verdict'] = 'candidate';
            $base['rows'] = $sameDay->map(fn ($r) => $this->rowInfo($r, $checks->get($r->id)))->values()->all();
            return $base;
        }

        $rank = ['match' => 3, 'amount_diff' => 2, 'route_diff' => 1];
        $best = null;
        foreach ($exact as $r) {
            $v = $this->verdict($it, $r);
            $base['rows'][] = $this->rowInfo($r, $checks->get($r->id)) + ['verdict' => $v];
            if ($best === null || $rank[$v] > $rank[$best]) {
                $best = $v;
            }
        }
        $base['verdict'] = $best;
        return $base;
    }

    /** 同じ日付・同じ行先の二つの行の比べ方 */
    private function verdict(TransportExpenseItem $a, TransportExpenseItem $b): string
    {
        if (TransportFare::routeKey($a->station_from, $a->station_to) !== TransportFare::routeKey($b->station_from, $b->station_to)) {
            return 'route_diff';
        }
        return (int) $a->amount === (int) $b->amount ? 'match' : 'amount_diff';
    }

    private function rowInfo(TransportExpenseItem $r, ?array $check): array
    {
        return [
            'id'            => $r->id,
            'destination'   => $r->destination,
            'purpose_label' => $r->purpose_label,
            'station_from'  => $r->station_from,
            'station_to'    => $r->station_to,
            'via_stations'  => $r->via_stations ?? [],
            'fare_type'     => $r->fare_type,
            'amount'        => (int) $r->amount,
            'base_amount'   => $r->auto_double ? (int) $r->base_amount : null,
            'fare_state'    => $check['fare']['state'] ?? null,
            'fare_expected' => $check['fare']['expected'] ?? null,
        ];
    }

    /** 行先の比較用: 全角半角・空白・大文字小文字のゆれをそろえる */
    public static function normText(?string $s): string
    {
        return mb_strtolower(preg_replace('/[\s　]+/u', '', mb_convert_kana((string) $s, 'KVas', 'UTF-8')));
    }
}
