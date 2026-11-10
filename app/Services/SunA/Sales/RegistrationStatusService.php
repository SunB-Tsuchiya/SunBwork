<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\Department;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\User;

/**
 * 売上レポートの「データ登録状況」。年（暦年）× 月の登録状況、最終実績月、部・課別の件数と金額、直近の取込。
 * 空欄の月は「未登録」、0円は登録済み（amount=0 の行がある）。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class RegistrationStatusService
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $monthRows = Fact::query()
            ->selectRaw('sales_year, sales_month, SUM(amount) as amount, SUM(order_count) as order_count, COUNT(DISTINCT client_key) as client_count')
            ->groupBy('sales_year', 'sales_month')
            ->orderBy('sales_year')->orderBy('sales_month')
            ->get()
            ->groupBy('sales_year');

        $yearClientRows = Fact::query()
            ->selectRaw('sales_year, COUNT(DISTINCT client_key) as client_count')
            ->groupBy('sales_year')
            ->pluck('client_count', 'sales_year');

        $deptRows = Fact::query()
            ->selectRaw('sales_year, department_key, section_key, SUM(amount) as amount, COUNT(DISTINCT client_key) as client_count')
            ->groupBy('sales_year', 'department_key', 'section_key')
            ->get()
            ->groupBy('sales_year');

        $departments = Department::query()->orderBy('sort_order')->get();
        $labels = $departments->pluck('label', 'key')->all();
        $order = $departments->pluck('sort_order', 'key')->all();

        $years = [];
        foreach ($monthRows as $year => $rows) {
            $byMonth = $rows->keyBy('sales_month');
            $months = [];
            foreach (range(1, 12) as $m) {
                $r = $byMonth->get($m);
                $months[] = [
                    'month' => $m,
                    'registered' => $r !== null,
                    'amount' => $r ? (float) $r->amount : null,
                    'order_count' => $r ? (int) $r->order_count : null,
                    'client_count' => $r ? (int) $r->client_count : 0,
                ];
            }
            $registered = array_filter($months, fn ($m) => $m['registered']);

            $years[] = [
                'year' => (int) $year,
                'months' => $months,
                'last_month' => $registered ? max(array_column($registered, 'month')) : null,
                'registered_months' => count($registered),
                'client_count' => (int) ($yearClientRows[$year] ?? 0),
                'total_amount' => (float) $rows->sum('amount'),
                'total_orders' => (int) $rows->sum('order_count'),
                'by_department' => $this->departmentBreakdown($deptRows->get($year, collect()), $labels, $order),
            ];
        }

        $last = Import::query()->orderByDesc('imported_at')->orderByDesc('id')->first();
        $importerName = $last ? User::find($last->imported_by)?->name : null;

        return [
            'has_data' => $years !== [],
            'years' => $years,
            'last_registered' => $this->lastRegistered($years),
            'last_import' => $last ? [
                'id' => $last->id,
                'original_filename' => $last->original_filename,
                'imported_at' => $last->imported_at?->toIso8601String(),
                'imported_by_name' => $importerName ?? '（不明）',
                'row_count' => $last->row_count,
            ] : null,
        ];
    }

    /**
     * 部・課別の件数と金額（部の下に、課を持つ部は課を並べる）。
     *
     * @return array<int, array{department_key:string, section_key:?string, label:string, client_count:int, amount:float}>
     */
    private function departmentBreakdown($rows, array $labels, array $order): array
    {
        return $rows->map(fn ($r) => [
            'department_key' => $r->department_key,
            'section_key' => $r->section_key,
            'label' => ($labels[$r->department_key] ?? $r->department_key) . ($r->section_key ? ' ' . ($labels[$r->section_key] ?? $r->section_key) : ''),
            'client_count' => (int) $r->client_count,
            'amount' => (float) $r->amount,
            '_order' => [$order[$r->department_key] ?? 999, $order[$r->section_key ?? ''] ?? 0],
        ])->sortBy('_order')->map(function ($r) {
            unset($r['_order']);

            return $r;
        })->values()->all();
    }

    /** 最終実績月（登録済みの最後の年・月）。無ければ null */
    private function lastRegistered(array $years): ?array
    {
        for ($i = count($years) - 1; $i >= 0; $i--) {
            if ($years[$i]['last_month'] !== null) {
                return ['year' => $years[$i]['year'], 'month' => $years[$i]['last_month']];
            }
        }

        return null;
    }
}
