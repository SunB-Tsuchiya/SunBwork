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

        // context: ロールに応じてフロントのカラーテーマを決定
        $context = match ($authUser->user_role) {
            'superadmin', 'admin', 'clerk' => 'admin',
            'leader'                       => 'leader',
            default                        => 'admin',
        };

        $employmentPriority = [
            'regular'   => 1,
            'contract'  => 2,
            'dispatch'  => 3,
            'outsource' => 4,
        ];

        $users = User::with(['department', 'presenceStatus', 'positionTitle'])
            ->whereIn('id', $userIds)
            ->where('is_ghost', false)
            ->whereNull('ghost_owner_id')
            ->get()
            ->sortBy([
                fn($a, $b) => ($a->presenceStatus?->sort_order ?? 9999) <=> ($b->presenceStatus?->sort_order ?? 9999),
                fn($a, $b) => ($a->positionTitle?->sort_order ?? 9999) <=> ($b->positionTitle?->sort_order ?? 9999),
                fn($a, $b) => ($employmentPriority[$a->employment_type ?? 'regular'] ?? 99) <=> ($employmentPriority[$b->employment_type ?? 'regular'] ?? 99),
                fn($a, $b) => $a->name <=> $b->name,
            ])
            ->values()
            ->map(fn(User $u) => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'department'          => $u->department?->name ?? '未所属',
                'department_id'       => $u->department_id,
                'sort_order'          => $u->presenceStatus?->sort_order ?? 0,
                'is_hidden'           => (bool) ($u->presenceStatus?->is_hidden ?? false),
                'position_title'      => $u->positionTitle?->name,
                'position_sort_order' => $u->positionTitle?->sort_order ?? 9999,
                'employment_type'     => $u->employment_type ?? 'regular',
            ]);

        $departments = $isAdmin
            ? Department::where('company_id', $authUser->company_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name'])
            : collect();

        $statusOrders = IrukaStatusOrder::getOrCreateForCompany($authUser->company_id)
            ->map(fn ($o) => [
                'id'           => $o->id,
                'slug'         => $o->slug,
                'sort_order'   => $o->sort_order,
                'is_active'    => $o->is_active,
                'custom_label' => $o->custom_label,
                'custom_color' => $o->custom_color,
            ]);

        return Inertia::render('Iruka/BoardSettings', [
            'users'        => $users,
            'departments'  => $departments,
            'isAdmin'      => $isAdmin,
            'statusOrders' => $statusOrders,
            'context'      => $context,
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
            $ps = UserPresenceStatus::where('user_id', $userId)->first();
            if ($ps) {
                $ps->update([
                    'sort_order' => $item['sort_order'],
                    'is_hidden'  => $item['is_hidden'],
                ]);
            } else {
                // 新規作成時は status を 'left' にする（DB デフォルトの 'present' にしない）
                UserPresenceStatus::create([
                    'user_id'    => $userId,
                    'status'     => 'left',
                    'sort_order' => $item['sort_order'],
                    'is_hidden'  => $item['is_hidden'],
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * ステータス表示順・有効/無効・カスタムラベル/カラーを一括保存
     */
    public function updateStatuses(Request $request)
    {
        $authUser = Auth::user();

        $items = $request->validate([
            'items'               => 'required|array',
            'items.*.slug'        => 'required|string|max:50',
            'items.*.sort_order'  => 'required|integer|min:0',
            'items.*.is_active'   => 'required|boolean',
            'items.*.custom_label'=> 'nullable|string|max:50',
            'items.*.custom_color'=> 'nullable|string|max:30',
        ])['items'];

        foreach ($items as $item) {
            IrukaStatusOrder::updateOrCreate(
                ['company_id' => $authUser->company_id, 'slug' => $item['slug']],
                [
                    'sort_order'   => $item['sort_order'],
                    'is_active'    => $item['is_active'],
                    'custom_label' => $item['custom_label'] ?: null,
                    'custom_color' => $item['custom_color'] ?: null,
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * カスタムステータスを新規追加
     */
    public function createStatus(Request $request)
    {
        $authUser = Auth::user();

        $validated = $request->validate([
            'custom_label' => 'required|string|max:50',
            'custom_color' => 'required|string|max:30',
        ]);

        $slug = 'cust_' . time();
        $maxOrder = IrukaStatusOrder::where('company_id', $authUser->company_id)->max('sort_order') ?? 0;

        $record = IrukaStatusOrder::create([
            'company_id'   => $authUser->company_id,
            'slug'         => $slug,
            'sort_order'   => $maxOrder + 1,
            'is_active'    => true,
            'custom_label' => $validated['custom_label'],
            'custom_color' => $validated['custom_color'],
        ]);

        return response()->json([
            'id'           => $record->id,
            'slug'         => $record->slug,
            'sort_order'   => $record->sort_order,
            'is_active'    => $record->is_active,
            'custom_label' => $record->custom_label,
            'custom_color' => $record->custom_color,
        ]);
    }

    /**
     * カスタムステータスを削除（cust_ プレフィックスのもののみ）
     */
    public function deleteStatus(IrukaStatusOrder $statusOrder)
    {
        $authUser = Auth::user();

        if ($statusOrder->company_id !== $authUser->company_id) {
            abort(403);
        }

        if (!str_starts_with($statusOrder->slug, 'cust_')) {
            return response()->json(['error' => '組み込みステータスは削除できません'], 422);
        }

        $statusOrder->delete();

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
