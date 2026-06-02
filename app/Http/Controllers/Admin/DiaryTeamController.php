<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\DiaryTeam;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DiaryTeamController extends Controller
{
    use ChecksAdminPermission, ResolvesContextCompany;

    public function index()
    {
        $this->requireAdminPermission('diary_management');
        $companyId = $this->contextCompanyId();

        $diaryTeams = DiaryTeam::with(['leaders:id,name,user_role', 'members:id,name'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'leader_count' => $t->leaders->count(),
                'member_count' => $t->members->count(),
                'leaders'      => $t->leaders->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'user_role' => $u->user_role]),
            ]);

        return Inertia::render('Admin/DiaryTeams/Index', [
            'diaryTeams' => $diaryTeams,
        ]);
    }

    public function create()
    {
        $this->requireAdminPermission('diary_management');
        $companyId = $this->contextCompanyId();

        [$leaderCandidates, $memberCandidates] = $this->getCandidates($companyId);

        return Inertia::render('Admin/DiaryTeams/Create', [
            'leaderCandidates' => $leaderCandidates,
            'memberCandidates' => $memberCandidates,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdminPermission('diary_management');
        $companyId = $this->contextCompanyId() ?? Auth::user()->company_id;

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'leader_ids'   => 'array',
            'leader_ids.*' => 'integer|exists:users,id',
            'member_ids'   => 'array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $diaryTeam = DiaryTeam::create([
            'company_id'  => $companyId,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $diaryTeam->leaders()->sync($this->filterLeaderIds($validated['leader_ids'] ?? [], $companyId));
        $diaryTeam->members()->sync($this->filterMemberIds($validated['member_ids'] ?? [], $companyId));

        return redirect()->route('admin.diary_teams.index')->with('success', '日報権限チームを作成しました');
    }

    public function edit($id)
    {
        $this->requireAdminPermission('diary_management');
        $diaryTeam = DiaryTeam::with(['leaders:id,name,user_role', 'members:id,name'])->findOrFail($id);
        $companyId = $diaryTeam->company_id;

        [$leaderCandidates, $memberCandidates] = $this->getCandidates($companyId);

        return Inertia::render('Admin/DiaryTeams/Edit', [
            'diaryTeam'        => [
                'id'          => $diaryTeam->id,
                'name'        => $diaryTeam->name,
                'description' => $diaryTeam->description,
            ],
            'leaderIds'        => $diaryTeam->leaders->pluck('id')->values()->toArray(),
            'memberIds'        => $diaryTeam->members->pluck('id')->values()->toArray(),
            'leaderCandidates' => $leaderCandidates,
            'memberCandidates' => $memberCandidates,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->requireAdminPermission('diary_management');
        $diaryTeam = DiaryTeam::findOrFail($id);
        $companyId = $diaryTeam->company_id;

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'leader_ids'   => 'array',
            'leader_ids.*' => 'integer|exists:users,id',
            'member_ids'   => 'array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $diaryTeam->update([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $diaryTeam->leaders()->sync($this->filterLeaderIds($validated['leader_ids'] ?? [], $companyId));
        $diaryTeam->members()->sync($this->filterMemberIds($validated['member_ids'] ?? [], $companyId));

        return redirect()->route('admin.diary_teams.index')->with('success', '日報権限チームを更新しました');
    }

    public function destroy($id)
    {
        $this->requireAdminPermission('diary_management');
        DiaryTeam::findOrFail($id)->delete();

        return redirect()->route('admin.diary_teams.index')->with('success', '日報権限チームを削除しました');
    }

    private function getCandidates(?int $companyId): array
    {
        $leaderCandidates = User::select(['id', 'name', 'user_role'])
            ->whereIn('user_role', ['clerk', 'coordinator', 'proof_coordinator'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        $memberCandidates = User::select(['id', 'name', 'user_role'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        return [$leaderCandidates, $memberCandidates];
    }

    private function filterLeaderIds(array $ids, ?int $companyId): array
    {
        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->whereIn('user_role', ['clerk', 'coordinator', 'proof_coordinator'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->pluck('id')
            ->toArray();
    }

    private function filterMemberIds(array $ids, ?int $companyId): array
    {
        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->pluck('id')
            ->toArray();
    }
}
