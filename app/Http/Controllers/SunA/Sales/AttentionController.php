<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\Analysis\AttentionService;
use App\Services\SunA\Sales\Analysis\DepartmentFilter;
use App\Services\SunA\Sales\Analysis\PeriodResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 売上レポートの注目ポイント（サンエー印刷。SUNASALES_PLAN1.md §20）。画面と JSON API。
 * ルートは suna_sales_report:view で保護（許可された人・サンエー印刷のみ）。
 */
class AttentionController extends Controller
{
    public function __construct(
        private AttentionService $service,
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
    ) {
    }

    public function index()
    {
        return Inertia::render('SunA/Sales/Attention', [
            'departmentOptions' => $this->departments->options(),
            'years' => $this->periods->years(),
            'initialYear' => $this->periods->latestYear(),
            'dormantOptions' => AttentionService::DORMANT_MONTHS,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1990', 'max:2100'],
            'dept' => ['nullable', 'string', 'max:50'],
            'dormant_months' => ['nullable', 'integer', Rule::in(AttentionService::DORMANT_MONTHS)],
        ]);
        $dept = $data['dept'] ?? DepartmentFilter::ALL;
        abort_unless($this->departments->isValid($dept), 422, '部門の指定が正しくありません。');

        return response()->json($this->service->summary((int) $data['year'], $dept, (int) ($data['dormant_months'] ?? 6)));
    }
}
