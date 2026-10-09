<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExpenseQueryService;
use App\Services\SalesAnalysis\SalesExpenses;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 経費比較画面（Phase22）。期別分析の経費セクション（Phase21）だけでは分からなかった
 * 「科目の複数年推移」「支払先（外注先）への依存度」を見るための画面。粗利計算はしない。
 * 経費データの出どころが会計年度単位のため、暦年（年次分析）への展開はしない
 * （PLAN1.md Phase22節、2026-10-09判断）。
 */
class ExpenseComparisonController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesExpenseQueryService $expenseQueryService)
    {
    }

    public function index()
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/ExpenseComparison', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
            'enabledDepartmentKeys' => $companyId !== null ? SalesDepartments::enabledKeysFor($companyId) : [],
            'hasCompanySelected' => $companyId !== null,
            'supportsExpenses' => $companyId !== null && SalesExpenses::supportsExpensesFor($companyId),
        ]);
    }

    private function validatedDepartmentKey(Request $request, int $companyId): array
    {
        return $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), SalesExpenses::SHARED_DEPARTMENT_KEY])],
        ]);
    }

    /** 科目セレクタの初期化用: 取込実績がある会計年度一覧と、直近年度の科目一覧 */
    public function meta(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = $this->validatedDepartmentKey($request, $companyId);
        $service = $this->expenseQueryService->forCompany($companyId);

        return response()->json([
            'fiscal_years' => $service->availableFiscalYears(),
            'categories' => $service->categoriesForDepartment($data['department_key']),
        ]);
    }

    public function trend(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = array_merge(
            $this->validatedDepartmentKey($request, $companyId),
            $request->validate(['category' => ['required', 'string', 'max:100']])
        );

        return response()->json(
            $this->expenseQueryService->forCompany($companyId)->categoryMultiYearTrend($data['department_key'], $data['category'])
        );
    }

    public function vendors(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = array_merge(
            $this->validatedDepartmentKey($request, $companyId),
            $request->validate([
                'category' => ['required', 'string', 'max:100'],
                'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            ])
        );

        return response()->json(
            $this->expenseQueryService->forCompany($companyId)->vendorComparison((int) $data['fiscal_year'], $data['department_key'], $data['category'])
        );
    }
}
