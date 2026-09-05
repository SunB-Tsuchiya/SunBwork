<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
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
 * 部署・年を選んで年間売上を分析する画面（2026-09-03 Codexレビュー2回目 6C節に基づき新規作成）。
 * 進行中の年は登録済みの最終月までを対象に前年の同期間と比較する（前年12ヶ月合計とは分母を分けない）。
 * 部署キーに 'all' を渡すと企画・制作・オンデマンドの3部署合算（全部署合計）になる。
 */
class AnnualAnalysisController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix;

    public function __construct(private SalesQueryService $queryService, private SalesExportService $exportService)
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

        // 選択中の年自体に登録が無いだけで空表示に落とさない（未登録年はPeriodNavigator/
        // 欠落月バナーの案内で扱う）。判定は部署に何か1件でも登録済みかに留める
        // （実機フィードバック対応: 未登録年のURLでリロードすると空表示に戻ってしまう問題、2026-09-04）
        $departmentKeysForQuery = $departmentKey === 'all' ? SalesDepartments::ENABLED_KEYS : [$departmentKey];
        $hasAnyData = SalesActiveMonth::whereIn('department_key', $departmentKeysForQuery)->exists();

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
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        return response()->json(
            $this->queryService->annualSummary(
                $data['department_key'],
                (int) $data['year'],
                (bool) ($data['consolidate_clients'] ?? false)
            )
        );
    }

    /** 期間ナビゲーターの「最新年」ボタン用（Phase 13） */
    public function latestPeriod(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
        ]);

        return response()->json([
            'latest' => ($year = $this->queryService->latestRegisteredYear($data['department_key'])) !== null ? ['year' => $year] : null,
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

    /** 得意先比較パネル（Top10/20＋全件詳細ドロワー共用、前年同期間との差額込み。Phase 13） */
    public function clients(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->annualClientPanel(
            $data['department_key'],
            (int) $data['year'],
            $request->boolean('consolidate'),
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 分類内訳パネル（Phase 13） */
    public function categories(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->annualBreakdownPanel(
            $data['department_key'],
            (int) $data['year'],
            'category',
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 項目内訳パネル（Phase 13） */
    public function items(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $panel = $this->validatePanelOptions($request);

        return response()->json($this->queryService->annualBreakdownPanel(
            $data['department_key'],
            (int) $data['year'],
            'item_name',
            $panel['keyword'] ?? null,
            $panel['sort'] ?? 'amount',
            $panel['direction'] ?? 'desc',
            $panel['limit'] ?? 10,
            $panel['page'] ?? 1
        ));
    }

    /** 「月別売上」グラフの複数年重ね表示用（2/3/5年切替、実機フィードバック対応、2026-09-04） */
    public function multiYearTrend(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'years' => ['nullable', 'integer', Rule::in([2, 3, 5])],
        ]);

        return response()->json([
            'series' => $this->queryService->multiYearMonthlySeries($data['department_key'], (int) $data['year'], (int) ($data['years'] ?? 2)),
        ]);
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

    public function export(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::ENABLED_KEYS, 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'consolidate_clients' => ['nullable', 'boolean'],
        ]);

        $departmentKey = $data['department_key'];
        $year = (int) $data['year'];
        $consolidateClients = (bool) ($data['consolidate_clients'] ?? false);

        $spreadsheet = $this->exportService->annualAnalysisWorkbook($departmentKey, $year, $consolidateClients);
        $departmentLabel = $departmentKey === 'all' ? '全部署合計' : (SalesDepartments::labelFromKey($departmentKey) ?? $departmentKey);
        $filename = "年次分析_{$departmentLabel}_{$year}年.xlsx";

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'export',
            'target_type' => 'annual_analysis',
            'context' => ['department_key' => $departmentKey, 'year' => $year],
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }

    private function isValidDepartmentKey(string $key): bool
    {
        return $key === 'all' || SalesDepartments::isEnabled($key);
    }
}
