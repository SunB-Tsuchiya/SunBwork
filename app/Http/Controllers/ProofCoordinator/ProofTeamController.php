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
        // 現在のチームメンバー
        $members = ProofTeamMember::with('user:id,name,email')
            ->get()
            ->map(fn($m) => [
                'id'   => $m->id,
                'user' => $m->user,
            ]);

        // 追加できるユーザー候補（チームに未登録のユーザー）
        $memberUserIds = ProofTeamMember::pluck('user_id');
        $candidates = User::whereNotIn('id', $memberUserIds)
            ->whereNotIn('user_role', ['superadmin'])
            ->orderBy('name')
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

        ProofTeamMember::firstOrCreate(['user_id' => $data['user_id']]);

        return back()->with('success', 'メンバーを追加しました。');
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
