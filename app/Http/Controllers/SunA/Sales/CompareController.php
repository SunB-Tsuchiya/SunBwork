<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Models\SunA\Sales\Fact;
use App\Services\SunA\Sales\Analysis\CompareService;
use App\Services\SunA\Sales\Analysis\DepartmentFilter;
use App\Services\SunA\Sales\Analysis\PeriodResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** 売上レポートの左右比較（サンエー印刷。SUNASALES_PLAN1.md §18）。権限・会社のコンテキストは AnnualAnalysisController と同じ（suna_sales_report:view）。 */
class CompareController extends Controller
{
    public function __construct(
        private CompareService $service,
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
    ) {
    }

    public function index()
    {
        $years = $this->periods->years();
        $latest = $this->periods->latestYear();
        $lastMonth = $latest !== null ? max($this->periods->months($latest) ?: [1]) : 1;

        // 年・月の選択肢の目印（登録のある月）。年ごとの登録済みの月
        $monthsByYear = Fact::query()->select('sales_year', 'sales_month')->distinct()->get()
            ->groupBy('sales_year')->map(fn ($g) => $g->pluck('sales_month')->map(fn ($m) => (int) $m)->sort()->values()->all())->all();

        return Inertia::render('SunA/Sales/Compare', [
            'departmentOptions' => $this->departments->options(),
            'years' => $years,
            'monthsByYear' => (object) $monthsByYear,
            'latestYear' => $latest,
            'latestMonth' => $lastMonth,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['year', 'month'])],
            'year_a' => ['required', 'integer', 'min:1990', 'max:2100'],
            'year_b' => ['required', 'integer', 'min:1990', 'max:2100'],
            'month_a' => ['nullable', 'integer', 'min:1', 'max:12', Rule::requiredIf(fn () => $request->input('mode') === 'month')],
            'month_b' => ['nullable', 'integer', 'min:1', 'max:12', Rule::requiredIf(fn () => $request->input('mode') === 'month')],
            'dept' => ['nullable', 'string', 'max:50'],
        ]);
        $dept = $data['dept'] ?? DepartmentFilter::ALL;
        abort_unless($this->departments->isValid($dept), 422, '部門の指定が正しくありません。');

        $a = ['year' => (int) $data['year_a']] + ($data['mode'] === 'month' ? ['month' => (int) $data['month_a']] : []);
        $b = ['year' => (int) $data['year_b']] + ($data['mode'] === 'month' ? ['month' => (int) $data['month_b']] : []);

        return response()->json($this->service->summary($data['mode'], $a, $b, $dept));
    }
}
