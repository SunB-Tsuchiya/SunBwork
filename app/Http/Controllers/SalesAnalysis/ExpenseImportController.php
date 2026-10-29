<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Services\SalesAnalysis\Exceptions\SalesImportConfirmException;
use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExpenseImportDiffService;
use App\Services\SalesAnalysis\SalesExpenseImportService;
use App\Services\SalesAnalysis\SalesExpenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * 部署別経費表（Phase 21）の取込画面。売上Excelと異なり、1ファイル＝1会計年度＝全社分の
 * ため、プレビュー/確定の2段階は設けず、アップロードと同時に検証・確定する単一ステップにする
 * （受注単位の個別除外のような複雑な部分許可の要件が無いため）。
 */
class ExpenseImportController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(
        private SalesExpenseImportService $importService,
        private SalesExpenseImportDiffService $diffService,
    ) {
    }

    public function create()
    {
        $companyId = $this->salesAnalysisCompanyId();
        $supportsExpenses = $companyId !== null && SalesExpenses::supportsExpensesFor($companyId);

        return Inertia::render('SalesAnalysis/ExpenseImport', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
            'hasCompanySelected' => $companyId !== null,
            'supportsExpenses' => $supportsExpenses,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403, 'この会社では経費取込機能は利用できません。');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'force' => ['nullable', 'boolean'],
        ]);

        $extension = strtolower($data['file']->getClientOriginalExtension());
        if ($extension !== 'xlsx') {
            return response()->json(['message' => 'xlsxファイルを選択してください。'], 422);
        }

        $originalFilename = basename($data['file']->getClientOriginalName());
        $storedPath = $data['file']->store('sales_expense_imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $import = $this->importService->confirm(
                $absolutePath,
                (int) $data['fiscal_year'],
                $originalFilename,
                Auth::id(),
                $companyId,
                (bool) ($data['force'] ?? false)
            );

            // 2版目以降（再取込）なら、前の版との差分の要約を返す。詳細は diff() で取得する
            $diff = $import->version > 1 ? $this->diffService->forCompany($companyId)->diff($import->fiscal_year, $import->version) : null;

            return response()->json([
                'import_id' => $import->id,
                'fiscal_year' => $import->fiscal_year,
                'version' => $import->version,
                'category_count' => $import->category_count,
                'line_item_count' => $import->line_item_count,
                'total_amount' => $import->total_amount,
                'warnings' => $import->warnings ?? [],
                'diff_summary' => ($diff['has_previous'] ?? false)
                    ? array_merge($diff['summary'], ['previous_version' => $diff['previous_version'], 'diff' => $diff['diff'], 'total_old' => $diff['total_old'], 'has_changes' => $diff['has_changes']])
                    : null,
            ]);
        } catch (SalesWorkbookException|SalesImportConfirmException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    private function resolveGatedCompanyId(): int
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403, 'この会社では経費取込機能は利用できません。');

        return $companyId;
    }

    /** 取込履歴（Phase24-6）。版ごとのファイル名・取込者・日時・件数と、前の版との差分を見られるか */
    public function history()
    {
        $companyId = $this->resolveGatedCompanyId();

        return response()->json(['imports' => $this->diffService->forCompany($companyId)->history()]);
    }

    /** 再取込時の差分（Phase24-6）。指定した版（省略時は最新版）と、その直前の版との違い */
    public function diff(Request $request)
    {
        $companyId = $this->resolveGatedCompanyId();
        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->diffService->forCompany($companyId)->diff((int) $data['fiscal_year'], isset($data['version']) ? (int) $data['version'] : null));
    }
}
