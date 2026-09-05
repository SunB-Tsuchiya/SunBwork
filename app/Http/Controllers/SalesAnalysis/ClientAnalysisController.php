<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesActiveMonth;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 得意先分析画面。「期間を選んで得意先内訳を見る」同月比較・左右比較とは逆に、
 * 「得意先を選んでその推移を見る」画面（2026-09-04 PLAN1.md「Phase 7-0 詳細設計」7-4/7-5節）。
 * 初期表示期間は「登録済み全期間」（ユーザー確認済み）。部署キーに 'all' を渡すと3部署合算になる。
 */
class ClientAnalysisController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/ClientAnalysis', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'departmentLabels' => [],
                'enabledDepartmentKeys' => [],
                'initialDepartmentKey' => 'all',
                'initialClientName' => null,
                'initialStartYear' => (int) now()->format('Y'),
                'initialStartMonth' => (int) now()->format('n'),
                'initialEndYear' => (int) now()->format('Y'),
                'initialEndMonth' => (int) now()->format('n'),
                'hasAnyData' => false,
            ]);
        }

        $bounds = SalesActiveMonth::where('company_id', $companyId)
            ->whereIn('department_key', SalesDepartments::enabledKeysFor($companyId))
            ->selectRaw('MIN(sales_year * 100 + sales_month) as min_ym, MAX(sales_year * 100 + sales_month) as max_ym')
            ->first();

        $hasAnyData = $bounds->min_ym !== null;
        $nowYear = (int) now()->format('Y');
        $nowMonth = (int) now()->format('n');

        // 月次分析の得意先比較からの深いリンク（Phase 12 Dセクションのクリック遷移）。
        // department_keyのみ、またはdepartment_key+client_nameで初期選択できる（Phase 15）。
        $departmentKey = $request->query('department_key');
        $initialDepartmentKey = is_string($departmentKey) && SalesDepartments::isEnabledFor($companyId, $departmentKey) ? $departmentKey : 'all';
        $clientName = $request->query('client_name');
        $initialClientName = is_string($clientName) && $clientName !== '' ? $clientName : null;

        return Inertia::render('SalesAnalysis/ClientAnalysis', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'departmentLabels' => SalesDepartments::labelsFor($companyId),
            'enabledDepartmentKeys' => SalesDepartments::enabledKeysFor($companyId),
            'initialDepartmentKey' => $initialDepartmentKey,
            'initialClientName' => $initialClientName,
            'initialStartYear' => $hasAnyData ? intdiv((int) $bounds->min_ym, 100) : $nowYear,
            'initialStartMonth' => $hasAnyData ? (int) $bounds->min_ym % 100 : $nowMonth,
            'initialEndYear' => $hasAnyData ? intdiv((int) $bounds->max_ym, 100) : $nowYear,
            'initialEndMonth' => $hasAnyData ? (int) $bounds->max_ym % 100 : $nowMonth,
            'hasAnyData' => $hasAnyData,
        ]);
    }

    /** 得意先ランキングのTop10/20＋全件詳細ドロワー用（Phase 15） */
    public function rankingPanel(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'end_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'end_month' => ['required', 'integer', 'min:1', 'max:12'],
            'keyword' => 'nullable|string|max:255',
            'sort' => ['nullable', 'string', Rule::in(['amount', 'label'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'limit' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1|max:1000',
        ]);

        return response()->json($this->queryService->clientAnalysisPanel(
            $data['department_key'],
            (int) $data['start_year'],
            (int) $data['start_month'],
            (int) $data['end_year'],
            (int) $data['end_month'],
            $request->boolean('consolidate_clients'),
            $data['keyword'] ?? null,
            $data['sort'] ?? 'amount',
            $data['direction'] ?? 'desc',
            $data['limit'] ?? 10,
            $data['page'] ?? 1
        ));
    }

    public function ranking(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'end_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'end_month' => ['required', 'integer', 'min:1', 'max:12'],
            'consolidate_clients' => ['nullable', 'boolean'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->queryService->clientRankingForPeriod(
            $data['department_key'],
            (int) $data['start_year'],
            (int) $data['start_month'],
            (int) $data['end_year'],
            (int) $data['end_month'],
            (bool) ($data['consolidate_clients'] ?? false),
            $data['keyword'] ?? null
        ));
    }

    public function detail(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'client_name' => ['required', 'string', 'max:255'],
            'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'end_year' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:start_year'],
            'end_month' => ['required', 'integer', 'min:1', 'max:12'],
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->queryService->clientDetail(
            $data['department_key'],
            $data['client_name'],
            (int) $data['start_year'],
            (int) $data['start_month'],
            (int) $data['end_year'],
            (int) $data['end_month'],
            (bool) ($data['consolidate_clients'] ?? false)
        ));
    }
}
