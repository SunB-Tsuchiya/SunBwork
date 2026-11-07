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
 * 一覧に出すのは、サン・ブレーン（company_type = 'sunbrain'）所属のユーザー。
 * SuperAdmin・Admin も、会社と部署が設定されていれば名前を出す（誰がいるか分かるように）。ただし、この 2 つのロールは
 * 許可に関わらず常に使える（Admin は閲覧専用）ため、スイッチは ON 固定で操作できない（always_on）。保存の対象にもしない。
 */
class PortfolioPermissionController extends Controller
{
    /** 許可に関わらず常にポートフォリオを使えるロール */
    private const ALWAYS_ON_ROLES = ['superadmin', 'admin'];

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
                    // SuperAdmin・Admin は常に使える（スイッチは固定表示）。それ以外は保存された許可
                    'always_on' => $alwaysOn,
                    'role_label' => $alwaysOn ? ($u->user_role === 'superadmin' ? 'SuperAdmin' : 'Admin') : null,
                    'enabled' => $alwaysOn ? true : (bool) ($enabledMap[$u->id] ?? false),
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

        $allowed = array_flip($this->switchableUsers()->pluck('id')->map(fn ($v) => (int) $v)->all());
        $current = PortfolioPermission::pluck('enabled', 'user_id');
        $changed = 0;

        foreach ($items as $item) {
            $userId = (int) $item['user_id'];
            if (! isset($allowed[$userId])) {
                continue; // 対象外（サンエー印刷・Admin・SuperAdmin など。後者 2 つは常に使えるので変更しない）は受け付けない
            }
            $enabled = (bool) $item['enabled'];
            if (isset($current[$userId]) && (bool) $current[$userId] === $enabled) {
                continue; // 変更のない行
            }
            if (! isset($current[$userId]) && ! $enabled) {
                continue; // 行が無く OFF のまま = 変更なし（無駄な行を作らない）
            }
            PortfolioPermission::updateOrCreate(
                ['user_id' => $userId],
                ['enabled' => $enabled, 'granted_by' => $request->user()->id, 'granted_at' => $enabled ? now() : null],
            );
            $changed++;
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /** スイッチを操作できるユーザー: SB 所属の、SuperAdmin・Admin 以外（ゴースト除外は User のグローバルスコープ） */
    private function switchableUsers()
    {
        return User::whereNotIn('user_role', self::ALWAYS_ON_ROLES)
            ->whereHas('company', fn ($q) => $q->where('company_type', 'sunbrain'));
    }

    /**
     * 一覧に出すユーザー: スイッチを操作できる人に加えて、会社と部署が設定されている SuperAdmin・Admin。
     * （会社・部署が未設定の管理用アカウントは、メンバーとして意味がないので出さない）
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
