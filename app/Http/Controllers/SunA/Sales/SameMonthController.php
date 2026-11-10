<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\Analysis\DepartmentFilter;
use App\Services\SunA\Sales\Analysis\PeriodResolver;
use App\Services\SunA\Sales\Analysis\SameMonthService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** 売上レポートの同月比較（サンエー印刷）。権限・会社のコンテキストは AnnualAnalysisController と同じ（suna_sales_report:view）。 */
class SameMonthController extends Controller
{
    public function __construct(
        private SameMonthService $service,
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
    ) {
    }

    public function index()
    {
        $last = $this->periods->latestYear();
        $months = $this->service->monthsWithData();
        // 最初に開く月: 最終実績月（無ければ、登録のある最初の月）
        $initialMonth = $last !== null && $this->periods->months($last) !== [] ? max($this->periods->months($last)) : ($months[0] ?? 1);

        return Inertia::render('SunA/Sales/SameMonth', [
            'departmentOptions' => $this->departments->options(),
            'monthsWithData' => $months,
            'initialMonth' => $initialMonth,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'integer', 'min:1', 'max:12'], 'dept' => ['nullable', 'string', 'max:50']]);
        $dept = $data['dept'] ?? DepartmentFilter::ALL;
        abort_unless($this->departments->isValid($dept), 422, '部門の指定が正しくありません。');

        return response()->json($this->service->summary((int) $data['month'], $dept));
    }
}
