<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseListController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));

        // 部署ごとにグループ化して申請を取得
        $expenses = TransportExpense::where('billing_month', $month)
            ->with(['user.department', 'items'])
            ->get();

        // 部署ごとにグループ化
        $grouped = $expenses->groupBy(function ($expense) {
            return $expense->user->department?->name ?? '部署未設定';
        })->map(function ($group, $deptName) {
            return [
                'department_name' => $deptName,
                'members'         => $group->groupBy('user_id')->map(function ($userExpenses) {
                    $user = $userExpenses->first()->user;
                    return [
                        'user_id'      => $user->id,
                        'user_name'    => $user->name,
                        'total_amount' => $userExpenses->sum('total_amount'),
                        'expenses'     => $userExpenses->map(function ($expense) {
                            return [
                                'id'              => $expense->id,
                                'billing_date'    => $expense->billing_date->format('Y/m/d'),
                                'department_code' => $expense->department_code,
                                'total_amount'    => $expense->total_amount,
                                'status'          => $expense->status,
                                'items'           => $expense->items->map(fn($item) => [
                                    'id'              => $item->id,
                                    'occurrence_date' => $item->occurrence_date?->format('m/d'),
                                    'destination'     => $item->destination,
                                    'purpose_label'   => $item->purpose_label,
                                    'station_from'    => $item->station_from,
                                    'station_to'      => $item->station_to,
                                    'fare_type'       => $item->fare_type,
                                    'amount'          => $item->amount,
                                ]),
                            ];
                        })->values(),
                    ];
                })->values(),
                'dept_total' => $group->sum('total_amount'),
            ];
        })->values();

        // 月のリスト（過去12か月）
        $months = collect(range(0, 11))->map(fn($i) => now()->subMonths($i)->format('Y-m'))->values();

        return Inertia::render('SuperAdmin/Billing/Transport/List', [
            'grouped'   => $grouped,
            'month'     => $month,
            'months'    => $months,
            'purposes'  => TransportExpenseItem::PURPOSE_LABELS,
        ]);
    }
}
