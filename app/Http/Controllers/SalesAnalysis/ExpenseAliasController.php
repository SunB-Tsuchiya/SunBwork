<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExpenseAliasService;
use App\Services\SalesAnalysis\SalesExpenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 経費の名寄せ設定（Phase24）。年度ごとに表記が変わる科目名（例: 外注加工→外注加工費）を
 * 正式名へ読み替える対応表を、画面で候補を承認しながら作る。取込済みのデータは書き換えない。
 */
class ExpenseAliasController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function index()
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/ExpenseAliases', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
            'enabledDepartmentKeys' => $companyId !== null ? SalesDepartments::enabledKeysFor($companyId) : [],
            'hasCompanySelected' => $companyId !== null,
            'supportsExpenses' => $companyId !== null && SalesExpenses::supportsExpensesFor($companyId),
        ]);
    }

    private function aliasService(): SalesExpenseAliasService
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        return (new SalesExpenseAliasService())->forCompany($companyId);
    }

    private function departmentRule(): array
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        return ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), SalesExpenses::SHARED_DEPARTMENT_KEY])];
    }

    /** 名寄せの対象。科目名（部署ごと）か、支払先名（部署をまたぐ）。省略時は科目名 */
    private function kindRule(): array
    {
        return ['nullable', Rule::in(['category', 'vendor'])];
    }

    public function data(Request $request)
    {
        $service = $this->aliasService();
        $data = $request->validate(['kind' => $this->kindRule(), 'department_key' => ['required_unless:kind,vendor', ...$this->departmentRuleOptional()]]);

        return response()->json(($data['kind'] ?? 'category') === 'vendor'
            ? $service->vendorOverview()
            : $service->overview($data['department_key']));
    }

    /** 正式名と別名を保存する。別名が空なら、その正式名のグループを解除する */
    public function save(Request $request)
    {
        $service = $this->aliasService();
        $data = $request->validate([
            'kind' => $this->kindRule(),
            'department_key' => ['required_unless:kind,vendor', ...$this->departmentRuleOptional()],
            'canonical' => ['required', 'string', 'max:255'],
            'aliases' => ['present', 'array'],
            'aliases.*' => ['string', 'max:255'],
        ]);

        if (($data['kind'] ?? 'category') === 'vendor') {
            $service->saveVendorGroup($data['canonical'], $data['aliases'], Auth::id());

            return response()->json($service->vendorOverview());
        }

        $service->saveGroup($data['department_key'], $data['canonical'], $data['aliases'], Auth::id());

        return response()->json($service->overview($data['department_key']));
    }

    public function approveSuggestions(Request $request)
    {
        $service = $this->aliasService();
        $data = $request->validate(['kind' => $this->kindRule(), 'department_key' => ['required_unless:kind,vendor', ...$this->departmentRuleOptional()]]);

        if (($data['kind'] ?? 'category') === 'vendor') {
            $count = $service->approveVendorSuggestions(Auth::id());

            return response()->json(array_merge($service->vendorOverview(), ['approved_count' => $count]));
        }

        $count = $service->approveSuggestions($data['department_key'], Auth::id());

        return response()->json(array_merge($service->overview($data['department_key']), ['approved_count' => $count]));
    }

    /** 部署キーは科目名のときだけ必須。指定されたら有効な部署キーであること */
    private function departmentRuleOptional(): array
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        return ['nullable', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), SalesExpenses::SHARED_DEPARTMENT_KEY])];
    }
}
