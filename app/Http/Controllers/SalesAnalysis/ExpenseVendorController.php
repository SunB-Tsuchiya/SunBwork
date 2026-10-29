<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExpenses;
use App\Services\SalesAnalysis\SalesExpenseVendorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 経費「支払先カルテ」（Phase24-3）のAPI。画面は経費比較ページ（ExpenseComparison.vue）の「支払先」タブ。
 * 全部署・全科目・全年度を支払先（名寄せ後の名前）で横断して集計する。
 */
class ExpenseVendorController extends Controller
{
    use ResolvesSalesAnalysisCompany;

    private function vendorService(): SalesExpenseVendorService
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        return (new SalesExpenseVendorService())->forCompany($companyId);
    }

    public function ranking(Request $request)
    {
        $service = $this->vendorService();
        $companyId = $this->requireSalesAnalysisCompanyId();

        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'department_key' => ['nullable', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), SalesExpenses::SHARED_DEPARTMENT_KEY])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($service->ranking((int) $data['fiscal_year'], $data['department_key'] ?? null, $data['q'] ?? ''));
    }

    public function detail(Request $request)
    {
        $service = $this->vendorService();

        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'vendor' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($service->detail($data['vendor'], (int) $data['fiscal_year']));
    }
}
