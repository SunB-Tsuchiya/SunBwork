<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesAuditLog;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExpenseExportService;
use App\Services\SalesAnalysis\SalesExpenseQueryService;
use App\Services\SalesAnalysis\SalesExpenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 経費比較画面（Phase22）。期別分析の経費セクション（Phase21）だけでは分からなかった
 * 「科目の複数年推移」「支払先（外注先）への依存度」を見るための画面。粗利計算はしない。
 * 経費データの出どころが会計年度単位のため、暦年（年次分析）への展開はしない
 * （PLAN1.md Phase22節、2026-10-09判断）。
 */
class ExpenseComparisonController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(
        private SalesExpenseQueryService $expenseQueryService,
        private SalesExpenseExportService $exportService,
    ) {
    }

    public function index()
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/ExpenseComparison', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
            'enabledDepartmentKeys' => $companyId !== null ? SalesDepartments::enabledKeysFor($companyId) : [],
            'hasCompanySelected' => $companyId !== null,
            'supportsExpenses' => $companyId !== null && SalesExpenses::supportsExpensesFor($companyId),
        ]);
    }

    private function validatedDepartmentKey(Request $request, int $companyId): array
    {
        return $request->validate([
            'department_key' => ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), SalesExpenses::SHARED_DEPARTMENT_KEY])],
        ]);
    }

    /** 科目セレクタの初期化用: 取込実績がある会計年度一覧と、直近年度の科目一覧 */
    public function meta(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = $this->validatedDepartmentKey($request, $companyId);
        $service = $this->expenseQueryService->forCompany($companyId);

        return response()->json([
            'fiscal_years' => $service->availableFiscalYears(),
            'categories' => $service->categoriesForDepartment($data['department_key']),
        ]);
    }

    public function trend(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = array_merge(
            $this->validatedDepartmentKey($request, $companyId),
            $request->validate(['category' => ['required', 'string', 'max:100']])
        );

        return response()->json(
            $this->expenseQueryService->forCompany($companyId)->categoryMultiYearTrend($data['department_key'], $data['category'])
        );
    }

    public function vendors(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = array_merge(
            $this->validatedDepartmentKey($request, $companyId),
            $request->validate([
                'category' => ['required', 'string', 'max:100'],
                'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            ])
        );

        return response()->json(
            $this->expenseQueryService->forCompany($companyId)->vendorComparison((int) $data['fiscal_year'], $data['department_key'], $data['category'])
        );
    }

    /** 部署シート（Phase23）: Excelの部署シートを1画面で再現するための科目×支払先×月のデータ */
    public function sheet(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = array_merge(
            $this->validatedDepartmentKey($request, $companyId),
            $request->validate([
                'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
                // 比較年度（省略時は前年度）と比較期間（省略時は期の途中ならytd、通年ならfull）
                'compare_fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'compare_period' => ['nullable', Rule::in(['ytd', 'full'])],
            ])
        );

        return response()->json(
            $this->expenseQueryService->forCompany($companyId)->departmentSheet(
                (int) $data['fiscal_year'],
                $data['department_key'],
                isset($data['compare_fiscal_year']) ? (int) $data['compare_fiscal_year'] : null,
                $data['compare_period'] ?? null,
            )
        );
    }

    /** 比較の共通パラメータ（比較年度は省略時に前年度、比較期間は省略時に期の途中ならytd） */
    private function compareRules(): array
    {
        return [
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'compare_fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'compare_period' => ['nullable', Rule::in(['ytd', 'full'])],
        ];
    }

    /** 経費として扱う部署キー（有効な部署＋共通費）を、表示順のままラベルつきで返す */
    private function expenseDepartmentLabels(int $companyId): array
    {
        $labels = [];
        foreach (SalesDepartments::enabledKeysFor($companyId) as $key) {
            $labels[$key] = SalesDepartments::labelsFor($companyId)[$key] ?? $key;
        }
        $labels[SalesExpenses::SHARED_DEPARTMENT_KEY] = '共通費';

        return $labels;
    }

    /** 部署比較（Phase24-4）: 部署ごとの年間合計・月別・前年比と、科目×部署のクロス表 */
    public function departments(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = $request->validate($this->compareRules());

        return response()->json(
            $this->expenseQueryService->forCompany($companyId)->departmentsOverview(
                (int) $data['fiscal_year'],
                array_keys($this->expenseDepartmentLabels($companyId)),
                isset($data['compare_fiscal_year']) ? (int) $data['compare_fiscal_year'] : null,
                $data['compare_period'] ?? null,
            )
        );
    }

    /** Excel出力（Phase24-4）: 部署比較シート＋部署ごとの部署シート。画面と同じ比較条件で出す */
    public function export(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        $data = $request->validate($this->compareRules());
        $fiscalYear = (int) $data['fiscal_year'];
        $compareFiscalYear = isset($data['compare_fiscal_year']) ? (int) $data['compare_fiscal_year'] : null;
        $comparePeriod = $data['compare_period'] ?? null;

        $spreadsheet = $this->exportService->forCompany($companyId)->workbook($fiscalYear, $this->expenseDepartmentLabels($companyId), $compareFiscalYear, $comparePeriod);
        $filename = "経費_" . ($fiscalYear - 1970) . "期_{$fiscalYear}年度.xlsx";

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'export',
            'target_type' => 'expense_comparison',
            'context' => ['fiscal_year' => $fiscalYear, 'compare_fiscal_year' => $compareFiscalYear, 'compare_period' => $comparePeriod],
        ]);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }
}
