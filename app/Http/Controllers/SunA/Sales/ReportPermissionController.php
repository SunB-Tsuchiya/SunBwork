<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\Portfolio\MemberList;
use App\Support\SunA\Sales\ReportAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * サンエー印刷 売上レポートの「利用メンバー設定」（Admin エリア。SuperAdmin も、サンエー印刷に切り替えて開ける）。
 * 画面と保存の形は、ポートフォリオの利用メンバー設定（Portfolio\PortfolioPermissionController）・申請者管理と同じ標準形:
 * ユーザーごとのスイッチを動かして、まとめて「保存する」。並び順も同じ（MemberList）。部署・区分・担当・役職のチップで選別できる。
 *
 * 対象: いまの会社（サンエー印刷）に所属する全ロールのユーザー。**既定は全員 OFF**（Admin も OFF。自分に許可を付けられる）。
 * 閲覧（enabled）と取込（can_import）の2つのスイッチ。取込は閲覧が前提（閲覧 OFF の人の取込は常に OFF）。
 * SuperAdmin は、開発・確認のため、許可に関わらず常に使える（この画面の対象外）。
 * ルートは suna_sales_report:manage で保護（Admin/SuperAdmin、かつサンエー印刷のコンテキスト）。
 */
class ReportPermissionController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) ReportAccess::companyIdFor($request->user());

        $users = MemberList::sort(
            $this->companyUsers($companyId)->with(['department:id,name', 'positionTitle:id,name,sort_order', 'assignment:id,name'])->get()
        );
        $permissions = ReportPermission::whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id');

        return Inertia::render('SunA/Sales/Permissions', [
            'routePrefix' => Str::before($request->route()->getName(), '.suna.sales.'),
            'users' => $users->map(function (User $u) use ($permissions) {
                $p = $permissions->get($u->id);

                return MemberList::row($u) + [
                    'assignment_name' => $u->assignment?->name,
                    'role_label' => $u->user_role === 'admin' ? 'Admin' : null,
                    // 行がなければ、閲覧も取込も OFF（既定は全員 OFF）
                    'enabled' => (bool) ($p->enabled ?? false),
                    'can_import' => (bool) (($p->enabled ?? false) && ($p->can_import ?? false)),
                ];
            })->values(),
            'departments' => Department::whereIn('id', $users->pluck('department_id')->filter()->unique())
                ->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            // 担当（営業・制作など）・役職（部長・次長・課長など。部長クラスを選びやすくするためのチップ）
            'assignmentNames' => $users->map(fn (User $u) => $u->assignment?->name)->filter()->unique()->sort()->values(),
            'positionNames' => $users->sortBy(fn (User $u) => $u->positionTitle?->sort_order ?? 9999)
                ->map(fn (User $u) => $u->positionTitle?->name)->filter()->unique()->values(),
        ]);
    }

    /**
     * スイッチを、まとめて保存する。リクエスト: items = [{ user_id, enabled, can_import }, ...]（対象外のユーザーは受け付けない）。
     * 変更のない行は触らない（許可した人・日時を残すため）。取込は、閲覧が ON のときだけ ON にできる。
     */
    public function update(Request $request)
    {
        $companyId = (int) ReportAccess::companyIdFor($request->user());

        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.user_id' => ['required', 'integer'],
            'items.*.enabled' => ['required', 'boolean'],
            'items.*.can_import' => ['required', 'boolean'],
        ])['items'];

        $allowed = $this->companyUsers($companyId)->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $current = ReportPermission::whereIn('user_id', $allowed->keys())->get()->keyBy('user_id');
        $changed = 0;

        foreach ($items as $item) {
            $userId = (int) $item['user_id'];
            if (! $allowed->has($userId)) {
                continue; // 他社・対象外のユーザーは受け付けない
            }
            $enabled = (bool) $item['enabled'];
            $canImport = $enabled && (bool) $item['can_import']; // 取込は閲覧が前提

            $now = $current->get($userId);
            if ((bool) ($now->enabled ?? false) === $enabled && (bool) ($now->can_import ?? false) === $canImport) {
                continue; // 変更なし（行も作らない）
            }
            ReportPermission::updateOrCreate(
                ['user_id' => $userId],
                ['enabled' => $enabled, 'can_import' => $canImport, 'granted_by' => $request->user()->id, 'granted_at' => ($enabled || $canImport) ? now() : null],
            );
            $changed++;
        }

        if ($changed > 0) {
            // 監査ログには、誰の許可を変えたかの件数だけを残す（個人名・内容は入れない）
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'permission_update',
                'target_type' => 'report_permissions',
                'context' => ['changed' => $changed],
            ]);
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /** 対象のユーザー: いまの会社に所属する全ロール（ゴースト除外は User のグローバルスコープ） */
    private function companyUsers(int $companyId)
    {
        return User::where('company_id', $companyId);
    }
}
