<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesAuditLog;
use App\Services\SalesAnalysis\SalesAttentionService;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 売上分析の「注目ポイント」（SBATTN_PLAN1.md）。大きく増減した得意先・新規・休眠。
 * 売上分析の他の画面と同じく、superadmin/admin/clerk の各ロールグループ内に複製登録され、`sales_analysis` ミドルウェアで保護される。
 */
class AttentionController extends Controller
{
    use ResolvesSalesAnalysisCompany, ResolvesSalesAnalysisRoutePrefix;

    public function __construct(private SalesAttentionService $service, private SalesExportService $exportService)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/Attention', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'departmentLabels' => [],
                'enabledDepartmentKeys' => [],
                'initialDepartmentKey' => null,
                'initialYear' => (int) now()->format('Y'),
                'hasAnyData' => false,
                'dormantOptions' => SalesAttentionService::DORMANT_MONTHS,
            ]);
        }

        $enabledKeys = SalesDepartments::enabledKeysFor($companyId);

        // 部署・年の指定があれば優先（データ登録状況などからのリンク）。なければ、最新に登録された部署・年
        $departmentKey = $request->query('department_key');
        $year = $request->query('year');
        $valid = is_string($departmentKey)
            && ($departmentKey === 'all' || SalesDepartments::isEnabledFor($companyId, $departmentKey))
            && is_numeric($year) && (int) $year >= 2000 && (int) $year <= 2100;

        if (! $valid) {
            $latest = SalesActiveMonth::where('company_id', $companyId)
                ->whereIn('department_key', $enabledKeys)
                ->orderByDesc('sales_year')->orderByDesc('activated_at')->first();
            $departmentKey = $latest->department_key ?? ($enabledKeys[0] ?? null);
            $year = $latest->sales_year ?? (int) now()->format('Y');
        }

        $keys = $departmentKey === 'all' ? $enabledKeys : [$departmentKey];

        return Inertia::render('SalesAnalysis/Attention', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'departmentLabels' => SalesDepartments::labelsFor($companyId),
            'enabledDepartmentKeys' => $enabledKeys,
            'initialDepartmentKey' => $departmentKey,
            'initialYear' => (int) $year,
            'hasAnyData' => SalesActiveMonth::where('company_id', $companyId)->whereIn('department_key', $keys)->exists(),
            'dormantOptions' => SalesAttentionService::DORMANT_MONTHS,
        ]);
    }

    public function summary(Request $request)
    {
        $data = $this->validated($request);

        return response()->json($this->service->forCompany($data['company_id'])->summary(
            $data['department_key'], (int) $data['year'], (bool) ($data['consolidate_clients'] ?? false), (int) ($data['dormant_months'] ?? 6),
        ));
    }

    public function export(Request $request)
    {
        $data = $this->validated($request);

        $departmentKey = $data['department_key'];
        $year = (int) $data['year'];
        $dormantMonths = (int) ($data['dormant_months'] ?? 6);
        $consolidate = (bool) ($data['consolidate_clients'] ?? false);

        $built = $this->exportService->forCompany($data['company_id'])->attentionWorkbook($departmentKey, $year, $consolidate, $dormantMonths);
        $departmentLabel = $departmentKey === 'all' ? '全部署合計' : (SalesDepartments::labelForKey($data['company_id'], $departmentKey) ?? $departmentKey);
        $filename = "注目ポイント_{$departmentLabel}_{$year}年_休眠{$dormantMonths}か月.xlsx";

        ob_start();
        (new Xlsx($built['spreadsheet']))->save('php://output');
        $content = ob_get_clean();

        // 監査ログ: 画面名と行数だけ（得意先名・金額・条件の内容は入れない）
        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'export',
            'target_type' => 'attention',
            'context' => ['rows' => $built['rows']],
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }

    private function validated(Request $request): array
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), 'all'])],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'consolidate_clients' => ['nullable', 'boolean'],
            'dormant_months' => ['nullable', 'integer', Rule::in(SalesAttentionService::DORMANT_MONTHS)],
        ]);
        $data['company_id'] = $companyId;

        return $data;
    }
}
