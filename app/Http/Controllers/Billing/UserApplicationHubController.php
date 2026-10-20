<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\TransportExpense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** ユーザー用の「申請」ハブ（全ロールが、自分の分を申請する入口）。今は交通費。 */
class UserApplicationHubController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $month = now()->format('Y-m');

        $current = TransportExpense::where('user_id', $userId)->where('billing_month', $month)->first(['id', 'status', 'total_amount']);
        // 差戻しされていて、直して再申請が必要な月
        $returned = TransportExpense::where('user_id', $userId)->where('status', TransportExpense::STATUS_RETURNED)
            ->orderBy('billing_month')->pluck('billing_month')->all();

        return Inertia::render('Billing/Applications/UserIndex', [
            'month'        => $month,
            'current'      => $current ? ['status' => $current->status, 'total_amount' => $current->total_amount] : null,
            'returned'     => $returned,
            'statusLabels' => TransportExpense::STATUS_LABELS,
        ]);
    }
}
