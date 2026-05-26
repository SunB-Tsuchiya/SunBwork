<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExpenseController extends Controller
{
    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    public function index(Request $request): Response
    {
        $user = $request->user()->load('department');

        // 未請求のみ（billing_request_id が null）
        $unbilledExpenses = TransportExpense::where('user_id', $user->id)
            ->whereNull('billing_request_id')
            ->with('items')
            ->orderByDesc('billing_date')
            ->get();

        // デフォルト請求期間（締め日11日: 先月12日〜今月11日）
        $today = now();
        $defaultPeriodEnd   = $today->copy()->day(11)->format('Y-m-d');
        $defaultPeriodStart = $today->copy()->subMonth()->day(12)->format('Y-m-d');

        return Inertia::render('SuperAdmin/Billing/Transport/Index', [
            'authDepartmentName'  => $user->department?->name ?? '',
            'departmentCodes'     => TransportExpense::DEPARTMENT_CODES,
            'purposes'            => TransportExpenseItem::PURPOSE_LABELS,
            'unbilledExpenses'    => $unbilledExpenses,
            'defaultPeriodStart'  => $defaultPeriodStart,
            'defaultPeriodEnd'    => $defaultPeriodEnd,
            'newBillingId'        => session('newBillingId'),
            'newBillingTotal'     => session('newBillingTotal'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'billing_date'            => 'required|date',
            'department_code'         => 'required|integer|in:0,10,20,30,50',
            'items'                   => 'required|array|min:1',
            'items.*.sort_order'      => 'required|integer',
            'items.*.occurrence_date' => 'nullable|date',
            'items.*.destination'     => 'nullable|string|max:100',
            'items.*.purpose'         => 'required|in:round_trip,outbound,return,direct_home,other',
            'items.*.purpose_text'    => 'nullable|string|max:100',
            'items.*.station_from'    => 'nullable|string|max:100',
            'items.*.station_to'      => 'nullable|string|max:100',
            'items.*.fare_type'       => 'required|in:ic,ticket',
            'items.*.amount'          => 'required|integer|min:0',
        ]);

        $user        = $request->user()->load('department');
        $billingDate = Carbon::parse($validated['billing_date']);

        $expense = TransportExpense::create([
            'user_id'         => $user->id,
            'department_id'   => $user->department_id,
            'department_code' => $validated['department_code'],
            'billing_date'    => $billingDate->toDateString(),
            'billing_month'   => $billingDate->format('Y-m'),
            'total_amount'    => collect($validated['items'])->sum('amount'),
            'status'          => 'draft',
        ]);

        foreach ($validated['items'] as $item) {
            $expense->items()->create($item);
        }

        return redirect()->route('superadmin.billing.transport.index')
            ->with('success', '交通費申請を保存しました。');
    }

    public function show(TransportExpense $expense): Response
    {
        $expense->load(['user.department', 'items']);

        return Inertia::render('SuperAdmin/Billing/Transport/Index', [
            'authDepartmentName' => $expense->user->department?->name ?? '',
            'departmentCodes'    => TransportExpense::DEPARTMENT_CODES,
            'purposes'           => TransportExpenseItem::PURPOSE_LABELS,
            'expenses'           => [$expense],
            'editExpense'        => $expense,
        ]);
    }

    public function update(Request $request, TransportExpense $expense)
    {
        $validated = $request->validate([
            'billing_date'            => 'required|date',
            'department_code'         => 'required|integer|in:0,10,20,30,50',
            'items'                   => 'required|array|min:1',
            'items.*.sort_order'      => 'required|integer',
            'items.*.occurrence_date' => 'nullable|date',
            'items.*.destination'     => 'nullable|string|max:100',
            'items.*.purpose'         => 'required|in:round_trip,outbound,return,direct_home,other',
            'items.*.purpose_text'    => 'nullable|string|max:100',
            'items.*.station_from'    => 'nullable|string|max:100',
            'items.*.station_to'      => 'nullable|string|max:100',
            'items.*.fare_type'       => 'required|in:ic,ticket',
            'items.*.amount'          => 'required|integer|min:0',
        ]);

        $billingDate = Carbon::parse($validated['billing_date']);

        $expense->update([
            'department_code' => $validated['department_code'],
            'billing_date'    => $billingDate->toDateString(),
            'billing_month'   => $billingDate->format('Y-m'),
            'total_amount'    => collect($validated['items'])->sum('amount'),
        ]);

        $expense->items()->delete();
        foreach ($validated['items'] as $item) {
            $expense->items()->create($item);
        }

        return redirect()->route('superadmin.billing.transport.index')
            ->with('success', '交通費申請を更新しました。');
    }

    public function destroy(TransportExpense $expense)
    {
        $expense->delete();

        return redirect()->route('superadmin.billing.transport.index')
            ->with('success', '交通費申請を削除しました。');
    }

    // ---------------------------------------------------------------
    // 出力
    // ---------------------------------------------------------------

    public function exportExcel(TransportExpense $expense)
    {
        $expense->load(['user.department', 'items']);

        $spreadsheet = $this->buildFilledSpreadsheet($expense);
        $filename    = $this->buildFilename($expense, 'xlsx');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }

    public function exportPdf(TransportExpense $expense)
    {
        $expense->load(['user.department', 'items']);

        $filename = $this->buildFilename($expense, 'pdf');

        $pdf = Pdf::loadView('billing.transport.expense_pdf', [
            'expense'         => $expense,
            'departmentCodes' => TransportExpense::DEPARTMENT_CODES,
            'deptCodeCells'   => [
                0  => ['D4', 'E4'],
                10 => ['H4', 'I4'],
                20 => ['D5', 'E5'],
                30 => ['H5', 'I5'],
                50 => ['L5', 'M5'],
            ],
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    // ---------------------------------------------------------------
    // テンプレート流し込み（Excel・PDF共通）
    // ---------------------------------------------------------------

    private function buildFilledSpreadsheet(TransportExpense $expense): Spreadsheet
    {
        $templatePath = base_path('z_instructions/SB_trains.xlsx');

        $reader      = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($templatePath);
        $sheet       = $spreadsheet->getActiveSheet();

        // --- タイトル（「サンプル」を除去）---
        $sheet->setCellValue('A1', '交通費金銭請求伝票');

        // --- ヘッダー: 請求日 ---
        $date       = Carbon::parse($expense->billing_date);
        $reiwaYear  = $date->year - 2018;
        $dateStr    = "令和{$reiwaYear}年{$date->month}月{$date->day}日";
        $this->setRedCell($sheet, 'D3', $dateStr);

        // --- ヘッダー: 所属・氏名 ---
        $this->setRedCell($sheet, 'U3', $expense->user->department?->name ?? '');
        $this->setRedCell($sheet, 'U4', $expense->user->name);

        // --- 部門コード: 選択中を太字・下線で強調 ---
        $deptCodeCells = [
            0  => ['D4', 'E4'],
            10 => ['H4', 'I4'],
            20 => ['D5', 'E5'],
            30 => ['H5', 'I5'],
            50 => ['L5', 'M5'],
        ];
        foreach ($deptCodeCells as $code => [$numCell, $nameCell]) {
            if ((int) $code === (int) $expense->department_code) {
                foreach ([$numCell, $nameCell] as $c) {
                    $sheet->getStyle($c)->getFont()
                        ->setBold(true)
                        ->setUnderline(Font::UNDERLINE_SINGLE);
                }
            }
        }

        // --- サンプルデータ行を消去（行7・8）---
        foreach ([7, 8] as $row) {
            foreach (['A', 'D', 'J', 'O', 'T', 'X'] as $col) {
                $sheet->setCellValue($col . $row, '');
            }
        }

        // --- 明細データを流し込み（行7〜31、最大25行）---
        $items       = $expense->items->values();
        $dataStart   = 7;
        $maxDataRows = 25;

        for ($i = 0; $i < min($items->count(), $maxDataRows); $i++) {
            $item = $items[$i];
            $row  = $dataStart + $i;

            // 発生日（M月D日）
            if ($item->occurrence_date) {
                $d = Carbon::parse($item->occurrence_date);
                $this->setRedCell($sheet, "A{$row}", "{$d->month}月{$d->day}日");
            }

            // 行先
            $this->setRedCell($sheet, "D{$row}", $item->destination ?? '');

            // 用件（往復のとき IC/切符も括弧付きで添える）
            $purposeLabel = $item->purpose_label;
            if ($item->fare_type === 'ic') {
                $purposeLabel .= '(IC)';
            }
            $this->setRedCell($sheet, "J{$row}", $purposeLabel);

            // 区間: 出発 ― 到着（S列は元テンプレートの「－」を保持）
            $this->setRedCell($sheet, "O{$row}", $item->station_from ?? '');
            $this->setRedCell($sheet, "T{$row}", $item->station_to  ?? '');

            // 金額
            if ($item->amount > 0) {
                $this->setRedCell($sheet, "X{$row}", $item->amount);
                $sheet->getStyle("X{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // --- 合計（X32:Z32） ---
        $this->setRedCell($sheet, 'X32', $expense->total_amount);
        $sheet->getStyle('X32')->getNumberFormat()->setFormatCode('#,##0');

        // --- モノクロ化: 全セルを黒文字・図形（テンプレートの赤丸など）を削除 ---
        $sheet->getStyle('A1:Z45')->getFont()->getColor()->setRGB('000000');
        $sheet->getDrawingCollection()->exchangeArray([]);

        return $spreadsheet;
    }

    // セルに値をセット（黒文字）
    private function setRedCell($sheet, string $cell, $value): void
    {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getFont()->getColor()->setRGB('000000');
    }

    private function buildFilename(TransportExpense $expense, string $ext): string
    {
        return '交通費請求_'
            . $expense->user->name . '_'
            . $expense->billing_date->format('Ym')
            . '.' . $ext;
    }
}
