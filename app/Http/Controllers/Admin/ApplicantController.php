<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TransportExpense;
use App\Models\User;
use App\Models\UserApplicationAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 申請者管理（Admin / SuperAdmin）。申請の種類（いまは交通費）ごとに、誰が使えるかを、ユーザーごとのスイッチで決める。
 * 構造は在席ボード管理（PresenceBoardSettingsController）と同じ。会社の範囲: Admin は自社、SuperAdmin は切り替え中の会社。
 */
class ApplicantController extends Controller
{
    use ResolvesContextCompany;

    private const EMPLOYMENT_PRIORITY = ['regular' => 1, 'contract' => 2, 'dispatch' => 3, 'outsource' => 4];

    /** 申請の種類のカード */
    public function index(Request $request)
    {
        $companyId = $this->companyId($request);
        $ids = $companyId ? $this->userIds($companyId) : [];

        $features = collect(UserApplicationAccess::FEATURES)->map(fn ($f, $key) => [
            'key'           => $key,
            'name'          => $f['name'],
            'description'   => $f['description'],
            'enabled_count' => $ids ? UserApplicationAccess::where('feature_key', $key)->where('enabled', true)->whereIn('user_id', $ids)->count() : 0,
            'total_count'   => count($ids),
        ])->values();

        return Inertia::render('Admin/Applicants/Index', [
            'features'          => $features,
            'noCompanySelected' => $companyId === null,
        ]);
    }

    /** ある申請の、ユーザーごとのスイッチ */
    public function show(Request $request, string $feature)
    {
        abort_unless(isset(UserApplicationAccess::FEATURES[$feature]), 404);
        $companyId = $this->companyId($request);

        $base = [
            'feature' => ['key' => $feature, 'name' => UserApplicationAccess::FEATURES[$feature]['name']],
        ];
        if ($companyId === null) {
            return Inertia::render('Admin/Applicants/Show', $base + ['users' => [], 'departments' => [], 'noCompanySelected' => true]);
        }

        $ids = $this->userIds($companyId);
        $enabled = UserApplicationAccess::where('feature_key', $feature)->where('enabled', true)->whereIn('user_id', $ids)->pluck('user_id')->flip();

        // 交通費: 下書き・申請済み・差戻し中の申請がある人（OFF にする前に、気付けるようにする）
        $progress = [];
        if ($feature === 'transport') {
            TransportExpense::whereIn('user_id', $ids)
                ->whereIn('status', [TransportExpense::STATUS_DRAFT, TransportExpense::STATUS_SUBMITTED, TransportExpense::STATUS_RETURNED])
                ->selectRaw('user_id, status, COUNT(*) as c')->groupBy('user_id', 'status')->get()
                ->each(function ($r) use (&$progress) { $progress[$r->user_id][$r->status] = (int) $r->c; });
        }

        $users = User::with(['department', 'positionTitle'])->whereIn('id', $ids)->get()
            ->sortBy([
                fn ($a, $b) => (self::EMPLOYMENT_PRIORITY[$a->employment_type ?? 'regular'] ?? 99) <=> (self::EMPLOYMENT_PRIORITY[$b->employment_type ?? 'regular'] ?? 99),
                fn ($a, $b) => ($a->positionTitle?->sort_order ?? 9999) <=> ($b->positionTitle?->sort_order ?? 9999),
                fn ($a, $b) => $a->name <=> $b->name,
            ])->values()
            ->map(fn (User $u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'department'      => $u->department?->name ?? '未所属',
                'department_id'   => $u->department_id,
                'position_title'  => $u->positionTitle?->name,
                'employment_type' => $u->employment_type ?? 'regular',
                'enabled'         => isset($enabled[$u->id]),
                'progress'        => $progress[$u->id] ?? [],
            ]);

        return Inertia::render('Admin/Applicants/Show', $base + [
            'users'             => $users,
            'departments'       => Department::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'noCompanySelected' => false,
        ]);
    }

    /** スイッチを、まとめて保存する。リクエスト: items = [{ user_id, enabled }, ...]（自社の人だけ受け付ける） */
    public function update(Request $request, string $feature)
    {
        abort_unless(isset(UserApplicationAccess::FEATURES[$feature]), 404);
        $companyId = $this->companyId($request);
        abort_if($companyId === null, 422, '会社が選択されていません。');

        $items = $request->validate([
            'items'             => ['required', 'array'],
            'items.*.user_id'   => ['required', 'integer'],
            'items.*.enabled'   => ['required', 'boolean'],
        ])['items'];

        $allowed = array_flip($this->userIds($companyId));
        $actor = $request->user();
        $changed = 0;
        foreach ($items as $item) {
            if (!isset($allowed[(int) $item['user_id']])) {
                continue;   // 自社の人以外は、受け付けない
            }
            $row = UserApplicationAccess::firstOrNew(['user_id' => (int) $item['user_id'], 'feature_key' => $feature]);
            if ($row->exists && $row->enabled === (bool) $item['enabled']) {
                continue;   // 変更のない行は、触らない（変えた人・日時を、残すため）
            }
            $row->enabled = (bool) $item['enabled'];
            $row->changed_by = $actor->id;
            $row->save();
            $changed++;
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /** 対象の会社（SuperAdmin は切り替え中の会社。未選択なら null） */
    private function companyId(Request $request): ?int
    {
        $user = $request->user();
        $id = $user->isSuperAdmin() ? $this->contextCompanyId() : ($this->contextCompanyId() ?? $user->company_id);
        return $id ? (int) $id : null;
    }

    /** 対象の会社の、操作できるユーザー（ゴーストは除く） */
    private function userIds(int $companyId): array
    {
        return User::where('company_id', $companyId)->where('is_ghost', false)->whereNull('ghost_owner_id')
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }
}
