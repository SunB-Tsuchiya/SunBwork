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
 * 部署・対象月を選び、直近5〜10年の同月売上を比較する画面
 * （2026-09-04 Codexレビュー2回目 10.4節・PLAN1.md「6D. 同月比較 詳細設計」に基づき新規作成）。
 * 得意先の新規/離脱・増減額上位・分類/項目の1・3・5年前比較を提供する。
 * 部署キーに 'all' を渡すと企画・制作・オンデマンドの3部署合算になる。
 */
class SameMonthComparisonController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/SameMonthComparison', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'departmentLabels' => [],
                'enabledDepartmentKeys' => [],
                'initialDepartmentKey' => null,
                'initialMonth' => (int) now()->format('n'),
                'hasAnyData' => false,
            ]);
        }

        $this->queryService->forCompany($companyId);
        $enabledKeys = SalesDepartments::enabledKeysFor($companyId);

        // 年次分析・データ登録状況からの深いリンク（部署・対象月指定）を優先する
        $departmentKey = $request->query('department_key');
        $month = $request->query('month');

        $hasValidQueryParams = is_string($departmentKey)
            && $this->isValidDepartmentKey($companyId, $departmentKey)
            && is_numeric($month)
            && (int) $month >= 1
            && (int) $month <= 12;

        if (! $hasValidQueryParams) {
            $latest = SalesActiveMonth::where('company_id', $companyId)
                ->whereIn('department_key', $enabledKeys)
                ->orderByDesc('sales_year')
                ->orderByDesc('sales_month')
                ->orderByDesc('activated_at')
                ->first();

            $departmentKey = $latest->department_key ?? ($enabledKeys[0] ?? null);
            $month = $latest->sales_month ?? (int) now()->format('n');
        }

        $month = (int) $month;

        // 選択中の月自体に登録が無いだけで空表示に落とさない（実機フィードバック対応、2026-09-04）
        $departmentKeysForQuery = $departmentKey === 'all' ? $enabledKeys : [$departmentKey];
        $hasAnyData = SalesActiveMonth::where('company_id', $companyId)->whereIn('department_key', $departmentKeysForQuery)->exists();

        return Inertia::render('SalesAnalysis/SameMonthComparison', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'departmentLabels' => SalesDepartments::labelsFor($companyId),
            'enabledDepartmentKeys' => $enabledKeys,
            'initialDepartmentKey' => $departmentKey,
            'initialMonth' => $month,
            'hasAnyData' => $hasAnyData,
        ]);
    }

    public function summary(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'years' => ['nullable', 'integer', Rule::in([5, 10])],
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->queryService->sameMonthComparison(
            $data['department_key'],
            (int) $data['month'],
            (int) ($data['years'] ?? 5),
            (bool) ($data['consolidate_clients'] ?? false)
        ));
    }

    /** 期間ナビゲーターの「最新登録月」ボタン用（Phase 13） */
    public function latestPeriod(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
        ]);

        $month = $this->queryService->latestRegisteredMonthNumber($data['department_key']);

        return response()->json(['latest' => $month !== null ? ['month' => $month] : null]);
    }

    private function isValidDepartmentKey(int $companyId, string $key): bool
    {
        return $key === 'all' || SalesDepartments::isEnabledFor($companyId, $key);
    }
}
