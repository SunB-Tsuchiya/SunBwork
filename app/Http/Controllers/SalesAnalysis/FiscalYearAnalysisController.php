<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesAuditLog;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExportService;
use App\Services\SalesAnalysis\SalesQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 部署・期（4月始まり〜翌3月終わり）を選んで会計年度の売上を分析する画面
 * （実機フィードバック対応: 「年次分析」の暦年/年度トグルが分かりにくいため、
 * 暦年専用の年次分析とは別に会計年度専用の画面として独立させた、2026-09-04）。
 * 構造・APIは`AnnualAnalysisController`と対応しているが、年またぎ集計になるため
 * サービス層は`SalesQueryService`の期別専用メソッド群（fiscalYear*）を使う。
 */
class FiscalYearAnalysisController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesQueryService $queryService, private SalesExportService $exportService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/FiscalYearAnalysis', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'departmentLabels' => [],
                'enabledDepartmentKeys' => [],
                'initialDepartmentKey' => null,
                'initialFiscalYear' => (int) (now()->month >= 4 ? now()->format('Y') : now()->format('Y') - 1),
                'hasAnyData' => false,
            ]);
        }

        $this->queryService->forCompany($companyId);
        $enabledKeys = SalesDepartments::enabledKeysFor($companyId);

        $departmentKey = $request->query('department_key');
        $fiscalYear = $request->query('fiscal_year');

        $hasValidQueryParams = is_string($departmentKey)
            && $this->isValidDepartmentKey($companyId, $departmentKey)
            && is_numeric($fiscalYear)
            && (int) $fiscalYear >= 2000
            && (int) $fiscalYear <= 2100;

        if (! $hasValidQueryParams) {
            $latest = SalesActiveMonth::where('company_id', $companyId)
                ->whereIn('department_key', $enabledKeys)
                ->orderByDesc('sales_year')
                ->orderByDesc('sales_month')
                ->orderByDesc('activated_at')
                ->first();

            $departmentKey = $latest->department_key ?? ($enabledKeys[0] ?? null);
            $fiscalYear = $latest
                ? ($latest->sales_month >= 4 ? (int) $latest->sales_year : (int) $latest->sales_year - 1)
                : (int) (now()->month >= 4 ? now()->format('Y') : now()->format('Y') - 1);
        }

        $fiscalYear = (int) $fiscalYear;

        // 選択中の期自体に登録が無いだけで空表示に落とさない（未登録期はPeriodNavigator/
        // 欠落月バナーの案内で扱う。判定は部署に何か1件でも登録済みかに留める）
        $departmentKeysForQuery = $departmentKey === 'all' ? $enabledKeys : [$departmentKey];
        $hasAnyData = SalesActiveMonth::where('company_id', $companyId)->whereIn('department_key', $departmentKeysForQuery)->exists();

        return Inertia::render('SalesAnalysis/FiscalYearAnalysis', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'departmentLabels' => SalesDepartments::labelsFor($companyId),
            'enabledDepartmentKeys' => $enabledKeys,
            'initialDepartmentKey' => $departmentKey,
            'initialFiscalYear' => $fiscalYear,
            'hasAnyData' => $hasAnyData,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        return response()->json(
            $this->queryService->fiscalYearSummary(
                $data['department_key'],
                (int) $data['fiscal_year'],
                (bool) ($data['consolidate_clients'] ?? false)
            )
        );
    }

    /** 期間ナビゲーターの「最新期」ボタン用 */
    public function latestPeriod(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request);

        $fiscalYear = $this->queryService->latestRegisteredFiscalYear($data['department_key']);

        return response()->json([
            'latest' => $fiscalYear !== null ? ['fiscal_year' => $fiscalYear] : null,
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

    /** 得意先比較パネル（Top10/20＋全件詳細ドロワー共用、前年同期間との差額込み） */
    public function clients(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->fiscalYearClientPanel(
            $data['department_key'],
            (int) $data['fiscal_year'],
            $request->boolean('consolidate'),
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 分類内訳パネル */
    public function categories(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->fiscalYearBreakdownPanel(
            $data['department_key'],
            (int) $data['fiscal_year'],
            'category',
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 項目内訳パネル */
    public function items(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->fiscalYearBreakdownPanel(
            $data['department_key'],
            (int) $data['fiscal_year'],
            'item_name',
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 「月別売上」グラフの複数期重ね表示用（2/3/5期切替） */
    public function multiYearTrend(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'years' => ['nullable', 'integer', Rule::in([2, 3, 5])],
        ]);

        return response()->json([
            'series' => $this->queryService->multiYearFiscalMonthlySeries($data['department_key'], (int) $data['fiscal_year'], (int) ($data['years'] ?? 2)),
        ]);
    }

    public function products(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'keyword' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        return response()->json([
            'orders' => $this->queryService->searchByProductNameForFiscalYear($data['department_key'], (int) $data['fiscal_year'], $data['keyword']),
        ]);
    }

    public function export(Request $request)
    {
        $data = $this->validateDepartmentAndRules($request, [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        $companyId = $data['company_id'];
        $departmentKey = $data['department_key'];
        $fiscalYear = (int) $data['fiscal_year'];
        $consolidateClients = (bool) ($data['consolidate_clients'] ?? false);

        $spreadsheet = $this->exportService->forCompany($companyId)->fiscalYearAnalysisWorkbook($departmentKey, $fiscalYear, $consolidateClients);
        $departmentLabel = $departmentKey === 'all' ? '全部署合計' : (SalesDepartments::labelForKey($companyId, $departmentKey) ?? $departmentKey);
        $filename = "期別分析_{$departmentLabel}_{$fiscalYear}年度.xlsx";

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'export',
            'target_type' => 'fiscal_year_analysis',
            'context' => ['department_key' => $departmentKey, 'fiscal_year' => $fiscalYear],
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }

    private function isValidDepartmentKey(int $companyId, string $key): bool
    {
        return $key === 'all' || SalesDepartments::isEnabledFor($companyId, $key);
    }

    private function validateDepartmentAndRules(Request $request, array $extraRules = []): array
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->queryService->forCompany($companyId);

        $data = $request->validate(array_merge([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
        ], $extraRules));

        $data['company_id'] = $companyId;

        return $data;
    }
}
