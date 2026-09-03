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
 * 部署・年月を選んで単月（当月・前月比・前年同月比）の売上を分析する画面。
 * 2026-09-03 Codexレビュー2回目により、旧DashboardController（ホーム画面だった）から改名。
 * ホーム画面は RegistrationStatusController（データ登録状況）に譲り、
 * このコントローラーは「月次分析」画面専用として存続する。
 * 2026-09-03 さらに、内容が単月分析であり「年次分析」という名称が実態と合わないため、
 * 改名前のコントローラー（年次分析を名乗っていたもの）から MonthlyAnalysisController へ改名した
 * （本物の年次分析画面は別途新規作成予定）。
 */
class MonthlyAnalysisController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        // 部署・年・月がクエリストリングで指定されていれば、それを初期表示に使う
        // （データ登録状況画面などからの深いリンク用。2026-09-03追加）。
        $departmentKey = $request->query('department_key');
        $year = $request->query('year');
        $month = $request->query('month');

        $hasValidQueryParams = is_string($departmentKey)
            && SalesDepartments::isEnabled($departmentKey)
            && is_numeric($year)
            && is_numeric($month)
            && (int) $year >= 2000
            && (int) $year <= 2100
            && (int) $month >= 1
            && (int) $month <= 12;

        if ($hasValidQueryParams) {
            $year = (int) $year;
            $month = (int) $month;

            $hasAnyData = SalesActiveMonth::where('department_key', $departmentKey)
                ->where('sales_year', $year)
                ->where('sales_month', $month)
                ->exists();

            return Inertia::render('SalesAnalysis/MonthlyAnalysis', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'departmentLabels' => SalesDepartments::LABELS,
                'enabledDepartmentKeys' => SalesDepartments::ENABLED_KEYS,
                'initialDepartmentKey' => $departmentKey,
                'initialYear' => $year,
                'initialMonth' => $month,
                'hasAnyData' => $hasAnyData,
            ]);
        }

        // 部署を1つ目（企画）に固定していると、制作・オンデマンドしか取込のない状態で
        // 「データなし」の空表示になってしまう（2026-09-03実機検証で発覚）。
        // 全有効部署のうち、登録済みの最新対象年・月を初期表示にする。
        // activated_at（取込操作の時刻）を主キーにすると、2020年分のデータを後から
        // 追加登録した直後に古い2020年が「最新」として開いてしまうため、
        // sales_year/sales_month（対象期間そのもの）を優先する
        // （Codexレビュー2回目 8.1 Medium-1対応）。
        $latest = SalesActiveMonth::whereIn('department_key', SalesDepartments::ENABLED_KEYS)
            ->orderByDesc('sales_year')
            ->orderByDesc('sales_month')
            ->orderByDesc('activated_at')
            ->first();

        $departmentKey = $latest->department_key ?? SalesDepartments::ENABLED_KEYS[0];

        return Inertia::render('SalesAnalysis/MonthlyAnalysis', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => SalesDepartments::LABELS,
            'enabledDepartmentKeys' => SalesDepartments::ENABLED_KEYS,
            'initialDepartmentKey' => $departmentKey,
            'initialYear' => $latest->sales_year ?? (int) now()->format('Y'),
            'initialMonth' => $latest->sales_month ?? (int) now()->format('n'),
            'hasAnyData' => $latest !== null,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $this->validatePeriod($request);

        return response()->json([
            'monthly' => $this->queryService->monthlyComparison($data['department_key'], $data['year'], $data['month']),
            'fiscal_calendar' => $this->queryService->fiscalYearToDate(
                $data['department_key'],
                $data['year'],
                $data['month'],
                SalesQueryService::FISCAL_MODE_CALENDAR
            ),
            'fiscal_april' => $this->queryService->fiscalYearToDate(
                $data['department_key'],
                $data['year'],
                $data['month'],
                SalesQueryService::FISCAL_MODE_APRIL
            ),
        ]);
    }

    public function trend(Request $request)
    {
        $data = $this->validatePeriod($request);
        $years = (int) $request->validate(['years' => 'nullable|integer|min:1|max:20'])['years'] ?? 5;

        return response()->json([
            'trend' => $this->queryService->monthlyTrend($data['department_key'], $data['year'], $data['month'], $years),
        ]);
    }

    public function clients(Request $request)
    {
        $data = $this->validatePeriod($request);
        $options = $request->validate([
            'consolidate' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        return response()->json(
            $this->queryService->clientRanking(
                $data['department_key'],
                $data['year'],
                $data['month'],
                (bool) ($options['consolidate'] ?? false),
                array_key_exists('limit', $options) ? $options['limit'] : 10
            )
        );
    }

    public function categories(Request $request)
    {
        $data = $this->validatePeriod($request);

        return response()->json(
            $this->queryService->categoryBreakdown($data['department_key'], $data['year'], $data['month'])
        );
    }

    public function items(Request $request)
    {
        $data = $this->validatePeriod($request);

        return response()->json(
            $this->queryService->itemBreakdown($data['department_key'], $data['year'], $data['month'])
        );
    }

    public function products(Request $request)
    {
        $data = $this->validatePeriod($request);
        $keyword = $request->validate(['keyword' => 'required|string|min:1|max:255'])['keyword'];

        return response()->json([
            'orders' => $this->queryService->searchByProductName($data['department_key'], $data['year'], $data['month'], $keyword),
        ]);
    }

    private function validatePeriod(Request $request): array
    {
        return $request->validate([
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::ENABLED_KEYS)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
    }
}
