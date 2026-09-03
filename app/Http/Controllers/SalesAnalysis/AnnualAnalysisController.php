<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesActiveMonth;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 部署・年を選んで年間売上を分析する画面（2026-09-03 Codexレビュー2回目 6C節に基づき新規作成）。
 * 進行中の年は登録済みの最終月までを対象に前年の同期間と比較する（前年12ヶ月合計とは分母を分けない）。
 * 部署キーに 'all' を渡すと企画・制作・オンデマンドの3部署合算（全部署合計）になる。
 */
class AnnualAnalysisController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        // データ登録状況画面からの深いリンク（部署・年指定）を優先する
        $departmentKey = $request->query('department_key');
        $year = $request->query('year');

        $hasValidQueryParams = is_string($departmentKey)
            && $this->isValidDepartmentKey($departmentKey)
            && is_numeric($year)
            && (int) $year >= 2000
            && (int) $year <= 2100;

        if (! $hasValidQueryParams) {
            $latest = SalesActiveMonth::whereIn('department_key', SalesDepartments::ENABLED_KEYS)
                ->orderByDesc('sales_year')
                ->orderByDesc('activated_at')
                ->first();

            $departmentKey = $latest->department_key ?? SalesDepartments::ENABLED_KEYS[0];
            $year = $latest->sales_year ?? (int) now()->format('Y');
        }

        $year = (int) $year;

        $departmentKeysForQuery = $departmentKey === 'all' ? SalesDepartments::ENABLED_KEYS : [$departmentKey];
        $hasAnyData = SalesActiveMonth::whereIn('department_key', $departmentKeysForQuery)
            ->where('sales_year', $year)
            ->exists();

        return Inertia::render('SalesAnalysis/AnnualAnalysis', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => SalesDepartments::LABELS,
            'enabledDepartmentKeys' => SalesDepartments::ENABLED_KEYS,
            'initialDepartmentKey' => $departmentKey,
            'initialYear' => $year,
            'hasAnyData' => $hasAnyData,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json(
            $this->queryService->annualSummary($data['department_key'], (int) $data['year'])
        );
    }

    public function products(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'keyword' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        return response()->json([
            'orders' => $this->queryService->searchByProductNameForYear($data['department_key'], (int) $data['year'], $data['keyword']),
        ]);
    }

    private function isValidDepartmentKey(string $key): bool
    {
        return $key === 'all' || SalesDepartments::isEnabled($key);
    }
}
