<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use App\Services\Portfolio\MemberList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * ポートフォリオの利用メンバー設定（SuperAdmin のみ）。
 * 画面と保存の形は、申請者管理（Admin\ApplicantController / Pages/Admin/Applicants/Show.vue）と同じ:
 * ユーザーごとのスイッチを動かして、まとめて「保存する」。並び順も同じ（MemberList）。
 * 一覧に出すのは、サン・ブレーン（company_type = 'sunbrain'）所属のユーザー。Admin も一般メンバーと同じようにスイッチで決める。
 * **Admin は、スイッチが未設定なら自動で ON**（部署が未設定でも出す）。OFF にした Admin だけが閲覧専用。
 * SuperAdmin だけは、許可に関わらず常に使える（試用）ため、会社と部署が設定されていれば名前を出し、スイッチは ON 固定（always_on）。
 */
class PortfolioPermissionController extends Controller
{
    /** 許可に関わらず常に自分のポートフォリオを作れるロール（試用中の SuperAdmin）。Admin は、スイッチで決める */
    private const ALWAYS_ON_ROLES = ['superadmin'];

    public function index(Request $request)
    {
        $this->authorizeManage($request);

        $users = MemberList::sort(
            $this->listedUsers()->with(['department:id,name', 'positionTitle:id,name,sort_order', 'assignment:id,name'])->get()
        );
        $enabledMap = PortfolioPermission::whereIn('user_id', $users->pluck('id'))->pluck('enabled', 'user_id');

        return Inertia::render('Portfolio/Permissions', [
            'routePrefix' => Str::before($request->route()->getName(), '.portfolio.'),
            'users' => $users->map(function (User $u) use ($enabledMap) {
                $alwaysOn = in_array($u->user_role, self::ALWAYS_ON_ROLES, true);

                return MemberList::row($u) + [
                    'assignment_name' => $u->assignment?->name,
                    // SuperAdmin は常に使える（スイッチは固定表示）。Admin を含むそれ以外は、保存された許可
                    'always_on' => $alwaysOn,
                    'role_label' => match ($u->user_role) {
                        'superadmin' => 'SuperAdmin',
                        'admin' => 'Admin',
                        default => null,
                    },
                    // 行がなければ、Admin は自動で ON、それ以外は OFF（PortfolioAccess::switchedOn と同じ規則）
                    'enabled' => $alwaysOn ? true : (bool) ($enabledMap[$u->id] ?? $u->isAdmin()),
                ];
            })->values(),
            // 担当（制作・営業など）。部署ごとに同名の担当があるため名前で一意にして絞り込みに使う
            'assignmentNames' => $users->map(fn (User $u) => $u->assignment?->name)->filter()->unique()->sort()->values(),
            'departments' => Department::whereIn('id', $users->pluck('department_id')->filter()->unique())
                ->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    /**
     * スイッチを、まとめて保存する。リクエスト: items = [{ user_id, enabled }, ...]（対象外のユーザーは受け付けない）。
     * 変更のない行は触らない（許可した人・日時を残すため）。
     */
    public function update(Request $request)
    {
        $this->authorizeManage($request);

        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.user_id' => ['required', 'integer'],
            'items.*.enabled' => ['required', 'boolean'],
        ])['items'];

        // 操作できるユーザー（id => ロール）。行がないときの既定値は、Admin が ON・それ以外が OFF
        $allowed = $this->switchableUsers()->pluck('user_role', 'id')->mapWithKeys(fn ($role, $id) => [(int) $id => $role])->all();
        $current = PortfolioPermission::pluck('enabled', 'user_id');
        $changed = 0;

        foreach ($items as $item) {
            $userId = (int) $item['user_id'];
            if (! array_key_exists($userId, $allowed)) {
                continue; // 対象外（サンエー印刷・SuperAdmin など。SuperAdmin は常に使えるので変更しない）は受け付けない
            }
            $enabled = (bool) $item['enabled'];
            // いまの状態（行があればその値。なければ既定値＝Admin は ON・それ以外は OFF）と同じなら、変更なし
            $effective = isset($current[$userId]) ? (bool) $current[$userId] : ($allowed[$userId] === 'admin');
            if ($effective === $enabled) {
                continue; // 変更のない行は触らない（許可した人・日時を残す。無駄な行も作らない）
            }
            PortfolioPermission::updateOrCreate(
                ['user_id' => $userId],
                ['enabled' => $enabled, 'granted_by' => $request->user()->id, 'granted_at' => $enabled ? now() : null],
            );
            $changed++;
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /** スイッチを操作できるユーザー: SB 所属の、SuperAdmin 以外（Admin を含む。ゴースト除外は User のグローバルスコープ） */
    private function switchableUsers()
    {
        return User::whereNotIn('user_role', self::ALWAYS_ON_ROLES)
            ->whereHas('company', fn ($q) => $q->where('company_type', 'sunbrain'));
    }

    /**
     * 一覧に出すユーザー: スイッチを操作できる人（Admin は部署が未設定でも出す）に加えて、会社と部署が設定されている SuperAdmin。
     * （会社・部署が未設定の SuperAdmin の管理用アカウントは、メンバーとして意味がないので出さない）
     */
    private function listedUsers()
    {
        return User::whereHas('company', fn ($q) => $q->where('company_type', 'sunbrain'))
            ->where(function ($q) {
                $q->whereNotIn('user_role', self::ALWAYS_ON_ROLES)->orWhereNotNull('department_id');
            });
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }
}
