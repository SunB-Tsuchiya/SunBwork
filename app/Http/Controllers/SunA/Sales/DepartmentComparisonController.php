<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\Analysis\DepartmentComparisonService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** 売上レポートの部門比較（サンエー印刷。SUNASALES_PLAN1.md §18）。権限・会社のコンテキストは AnnualAnalysisController と同じ（suna_sales_report:view）。 */
class DepartmentComparisonController extends Controller
{
    public function __construct(private DepartmentComparisonService $service)
    {
    }

    public function index()
    {
        return Inertia::render('SunA/Sales/Department');
    }

    public function summary(Request $request)
    {
        $data = $request->validate(['basis' => ['nullable', Rule::in(['same_month', 'year'])]]);

        return response()->json($this->service->summary($data['basis'] ?? 'same_month'));
    }
}
