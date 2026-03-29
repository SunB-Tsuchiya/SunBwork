<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Models\Assignment;
use App\Models\PositionTitle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    use ChecksLeaderPermission;

    /**
     * 部署リーダーチームを取得。該当しない場合は 403。
     */
    private function getDeptTeam(): Team
    {
        $user = Auth::user();
        $deptTeam = Team::where('team_type', 'department')
            ->where('company_id', $user->company_id)
            ->where('leader_id', $user->id)
            ->first();

        if (! $deptTeam) {
            abort(403, 'この機能は部署リーダーのみ使用できます。');
        }

        return $deptTeam;
    }

    /** ユーザー一覧（同部署のみ） */
    public function index()
    {
        $this->requireLeaderPermission('user_management');
        $deptTeam = $this->getDeptTeam();

        $users = User::where('company_id', Auth::user()->company_id)
            ->where('department_id', $deptTeam->department_id)
            ->with(['assignment'])
            ->orderByRaw("FIELD(user_role, 'leader', 'coordinator', 'user')")
            ->orderBy('name')
            ->get();

        $assignments = Assignment::where('department_id', $deptTeam->department_id)
            ->where('active', true)
            ->get(['id', 'name']);

        return Inertia::render('Leader/UserManagement/Index', [
            'users'       => $users,
            'assignments' => $assignments,
        ]);
    }

    /** 新規ユーザー登録フォーム */
    public function create()
    {
        $this->requireLeaderPermission('user_management');
        $deptTeam = $this->getDeptTeam();

        $assignments = Assignment::where('department_id', $deptTeam->department_id)
            ->where('active', true)
            ->get(['id', 'name']);

        $leaderTitles = PositionTitle::where('applicable_role', 'leader')
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return Inertia::render('Leader/UserManagement/Create', [
            'assignments'  => $assignments,
            'leaderTitles' => $leaderTitles,
        ]);
    }

    /** ユーザー登録処理 */
    public function store(Request $request)
    {
        $this->requireLeaderPermission('user_management');
        $deptTeam = $this->getDeptTeam();
        $leader   = Auth::user();

        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|string|lowercase|email|max:255|unique:users',
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
            'assignment_id'    => 'required|exists:assignments,id',
            'user_role'        => 'required|in:leader,coordinator,user',
            'employment_type'  => 'nullable|in:regular,contract,dispatch,outsource',
            'position_title_id'=> 'nullable|exists:position_titles,id',
        ]);

        $companyTeam = Team::where('company_id', $leader->company_id)
            ->where('team_type', 'company')
            ->first();

        $departmentTeam = Team::where('company_id', $leader->company_id)
            ->where('department_id', $deptTeam->department_id)
            ->where('team_type', 'department')
            ->first();

        $positionTitleId = ($request->user_role === 'leader')
            ? $request->input('position_title_id')
            : null;

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'company_id'        => $leader->company_id,
            'department_id'     => $deptTeam->department_id,
            'assignment_id'     => $request->assignment_id,
            'position_title_id' => $positionTitleId,
            'current_team_id'   => $leader->company_id,
            'user_role'         => $request->user_role,
            'employment_type'   => $request->input('employment_type', 'regular'),
            'email_verified_at' => now(),
        ]);

        if ($companyTeam) {
            $user->teams()->attach($companyTeam->id, ['role' => 'viewer']);
        }
        if ($departmentTeam) {
            $user->teams()->attach($departmentTeam->id, ['role' => 'viewer']);
        }

        return redirect()->route('leader.user_management.index')
            ->with('success', 'ユーザーを登録しました。');
    }

    /** 編集フォーム */
    public function edit(User $user)
    {
        $this->requireLeaderPermission('user_management');
        $deptTeam = $this->getDeptTeam();

        if ($user->department_id !== $deptTeam->department_id) {
            abort(403);
        }

        $assignments = Assignment::where('department_id', $deptTeam->department_id)
            ->where('active', true)
            ->get(['id', 'name']);

        $leaderTitles = PositionTitle::where('applicable_role', 'leader')
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return Inertia::render('Leader/UserManagement/Edit', [
            'editUser'     => $user,
            'assignments'  => $assignments,
            'leaderTitles' => $leaderTitles,
        ]);
    }

    /** 更新処理 */
    public function update(Request $request, User $user)
    {
        $this->requireLeaderPermission('user_management');
        $deptTeam = $this->getDeptTeam();

        if ($user->department_id !== $deptTeam->department_id) {
            abort(403);
        }

        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'assignment_id'    => 'required|exists:assignments,id',
            'user_role'        => 'required|in:leader,coordinator,user',
            'employment_type'  => 'nullable|in:regular,contract,dispatch,outsource',
            'position_title_id'=> 'nullable|exists:position_titles,id',
        ]);

        $positionTitleId = ($request->user_role === 'leader')
            ? $request->input('position_title_id')
            : null;

        $user->update([
            'name'              => $request->name,
            'email'             => $request->email,
            'assignment_id'     => $request->assignment_id,
            'position_title_id' => $positionTitleId,
            'user_role'         => $request->user_role,
            'employment_type'   => $request->input('employment_type', $user->employment_type ?? 'regular'),
        ]);

        return redirect()->route('leader.user_management.index')
            ->with('success', 'ユーザー情報を更新しました。');
    }

    /** 一覧編集モードからの一括保存 */
    public function bulkUpdate(Request $request)
    {
        $this->requireLeaderPermission('user_management');
        $deptTeam = $this->getDeptTeam();

        $request->validate([
            'users'                    => 'required|array',
            'users.*.id'               => 'required|exists:users,id',
            'users.*.name'             => 'required|string|max:255',
            'users.*.assignment_id'    => 'required|exists:assignments,id',
            'users.*.user_role'        => 'required|in:leader,coordinator,user',
            'users.*.employment_type'  => 'nullable|in:regular,contract,dispatch,outsource',
        ]);

        foreach ($request->users as $data) {
            $user = User::find($data['id']);

            // 同部署のユーザーのみ更新
            if (! $user || $user->department_id !== $deptTeam->department_id) {
                continue;
            }

            $user->update([
                'name'            => $data['name'],
                'assignment_id'   => $data['assignment_id'],
                'user_role'       => $data['user_role'],
                'employment_type' => $data['employment_type'] ?? $user->employment_type ?? 'regular',
            ]);
        }

        return redirect()->route('leader.user_management.index')
            ->with('success', '変更を保存しました。');
    }
}
