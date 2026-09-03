<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesQueryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 売上分析のホーム画面。ファイルの区切り（年次/半期/月次）に関係なく、
 * 部署・年度・月単位でどこまでデータが揃っているかを一覧できるようにする
 * （2026-09-03 Codexレビュー2回目 9〜13章）。
 */
class RegistrationStatusController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix;

    public function __construct(private SalesQueryService $queryService)
    {
    }

    public function index()
    {
        return Inertia::render('SalesAnalysis/RegistrationStatus', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => SalesDepartments::LABELS,
            'enabledDepartmentKeys' => SalesDepartments::ENABLED_KEYS,
        ]);
    }

    public function data(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::ENABLED_KEYS)],
        ]);

        return response()->json([
            'department_key' => $data['department_key'],
            'as_of' => now()->toDateString(),
            'years' => $this->queryService->registrationStatusByDepartment($data['department_key']),
        ]);
    }

    public function files(Request $request)
    {
        $data = $request->validate([
            'department_key' => ['required', 'string', Rule::in(SalesDepartments::ENABLED_KEYS)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json([
            'files' => $this->queryService->registrationStatusFiles($data['department_key'], (int) $data['year']),
        ]);
    }
}
