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
 * 商品分析画面。得意先分析（ClientAnalysisController）と対称構造で、
 * 「品名を選んでその推移を見る」ことができる（2026-09-05、事務・経理からの要望対応）。
 * 商品には得意先統合に相当する名寄せ概念が無いため consolidate_clients パラメータは持たない。
 */
class ProductAnalysisController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/ProductAnalysis', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'departmentLabels' => [],
                'enabledDepartmentKeys' => [],
                'initialDepartmentKey' => 'all',
                'initialProductName' => null,
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

        $departmentKey = $request->query('department_key');
        $initialDepartmentKey = is_string($departmentKey) && SalesDepartments::isEnabledFor($companyId, $departmentKey) ? $departmentKey : 'all';
        $productName = $request->query('product_name');
        $initialProductName = is_string($productName) && $productName !== '' ? $productName : null;

        return Inertia::render('SalesAnalysis/ProductAnalysis', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'departmentLabels' => SalesDepartments::labelsFor($companyId),
            'enabledDepartmentKeys' => SalesDepartments::enabledKeysFor($companyId),
            'initialDepartmentKey' => $initialDepartmentKey,
            'initialProductName' => $initialProductName,
            'initialStartYear' => $hasAnyData ? intdiv((int) $bounds->min_ym, 100) : $nowYear,
            'initialStartMonth' => $hasAnyData ? (int) $bounds->min_ym % 100 : $nowMonth,
            'initialEndYear' => $hasAnyData ? intdiv((int) $bounds->max_ym, 100) : $nowYear,
            'initialEndMonth' => $hasAnyData ? (int) $bounds->max_ym % 100 : $nowMonth,
            'hasAnyData' => $hasAnyData,
        ]);
    }

    /** 商品ランキングのTop10/20＋全件詳細ドロワー用 */
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

        return response()->json($this->queryService->productAnalysisPanel(
            $data['department_key'],
            (int) $data['start_year'],
            (int) $data['start_month'],
            (int) $data['end_year'],
            (int) $data['end_month'],
            $data['keyword'] ?? null,
            $data['sort'] ?? 'amount',
            $data['direction'] ?? 'desc',
            $data['limit'] ?? 10,
            $data['page'] ?? 1
        ));
    }

    public function detail(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'product_name' => ['required', 'string', 'max:255'],
            'start_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'end_year' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:start_year'],
            'end_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json($this->queryService->productDetail(
            $data['department_key'],
            $data['product_name'],
            (int) $data['start_year'],
            (int) $data['start_month'],
            (int) $data['end_year'],
            (int) $data['end_month']
        ));
    }

    /** 「新規/取扱終了商品」パネル用。常に直近登録年対前年で固定比較する */
    public function yearOverYear(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
        ]);

        return response()->json($this->queryService->productYearOverYearComparison($data['department_key']));
    }
}
