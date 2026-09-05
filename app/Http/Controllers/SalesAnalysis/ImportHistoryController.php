<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\User;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesImportService;
use Inertia\Inertia;

class ImportHistoryController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private SalesImportService $importService)
    {
    }

    public function index()
    {
        $companyId = $this->salesAnalysisCompanyId();

        if ($companyId === null) {
            return Inertia::render('SalesAnalysis/ImportHistory', [
                'routePrefix' => $this->salesAnalysisRoutePrefix(),
                'hasCompanySelected' => false,
                'imports' => [],
                'currentPage' => 1,
                'lastPage' => 1,
            ]);
        }

        $imports = SalesImport::where('company_id', $companyId)->orderByDesc('imported_at')->paginate(20);

        $userNames = User::whereIn('id', $imports->pluck('imported_by')->unique())
            ->pluck('name', 'id');

        // 版ごとの有効月数（1ヶ月でも有効ならis_active=trueになるだけでは、半期取込のうち一部の月だけ
        // 別版に差し替わった状態を見分けられない。active_month_count/total_month_countで「5/6」のように
        // 可視化する（Codexレビュー2回目 8.1 Medium-2対応）
        $activeCounts = SalesActiveMonth::whereIn('sales_import_id', $imports->pluck('id'))
            ->selectRaw('sales_import_id, COUNT(*) as active_count')
            ->groupBy('sales_import_id')
            ->pluck('active_count', 'sales_import_id');

        $items = $imports->getCollection()->map(function (SalesImport $import) use ($userNames, $activeCounts, $companyId) {
            $totalMonthCount = $this->importService->targetMonths(
                $import->source_type,
                $import->source_year,
                $import->source_month,
                $import->source_month_end
            )->count();
            $activeMonthCount = (int) ($activeCounts->get($import->id) ?? 0);

            return [
                'id' => $import->id,
                'department_label' => SalesDepartments::labelForKey($companyId, $import->department_key),
                'source_type' => $import->source_type,
                'source_year' => $import->source_year,
                'source_month' => $import->source_month,
                'version' => $import->version,
                'original_filename' => $import->original_filename,
                'file_sha256_short' => substr($import->file_sha256, 0, 8),
                'imported_by_name' => $userNames->get($import->imported_by, '不明'),
                'order_count' => $import->order_count,
                'detail_count' => $import->detail_count,
                'total_amount' => $import->total_amount,
                'status' => $import->status,
                'imported_at' => optional($import->imported_at)->toIso8601String(),
                'is_active' => $activeMonthCount > 0,
                'active_month_count' => $activeMonthCount,
                'total_month_count' => $totalMonthCount,
            ];
        });

        return Inertia::render('SalesAnalysis/ImportHistory', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => true,
            'imports' => $items,
            'currentPage' => $imports->currentPage(),
            'lastPage' => $imports->lastPage(),
        ]);
    }
}
