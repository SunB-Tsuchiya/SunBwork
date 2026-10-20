<?php

namespace App\Services\Billing\Transport;

use App\Models\TransportExpense;
use App\Models\TransportFare;

/** 申請されたとき、運賃マスタに無い経路を「未検査」として自動で溜める（総務・経理が、使われた回数の多い順に確認できる） */
class TransportFareStock
{
    public function recordFromExpense(TransportExpense $expense): void
    {
        if (!$expense->company_id) {
            return;
        }
        $expense->loadMissing('items');

        foreach ($expense->items as $it) {
            $a = trim((string) $it->station_from);
            $b = trim((string) $it->station_to);
            $key = TransportFare::routeKey($a, $b);
            $same = TransportFare::normalizeStation($a) === TransportFare::normalizeStation($b);
            if ($a === '' || $b === '' || $same) {
                continue;
            }

            $fare = TransportFare::firstOrNew(['company_id' => $expense->company_id, 'route_key' => $key]);
            if (!$fare->exists) {
                $fare->fill(['station_a' => $a, 'station_b' => $b, 'status' => TransportFare::STATUS_UNVERIFIED, 'source' => 'stock']);
            }
            // 検査済みでない経路には、申請の金額を参考値として残す（確認の手がかり）
            if ($fare->status !== TransportFare::STATUS_VERIFIED && ($one = TransportFareChecker::oneWay($it)) !== null) {
                $fare->reported_fare = $one;
                $fare->reported_fare_type = $it->fare_type;
            }
            $fare->use_count = (int) $fare->use_count + 1;
            $fare->last_used_at = now();
            $fare->save();
        }
    }
}
