<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Models\Sales\SalesAuditLog;
use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Services\SalesAnalysis\ClientGroupService;
use App\Services\SalesAnalysis\ClientNameNormalizer;
use App\Services\SalesAnalysis\SalesDepartments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * 得意先統合設定画面（正規化候補・グループCRUD・統合プレビュー）。
 * 2026-09-04 PLAN1.md「Phase 7-0 詳細設計」に基づき新規作成。
 * 正規化は候補提示のみに留め、グループ作成・メンバー追加は常にユーザーの手動操作でのみ確定する
 * （PLAN 2.7「自動統合をしないことのtest」を満たす）。
 */
class ClientGroupController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    public function __construct(private ClientGroupService $service)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/ClientGroups', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => $companyId !== null,
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
        ]);
    }

    public function data(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->service->forCompany($companyId);

        return response()->json([
            'candidates' => $this->service->candidates(),
            'groups' => $this->service->groups(),
            'unassigned_clients' => $this->service->unassignedClients(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_names' => ['required', 'array', 'min:1'],
            'client_names.*' => ['required', 'string', 'max:255'],
        ]);

        $conflicts = SalesClientGroupMember::where('company_id', $companyId)
            ->whereIn('client_name', $data['client_names'])
            ->pluck('client_name');
        if ($conflicts->isNotEmpty()) {
            return response()->json([
                'message' => '既に他のグループへ所属している得意先名が含まれています。',
                'conflicts' => $conflicts->values(),
            ], 422);
        }

        $userId = Auth::id();
        $group = SalesClientGroup::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach (array_unique($data['client_names']) as $clientName) {
            SalesClientGroupMember::create([
                'sales_client_group_id' => $group->id,
                'company_id' => $companyId,
                'client_name' => $clientName,
                'normalized_name' => ClientNameNormalizer::normalize($clientName),
            ]);
        }

        SalesAuditLog::create([
            'user_id' => $userId,
            'action' => 'client_group_create',
            'target_type' => 'client_group',
            'target_id' => $group->id,
            'context' => ['member_count' => count($data['client_names'])],
        ]);

        return response()->json(['id' => $group->id], 201);
    }

    public function update(Request $request, SalesClientGroup $group)
    {
        $this->authorizeGroupCompany($group);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $group->update(['name' => $data['name'], 'updated_by' => Auth::id()]);

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'client_group_rename',
            'target_type' => 'client_group',
            'target_id' => $group->id,
            'context' => [],
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(SalesClientGroup $group)
    {
        $this->authorizeGroupCompany($group);

        $memberCount = $group->members()->count();
        $groupId = $group->id;
        $group->delete();

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'client_group_delete',
            'target_type' => 'client_group',
            'target_id' => $groupId,
            'context' => ['member_count' => $memberCount],
        ]);

        return response()->json(['ok' => true]);
    }

    public function addMember(Request $request, SalesClientGroup $group)
    {
        $companyId = $this->authorizeGroupCompany($group);

        $data = $request->validate(['client_name' => ['required', 'string', 'max:255']]);

        if (SalesClientGroupMember::where('company_id', $companyId)->where('client_name', $data['client_name'])->exists()) {
            return response()->json(['message' => 'この得意先名は既に他のグループへ所属しています。'], 422);
        }

        SalesClientGroupMember::create([
            'sales_client_group_id' => $group->id,
            'company_id' => $companyId,
            'client_name' => $data['client_name'],
            'normalized_name' => ClientNameNormalizer::normalize($data['client_name']),
        ]);
        $group->update(['updated_by' => Auth::id()]);

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'client_group_member_add',
            'target_type' => 'client_group',
            'target_id' => $group->id,
            'context' => [],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function removeMember(SalesClientGroup $group, SalesClientGroupMember $member)
    {
        $this->authorizeGroupCompany($group);
        abort_unless($member->sales_client_group_id === $group->id, 404);

        $member->delete();
        $group->update(['updated_by' => Auth::id()]);

        SalesAuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'client_group_member_remove',
            'target_type' => 'client_group',
            'target_id' => $group->id,
            'context' => [],
        ]);

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        $this->service->forCompany($companyId);

        $data = $request->validate([
            'client_names' => ['required', 'array', 'min:1'],
            'client_names.*' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($this->service->preview($data['client_names']));
    }

    /**
     * ルートモデルバインディングで解決した$groupが現在の会社のものであることを確認する
     * （会社別データ分離、2026-09-05）。IDを推測して他社のグループを操作されるのを防ぐ。
     */
    private function authorizeGroupCompany(SalesClientGroup $group): int
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless($group->company_id === $companyId, 404);

        return $companyId;
    }
}
