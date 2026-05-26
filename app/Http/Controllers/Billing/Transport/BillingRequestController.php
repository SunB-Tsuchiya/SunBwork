<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportBillingRequest;
use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BillingRequestController extends Controller
{
    /** 請求済み一覧（JobBox風） */
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $month = $request->input('month', now()->format('Y-m'));

        // 月一覧（過去12か月）
        $months = collect(range(0, 11))
            ->map(fn($i) => now()->subMonths($i)->format('Y-m'))
            ->values();

        // 当月に請求済みの billing_requests（period_end が当月内）
        $billings = TransportBillingRequest::where('user_id', $user->id)
            ->whereRaw("DATE_FORMAT(period_end, '%Y-%m') = ?", [$month])
            ->with(['expenses.items'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => [
                'id'           => $b->id,
                'period_start' => $b->period_start->format('Y/m/d'),
                'period_end'   => $b->period_end->format('Y/m/d'),
                'total_amount' => $b->total_amount,
                'created_at'   => $b->created_at->format('Y/m/d H:i'),
                'expense_count'=> $b->expenses->count(),
                'expenses'     => $b->expenses->map(fn($exp) => [
                    'id'              => $exp->id,
                    'billing_date'    => $exp->billing_date->format('Y/m/d'),
                    'department_code' => $exp->department_code,
                    'total_amount'    => $exp->total_amount,
                    'items'           => $exp->items->map(fn($item) => [
                        'id'              => $item->id,
                        'occurrence_date' => $item->occurrence_date?->format('m/d'),
                        'destination'     => $item->destination,
                        'purpose_label'   => $item->purpose_label,
                        'station_from'    => $item->station_from,
                        'station_to'      => $item->station_to,
                        'fare_type'       => $item->fare_type,
                        'amount'          => $item->amount,
                    ]),
                ]),
            ]);

        return Inertia::render('SuperAdmin/Billing/Transport/Billed', [
            'billings'       => $billings,
            'month'          => $month,
            'months'         => $months,
            'departmentCodes'=> TransportExpense::DEPARTMENT_CODES,
        ]);
    }

    /** 請求データ作成 */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $user  = $request->user();
        $start = Carbon::parse($validated['period_start'])->startOfDay();
        $end   = Carbon::parse($validated['period_end'])->endOfDay();

        // 期間内に発生日を持つ明細を含む未請求 expense を取得
        $expenses = TransportExpense::where('user_id', $user->id)
            ->whereNull('billing_request_id')
            ->whereHas('items', fn($q) =>
                $q->whereBetween('occurrence_date', [$start->toDateString(), $end->toDateString()])
            )
            ->get();

        if ($expenses->isEmpty()) {
            return back()->withErrors(['period' => '指定期間内に未請求データがありません。']);
        }

        $total = $expenses->sum('total_amount');

        $billing = TransportBillingRequest::create([
            'user_id'      => $user->id,
            'period_start' => $start->toDateString(),
            'period_end'   => $end->toDateString(),
            'total_amount' => $total,
        ]);

        $expenses->each(fn($exp) => $exp->update(['billing_request_id' => $billing->id]));

        return redirect()->route('superadmin.billing.transport.index')
            ->with('newBillingId',    $billing->id)
            ->with('newBillingTotal', $total)
            ->with('success', '請求データを作成しました（合計 ' . number_format($total) . '円）。');
    }

    /** 請求書PDF（mode=inline でブラウザ内閲覧・印刷、省略で DL） */
    public function exportPdf(Request $request, TransportBillingRequest $billing)
    {
        $billing->load(['user.department', 'expenses.items']);
        $filename = '交通費請求書_' . $billing->user->name
            . '_' . $billing->period_start->format('Ym')
            . '-' . $billing->period_end->format('Ym') . '.pdf';

        $pdf = Pdf::loadView('billing.transport.billing_pdf', [
            'billing'        => $billing,
            'departmentCodes'=> TransportExpense::DEPARTMENT_CODES,
        ])->setPaper('a4', 'portrait');

        return $request->query('mode') === 'inline'
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    /** 請求書Excel */
    public function exportExcel(TransportBillingRequest $billing)
    {
        $billing->load(['user.department', 'expenses.items']);

        $templatePath = base_path('z_instructions/SB_trains.xlsx');
        $reader       = IOFactory::createReader('Xlsx');
        $spreadsheet  = $reader->load($templatePath);
        $sheet        = $spreadsheet->getActiveSheet();

        $set = function (string $cell, $value) use ($sheet): void {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('000000');
        };

        // タイトル
        $sheet->setCellValue('A1', '交通費金銭請求書');

        // 期間ヘッダー
        $startD = $billing->period_start;
        $endD   = $billing->period_end;
        $set('D3', "令和" . ($startD->year - 2018) . "年{$startD->month}月{$startD->day}日"
            . " ～ 令和" . ($endD->year - 2018) . "年{$endD->month}月{$endD->day}日");
        $set('U3', $billing->user->department?->name ?? '');
        $set('U4', $billing->user->name);

        // 全 expenses の items を flatten して行に流し込み
        $allItems = $billing->expenses->flatMap(fn($exp) => $exp->items)->values();

        foreach ([7, 8] as $row) {
            foreach (['A', 'D', 'J', 'O', 'T', 'X'] as $col) {
                $sheet->setCellValue($col . $row, '');
            }
        }

        $dataStart = 7;
        foreach ($allItems->take(25) as $i => $item) {
            $row = $dataStart + $i;
            if ($item->occurrence_date) {
                $d = Carbon::parse($item->occurrence_date);
                $set("A{$row}", "{$d->month}月{$d->day}日");
            }
            $set("D{$row}", $item->destination ?? '');
            $set("J{$row}", $item->purpose_label);
            $set("O{$row}", $item->station_from ?? '');
            $set("T{$row}", $item->station_to   ?? '');
            if ($item->amount > 0) {
                $set("X{$row}", $item->amount);
                $sheet->getStyle("X{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        $set('X32', $billing->total_amount);
        $sheet->getStyle('X32')->getNumberFormat()->setFormatCode('#,##0');

        // モノクロ化: 全セルを黒文字・図形（テンプレートの赤丸など）を削除
        $sheet->getStyle('A1:Z45')->getFont()->getColor()->setRGB('000000');
        $sheet->getDrawingCollection()->exchangeArray([]);

        $filename = '交通費請求書_' . $billing->user->name
            . '_' . $billing->period_start->format('Ym') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }
}
