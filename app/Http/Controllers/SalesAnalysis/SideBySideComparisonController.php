<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 部署共通・任意の期間A/B（年 または 年月）を選び、差額・増減率を比較する画面
 * （2026-09-04 Codexレビュー2回目 10.5節・PLAN1.md「6E. 左右比較 詳細設計」に基づき新規作成）。
 * 「同月前年」はUI側の入力補助（月対月モードのショートカット）として扱い、API/Serviceは
 * 年対年・月対月の2種類のみを持つ。部署キーに 'all' を渡すと3部署合算になる。
 */
class SideBySideComparisonController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/SideBySideComparison', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => $companyId !== null,
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
            'enabledDepartmentKeys' => $companyId !== null ? SalesDepartments::enabledKeysFor($companyId) : [],
        ]);
    }

    public function summary(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'period_a' => ['required', 'array'],
            'period_a.type' => ['required', 'string', Rule::in(['year', 'month'])],
            'period_a.year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_a.month' => ['required_if:period_a.type,month', 'nullable', 'integer', 'min:1', 'max:12'],
            'period_b' => ['required', 'array'],
            'period_b.type' => ['required', 'string', Rule::in(['year', 'month'])],
            'period_b.year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_b.month' => ['required_if:period_b.type,month', 'nullable', 'integer', 'min:1', 'max:12'],
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->queryService->sideBySideComparison(
            $data['department_key'],
            $data['period_a'],
            $data['period_b'],
            (bool) ($data['consolidate_clients'] ?? false)
        ));
    }
}
