<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\ProofTeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProofTeamController extends Controller
{
    /**
     * GET /proof-coordinator/team
     * 校正チーム管理
     */
    public function index(): Response
    {
        // 現在のチームメンバー（sort_order 順）
        $members = ProofTeamMember::with('user:id,name,email')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'sort_order' => $m->sort_order,
                'user'       => $m->user,
            ]);

        // 追加できるユーザー候補（チームに未登録のユーザー）
        $memberUserIds = ProofTeamMember::pluck('user_id');
        $candidates = User::whereNotIn('id', $memberUserIds)
            ->whereNotIn('user_role', ['superadmin'])
            ->ordered()
            ->get(['id', 'name', 'email']);

        return Inertia::render('ProofCoordinator/Team/Index', [
            'members'    => $members,
            'candidates' => $candidates,
        ]);
    }

    /**
     * POST /proof-coordinator/team
     * メンバー追加
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $maxOrder = ProofTeamMember::max('sort_order') ?? 0;

        ProofTeamMember::firstOrCreate(
            ['user_id' => $data['user_id']],
            ['sort_order' => $maxOrder + 1]
        );

        return back()->with('success', 'メンバーを追加しました。');
    }

    /**
     * POST /proof-coordinator/team/reorder
     * メンバー並び替え保存
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:proof_team_members,id'],
        ]);

        foreach ($data['ids'] as $order => $id) {
            ProofTeamMember::where('id', $id)->update(['sort_order' => $order + 1]);
        }

        return back()->with('success', '並び順を保存しました。');
    }

    /**
     * DELETE /proof-coordinator/team/{proofTeamMember}
     * メンバー削除
     */
    public function destroy(ProofTeamMember $proofTeamMember)
    {
        $proofTeamMember->delete();

        return back()->with('success', 'メンバーを削除しました。');
    }
}
