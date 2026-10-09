<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Services\SalesAnalysis\SalesExpenses;
use Inertia\Inertia;

/**
 * 「Excel取込」タブの入口。年度別データ（売上Excel）取込と経費取込の2本のボタンが
 * 別タブに分かれて迷いやすいとの実機フィードバックを受け、1つの入口から選ぶ構成にする
 * （2026-10-09）。実際の取込処理はそれぞれ既存のImportController/ExpenseImportControllerへ進む。
 */
class ImportHubController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function index()
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/ImportHub', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => $companyId !== null,
            'supportsExpenses' => $companyId !== null && SalesExpenses::supportsExpensesFor($companyId),
        ]);
    }
}
