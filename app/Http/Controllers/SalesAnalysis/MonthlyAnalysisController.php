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
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/MonthlyAnalysis', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'departmentLabels' => [],
                'enabledDepartmentKeys' => [],
                'initialDepartmentKey' => null,
                'initialYear' => (int) now()->format('Y'),
                'initialMonth' => (int) now()->format('n'),
                'hasAnyData' => false,
                'initialLatestPeriod' => null,
            ]);
        }

        $enabledKeys = SalesDepartments::enabledKeysFor($companyId);

        // 部署・年・月がクエリストリングで指定されていれば、それを初期表示に使う
        // （データ登録状況画面などからの深いリンク用。2026-09-03追加）。
        $departmentKey = $request->query('department_key');
        $year = $request->query('year');
        $month = $request->query('month');

        $hasValidQueryParams = is_string($departmentKey)
            && SalesDepartments::isEnabledFor($companyId, $departmentKey)
            && is_numeric($year)
            && is_numeric($month)
            && (int) $year >= 2000
            && (int) $year <= 2100
            && (int) $month >= 1
            && (int) $month <= 12;

        if ($hasValidQueryParams) {
            $year = (int) $year;
            $month = (int) $month;

            // 深いリンク・期間ナビゲーターでの移動先・URLクエリでのリロードいずれでも、
            // 選択中の年月自体に登録が無いだけで空表示に落とさない（未登録月はPeriodNavigatorの
            // 案内で扱う）。判定は「この部署に何か1件でも登録済みか」に留める
            // （実機フィードバック対応: 未登録月のURLでリロードすると空表示に戻ってしまう問題、2026-09-04）
            $hasAnyData = SalesActiveMonth::where('company_id', $companyId)->where('department_key', $departmentKey)->exists();

            return Inertia::render('SalesAnalysis/MonthlyAnalysis', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => true,
                'departmentLabels' => SalesDepartments::labelsFor($companyId),
                'enabledDepartmentKeys' => $enabledKeys,
                'initialDepartmentKey' => $departmentKey,
                'initialYear' => $year,
                'initialMonth' => $month,
                'hasAnyData' => $hasAnyData,
                'initialLatestPeriod' => $this->queryService->forCompany($companyId)->latestRegisteredMonth($departmentKey),
            ]);
        }

        // 部署を1つ目（企画）に固定していると、制作・オンデマンドしか取込のない状態で
        // 「データなし」の空表示になってしまう（2026-09-03実機検証で発覚）。
        // 全有効部署のうち、登録済みの最新対象年・月を初期表示にする。
        // activated_at（取込操作の時刻）を主キーにすると、2020年分のデータを後から
        // 追加登録した直後に古い2020年が「最新」として開いてしまうため、
        // sales_year/sales_month（対象期間そのもの）を優先する
        // （Codexレビュー2回目 8.1 Medium-1対応）。
        $latest = SalesActiveMonth::where('company_id', $companyId)
            ->whereIn('department_key', $enabledKeys)
            ->orderByDesc('sales_year')
            ->orderByDesc('sales_month')
            ->orderByDesc('activated_at')
            ->first();

        $departmentKey = $latest->department_key ?? ($enabledKeys[0] ?? null);

        return Inertia::render('SalesAnalysis/MonthlyAnalysis', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'departmentLabels' => SalesDepartments::labelsFor($companyId),
            'enabledDepartmentKeys' => $enabledKeys,
            'initialDepartmentKey' => $departmentKey,
            'initialYear' => $latest->sales_year ?? (int) now()->format('Y'),
            'initialMonth' => $latest->sales_month ?? (int) now()->format('n'),
            'hasAnyData' => $latest !== null,
            'initialLatestPeriod' => $latest ? ['year' => (int) $latest->sales_year, 'month' => (int) $latest->sales_month] : null,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $this->validatePeriod($request);

        return response()->json([
            'period_status' => $this->queryService->nearestRegisteredMonths($data['department_key'], $data['year'], $data['month']),
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

    /** 「月の推移グラフ」用。選択月までの直近$monthsヶ月（既定13）＋3ヶ月移動平均（Phase 12） */
    public function trend(Request $request)
    {
        $data = $this->validatePeriod($request);
        $months = (int) ($request->validate(['months' => 'nullable|integer|min:3|max:36'])['months'] ?? 13);

        return response()->json([
            'trend' => $this->queryService->recentMonthlyTrend($data['department_key'], $data['year'], $data['month'], $months),
        ]);
    }

    /** 「同月の複数年比較」用。選択月だけを直近$years年分（既定5）（Phase 12） */
    public function sameMonthHistory(Request $request)
    {
        $data = $this->validatePeriod($request);
        $years = (int) ($request->validate(['years' => 'nullable|integer|min:2|max:20'])['years'] ?? 5);

        return response()->json([
            'history' => $this->queryService->sameMonthAcrossYears($data['department_key'], $data['month'], $data['year'], $years),
        ]);
    }

    /** 期間ナビゲーターの「最新登録月」ボタン用（部署切替後の再取得もこのAPIを使う。Phase 12） */
    public function latestPeriod(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::enabledKeysFor($companyId))],
        ]);

        return response()->json([
            'latest' => $this->queryService->forCompany($companyId)->latestRegisteredMonth($data['department_key']),
        ]);
    }

    private function validatePanelOptions(Request $request): array
    {
        return $request->validate([
            'keyword' => 'nullable|string|max:255',
            'sort' => ['nullable', 'string', Rule::in(['amount', 'diff', 'rate', 'label'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'limit' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1|max:1000',
        ]);
    }

    /** 得意先比較パネル（Top10/20＋全件詳細ドロワー共用、当月/前月増減/前年同月増減の3モード。Phase 12） */
    public function clients(Request $request)
    {
        $data = $this->validatePeriod($request);
        $panel = $this->validatePanelOptions($request);
        $mode = $request->validate(['mode' => ['nullable', 'string', Rule::in(['current', 'vs_previous', 'vs_previous_year'])]])['mode'] ?? 'current';

        return response()->json($this->queryService->monthlyClientPanel(
            $data['department_key'],
            $data['year'],
            $data['month'],
            $mode,
            (bool) $request->boolean('consolidate'),
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 分類内訳パネル（Top10/20＋全件詳細ドロワー共用。Phase 12） */
    public function categories(Request $request)
    {
        $data = $this->validatePeriod($request);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->monthlyBreakdownPanel(
            $data['department_key'],
            $data['year'],
            $data['month'],
            'category',
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 項目内訳パネル（Top10/20＋全件詳細ドロワー共用。Phase 12） */
    public function items(Request $request)
    {
        $data = $this->validatePeriod($request);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->monthlyBreakdownPanel(
            $data['department_key'],
            $data['year'],
            $data['month'],
            'item_name',
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    public function products(Request $request)
    {
        $data = $this->validatePeriod($request);
        $keyword = $request->validate(['keyword' => 'required|string|min:1|max:255'])['keyword'];

        return response()->json([
            'orders' => $this->queryService->searchByProductName($data['department_key'], $data['year'], $data['month'], $keyword),
        ]);
    }

    /**
     * 期間パラメータのバリデーションと同時に会社IDを解決し、以降のqueryService呼び出しに
     * `forCompany()`を適用しておく（各アクションが個別にforCompany()を呼ばずに済むようにする）。
     */
    private function validatePeriod(Request $request): array
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::enabledKeysFor($companyId))],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $data['company_id'] = $companyId;

        return $data;
    }
}
