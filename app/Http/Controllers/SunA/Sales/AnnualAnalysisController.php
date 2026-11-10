<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\Analysis\AnnualAnalysisService;
use App\Services\SunA\Sales\Analysis\DepartmentFilter;
use App\Services\SunA\Sales\Analysis\PeriodResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 売上レポートの年次分析（サンエー印刷）。画面と、グラフ・ランキング用の JSON API。
 * ルートは suna_sales_report:view で保護（許可された人・サンエー印刷のみ。会社のコンテキストはミドルウェアが設定）。
 */
class AnnualAnalysisController extends Controller
{
    public function __construct(
        private AnnualAnalysisService $service,
        private PeriodResolver $periods,
        private DepartmentFilter $departments,
    ) {
    }

    public function index()
    {
        $years = $this->periods->years();

        return Inertia::render('SunA/Sales/Annual', [
            'departmentOptions' => $this->departments->options(),
            'years' => $years,
            'initialYear' => $this->periods->latestYear(),
        ]);
    }

    public function summary(Request $request)
    {
        $data = $this->validated($request, ['years' => ['nullable', 'integer', 'min:1', 'max:5']]);

        return response()->json($this->service->summary((int) $data['year'], $data['dept'], (int) ($data['years'] ?? 2)));
    }

    public function clients(Request $request)
    {
        $data = $this->validated($request, [
            'mode' => ['nullable', Rule::in(['all', 'increase', 'decrease', 'new', 'ended'])],
            'sort' => ['nullable', Rule::in(['amount', 'diff'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->service->clients(
            (int) $data['year'], $data['dept'], $data['mode'] ?? 'all', $data['sort'] ?? 'amount', $data['direction'] ?? 'desc',
            (string) ($data['keyword'] ?? ''), (int) ($data['page'] ?? 1), (int) ($data['limit'] ?? 10),
        ));
    }

    private function validated(Request $request, array $extra): array
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:1990', 'max:2100'], 'dept' => ['nullable', 'string', 'max:50']] + $extra);
        $data['dept'] = $data['dept'] ?? DepartmentFilter::ALL;
        abort_unless($this->departments->isValid($data['dept']), 422, '部門の指定が正しくありません。');

        return $data;
    }
}
