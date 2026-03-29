<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Models\DispatchProfile;
use App\Models\User;
use App\Models\UserEmploymentSetting;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DispatchManagementController extends Controller
{
    use ChecksLeaderPermission;

    /**
     * 派遣・業務委託管理 一覧
     * リーダーが担当する部署・チームのユーザーを表示する。
     */
    public function index(Request $request)
    {
        $this->requireLeaderPermission('dispatch_management');

        $viewer = Auth::user();
        $userIds = $this->buildPermittedUserIds($viewer);

        $users = User::whereIn('id', $userIds)
            ->with(['assignment', 'department', 'employmentSetting', 'dispatchProfile'])
            ->orderBy('department_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'email'               => $u->email,
                'user_role'           => $u->user_role,
                'employment_type'     => $u->employment_type ?? 'regular',
                'employment_type_label' => $u->employmentTypeLabel(),
                'assignment_name'     => $u->assignment?->name ?? '',
                'department_name'     => $u->department?->name ?? '',
                'diary_required'      => $u->isDiaryRequired(),
                'diary_required_override' => $u->employmentSetting?->diary_required,
                'agency_name'         => $u->dispatchProfile?->agency_name ?? '',
                'contract_end'        => $u->dispatchProfile?->contract_end?->format('Y-m-d') ?? '',
            ]);

        return Inertia::render('Leader/DispatchManagement/Index', [
            'users' => $users,
        ]);
    }

    /**
     * 派遣・業務委託 個人設定 編集フォーム
     */
    public function edit(User $dispatchUser)
    {
        $this->requireLeaderPermission('dispatch_management');

        $this->authorizeUserAccess($dispatchUser);

        $dispatchUser->load(['assignment', 'department', 'employmentSetting', 'dispatchProfile']);

        return Inertia::render('Leader/DispatchManagement/Edit', [
            'dispatchUser' => [
                'id'              => $dispatchUser->id,
                'name'            => $dispatchUser->name,
                'email'           => $dispatchUser->email,
                'user_role'       => $dispatchUser->user_role,
                'employment_type' => $dispatchUser->employment_type ?? 'regular',
                'assignment_name' => $dispatchUser->assignment?->name ?? '',
                'department_name' => $dispatchUser->department?->name ?? '',
                'diary_required'  => $dispatchUser->isDiaryRequired(),
                'diary_required_override' => $dispatchUser->employmentSetting?->diary_required,
                'agency_name'     => $dispatchUser->dispatchProfile?->agency_name ?? '',
                'contract_start'  => $dispatchUser->dispatchProfile?->contract_start?->format('Y-m-d') ?? '',
                'contract_end'    => $dispatchUser->dispatchProfile?->contract_end?->format('Y-m-d') ?? '',
                'dispatch_notes'  => $dispatchUser->dispatchProfile?->notes ?? '',
            ],
        ]);
    }

    /**
     * 雇用形態・設定を保存
     */
    public function update(Request $request, User $dispatchUser)
    {
        $this->requireLeaderPermission('dispatch_management');

        $this->authorizeUserAccess($dispatchUser);

        $validated = $request->validate([
            'employment_type'  => 'required|in:regular,contract,dispatch,outsource',
            'diary_required'   => 'nullable|boolean',
            'agency_name'      => 'nullable|string|max:255',
            'contract_start'   => 'nullable|date',
            'contract_end'     => 'nullable|date|after_or_equal:contract_start',
            'dispatch_notes'   => 'nullable|string|max:1000',
        ]);

        // 雇用形態を users テーブルに保存
        $dispatchUser->employment_type = $validated['employment_type'];
        $dispatchUser->save();

        // diary_required が明示指定された場合は user_employment_settings に保存
        // null（明示指定なし）の場合はレコードを削除して「デフォルト」に戻す
        if (array_key_exists('diary_required', $validated) && $validated['diary_required'] !== null) {
            UserEmploymentSetting::updateOrCreate(
                ['user_id' => $dispatchUser->id],
                ['diary_required' => (bool) $validated['diary_required']]
            );
        } else {
            // デフォルト動作に戻す（employment_type から自動判定）
            UserEmploymentSetting::where('user_id', $dispatchUser->id)->delete();
        }

        // dispatch_profiles: agency_name / contract_start / contract_end / notes を保存
        // dispatch / outsource の場合のみ保持。その他の場合はレコードを削除
        $isDispatchType = in_array($validated['employment_type'], ['dispatch', 'outsource']);
        $hasProfileData = ($validated['agency_name'] ?? '') !== ''
            || ($validated['contract_start'] ?? '') !== ''
            || ($validated['contract_end'] ?? '') !== ''
            || ($validated['dispatch_notes'] ?? '') !== '';

        if ($isDispatchType && $hasProfileData) {
            DispatchProfile::updateOrCreate(
                ['user_id' => $dispatchUser->id],
                [
                    'agency_name'    => $validated['agency_name'] ?? null,
                    'contract_start' => $validated['contract_start'] ?? null,
                    'contract_end'   => $validated['contract_end'] ?? null,
                    'notes'          => $validated['dispatch_notes'] ?? null,
                ]
            );
        } elseif (! $isDispatchType) {
            DispatchProfile::where('user_id', $dispatchUser->id)->delete();
        }

        return redirect()
            ->route('leader.dispatch_management.index')
            ->with('success', "{$dispatchUser->name} の雇用形態を更新しました。");
    }

    /**
     * ログインリーダーが管理できるユーザー ID リストを返す
     */
    private function buildPermittedUserIds(User $viewer): array
    {
        // SuperAdmin / Admin は自社全員
        if ($viewer->isSuperAdmin() || $viewer->isAdmin()) {
            return User::where('company_id', $viewer->company_id)
                ->pluck('id')->toArray();
        }

        $userIds = [];
        $teams = Team::where('leader_id', $viewer->id)
            ->whereIn('team_type', ['department', 'unit'])
            ->get();

        foreach ($teams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                $ids = User::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->pluck('id')->toArray();
                $userIds = array_merge($userIds, $ids);
            }
            if ($team->team_type === 'unit') {
                $members = $team->users()->pluck('users.id')->toArray();
                $userIds = array_merge($userIds, $members);
            }
        }

        // サブリーダーとして所属するチームも対象
        $subTeams = $viewer->subLeaderTeams ?? collect();
        foreach ($subTeams as $team) {
            $members = $team->users()->pluck('users.id')->toArray();
            $userIds = array_merge($userIds, $members);
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    /**
     * リーダーが対象ユーザーにアクセスできるか確認
     */
    private function authorizeUserAccess(User $target): void
    {
        $viewer = Auth::user();
        if ($viewer->isSuperAdmin() || $viewer->isAdmin()) {
            return;
        }
        $permitted = $this->buildPermittedUserIds($viewer);
        if (! in_array($target->id, $permitted)) {
            abort(403, 'このユーザーにアクセスする権限がありません。');
        }
    }
}
