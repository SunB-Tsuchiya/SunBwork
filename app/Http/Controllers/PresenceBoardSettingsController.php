<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\IrukaStatusOrder;
use App\Models\User;
use App\Models\UserPresenceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PresenceBoardSettingsController extends Controller
{
    /**
     * 在席ボード管理画面
     * Admin: 全部署 / Leader: 自部署のみ
     */
    public function index()
    {
        $authUser = Auth::user();
        $userIds  = $this->getAllowedUserIds($authUser);
        $isAdmin  = in_array($authUser->user_role, ['admin', 'superadmin', 'clerk']);

        $users = User::with(['department', 'presenceStatus'])
            ->whereIn('id', $userIds)
            ->where('is_ghost', false)
            ->whereNull('ghost_owner_id')
            ->get()
            ->sortBy([
                fn($u) => $u->presenceStatus?->sort_order ?? 9999,
                fn($u) => $u->department_id ?? 9999,
                fn($u) => $u->name,
            ])
            ->values()
            ->map(fn(User $u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'department'    => $u->department?->name ?? '未所属',
                'department_id' => $u->department_id,
                'sort_order'    => $u->presenceStatus?->sort_order ?? 0,
                'is_hidden'     => (bool) ($u->presenceStatus?->is_hidden ?? false),
            ]);

        $departments = $isAdmin
            ? Department::where('company_id', $authUser->company_id)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $statusOrders = IrukaStatusOrder::getOrCreateForCompany($authUser->company_id)
            ->map(fn ($o) => [
                'slug'      => $o->slug,
                'sort_order'=> $o->sort_order,
                'is_active' => $o->is_active,
            ]);

        return Inertia::render('Iruka/BoardSettings', [
            'users'        => $users,
            'departments'  => $departments,
            'isAdmin'      => $isAdmin,
            'statusOrders' => $statusOrders,
        ]);
    }

    /**
     * 並び順・表示/非表示を一括保存
     * リクエスト: [{ user_id, sort_order, is_hidden }, ...]
     */
    public function update(Request $request)
    {
        $authUser = Auth::user();
        $allowedIds = $this->getAllowedUserIds($authUser);

        $items = $request->validate([
            'items'               => 'required|array',
            'items.*.user_id'     => 'required|integer',
            'items.*.sort_order'  => 'required|integer|min:0',
            'items.*.is_hidden'   => 'required|boolean',
        ])['items'];

        foreach ($items as $item) {
            $userId = (int) $item['user_id'];
            if (!in_array($userId, $allowedIds, true)) {
                continue;
            }
            UserPresenceStatus::updateOrCreate(
                ['user_id' => $userId],
                [
                    'sort_order' => $item['sort_order'],
                    'is_hidden'  => $item['is_hidden'],
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * ステータス表示順・有効/無効を一括保存
     */
    public function updateStatuses(Request $request)
    {
        $authUser = Auth::user();

        $items = $request->validate([
            'items'              => 'required|array',
            'items.*.slug'       => 'required|string|max:50',
            'items.*.sort_order' => 'required|integer|min:0',
            'items.*.is_active'  => 'required|boolean',
        ])['items'];

        foreach ($items as $item) {
            IrukaStatusOrder::updateOrCreate(
                ['company_id' => $authUser->company_id, 'slug' => $item['slug']],
                ['sort_order' => $item['sort_order'], 'is_active' => $item['is_active']]
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 操作者が管理できるユーザーIDの配列を返す
     */
    private function getAllowedUserIds(User $authUser): array
    {
        $role = $authUser->user_role;

        // Admin / SuperAdmin / Clerk は全部署
        if (in_array($role, ['admin', 'superadmin', 'clerk'])) {
            return User::where('company_id', $authUser->company_id)
                ->where('is_ghost', false)
                ->whereNull('ghost_owner_id')
                ->pluck('id')
                ->map(fn($v) => (int) $v)
                ->all();
        }

        // Leader は自分の所属部署のメンバーのみ（全Leaderが操作可能）
        if ($role === 'leader') {
            if (!$authUser->department_id) {
                return [];
            }

            return User::where('company_id', $authUser->company_id)
                ->where('department_id', $authUser->department_id)
                ->where('is_ghost', false)
                ->whereNull('ghost_owner_id')
                ->pluck('id')
                ->map(fn($v) => (int) $v)
                ->all();
        }

        return [];
    }
}
