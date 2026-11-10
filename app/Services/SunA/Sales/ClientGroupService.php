<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\ClientGroup;
use App\Models\SunA\Sales\ClientGroupEvent;
use App\Models\SunA\Sales\ClientGroupMember;
use App\Models\SunA\Sales\Fact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 得意先の名寄せ設定（SUNASALES_PLAN1.md §17）。グループの作成・名前変更・メンバーの追加/解除・解散と、変更の記録。
 * 売上の行は変更しない。会社のコンテキスト（SunaCompanyContext）が必要。監査ログには件数だけを残す（得意先名は変更の記録のほうに残す）。
 */
class ClientGroupService
{
    public function __construct(private ClientGroupCandidates $candidates)
    {
    }

    /** @return array<string, mixed> 画面用: グループ（メンバーと累計売上つき）・統合の候補 */
    public function overview(): array
    {
        $groups = ClientGroup::query()->orderBy('name')->get();
        $members = ClientGroupMember::query()->orderBy('id')->get()->groupBy('group_id');
        $totals = Fact::query()->selectRaw('client_key, SUM(amount) as total, MAX(sales_year) as last_year')->groupBy('client_key')->get()->keyBy('client_key');

        return [
            'groups' => $groups->map(fn (ClientGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'members' => ($members[$g->id] ?? collect())->map(fn ($m) => [
                    'client_key' => $m->client_key,
                    'name' => $m->client_name,
                    'total_amount' => (float) ($totals[$m->client_key]->total ?? 0),
                    'last_year' => (int) ($totals[$m->client_key]->last_year ?? 0),
                ])->values()->all(),
            ])->values()->all(),
            'candidates' => $this->candidates->build(),
            // 候補を探した対象の数（候補が0件のとき、「見つからなかった」のか「調べていない」のかを画面で区別するため）
            'candidates_checked' => $this->candidates->countChecked(),
        ];
    }

    /**
     * 名寄せしていない得意先の検索（メンバーの追加・手動の統合で使う）。売上の多い順。
     *
     * @return array<int, array{client_key:string, name:string, total_amount:float, last_year:int}>
     */
    public function searchUngrouped(string $keyword, int $limit = 20): array
    {
        $grouped = ClientGroupMember::query()->pluck('client_key')->all();
        $q = Fact::query()->selectRaw('client_key, MAX(client_name) as name, SUM(amount) as total, MAX(sales_year) as last_year')
            ->when($grouped !== [], fn ($w) => $w->whereNotIn('client_key', $grouped))
            ->groupBy('client_key');
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $q->where('client_name', 'like', $like);
        }

        return $q->orderByDesc('total')->orderBy('client_key')->limit(max(1, min(50, $limit)))->get()
            ->map(fn ($r) => ['client_key' => (string) $r->client_key, 'name' => (string) $r->name, 'total_amount' => (float) $r->total, 'last_year' => (int) $r->last_year])
            ->all();
    }

    /** @return array<string, mixed> */
    public function events(int $page = 1, int $perPage = 100): array
    {
        $query = ClientGroupEvent::query()->orderByDesc('id');
        $total = (clone $query)->count();
        $rows = $query->forPage(max(1, $page), $perPage)->get();
        $names = User::whereIn('id', $rows->pluck('user_id')->unique()->all())->pluck('name', 'id');

        return [
            'total' => $total, 'page' => max(1, $page), 'per_page' => $perPage,
            'events' => $rows->map(fn ($e) => [
                'id' => $e->id, 'action' => $e->action, 'group_name' => $e->group_name, 'client_name' => $e->client_name,
                'user_name' => $names[$e->user_id] ?? '（不明）', 'created_at' => $e->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @param  array<int, string>  $clientKeys */
    public function create(string $name, array $clientKeys, int $userId): ClientGroup
    {
        $name = $this->cleanName($name);
        $clientKeys = array_values(array_unique($clientKeys));
        if (count($clientKeys) < 2) {
            throw new ClientGroupException('統合するには、2社以上の得意先を選んでください。');
        }

        return DB::connection('sales')->transaction(function () use ($name, $clientKeys, $userId) {
            $names = $this->resolveUngrouped($clientKeys);
            $group = ClientGroup::create(['name' => $name]);
            foreach ($clientKeys as $key) {
                ClientGroupMember::create(['group_id' => $group->id, 'client_key' => $key, 'client_name' => $names[$key]]);
                $this->event($userId, 'create', $group, $key, $names[$key]);
            }
            $this->audit($userId, 'client_group_create', $group->id, ['members' => count($clientKeys)]);

            return $group;
        });
    }

    public function rename(int $groupId, string $name, int $userId): ClientGroup
    {
        $name = $this->cleanName($name);

        return DB::connection('sales')->transaction(function () use ($groupId, $name, $userId) {
            $group = $this->findGroup($groupId);
            if ($group->name !== $name) {
                $this->event($userId, 'rename', $group, null, null, $group->name . ' → ' . $name);
                $group->update(['name' => $name]);
                $this->audit($userId, 'client_group_rename', $group->id, []);
            }

            return $group;
        });
    }

    /** @param  array<int, string>  $clientKeys */
    public function addMembers(int $groupId, array $clientKeys, int $userId): ClientGroup
    {
        $clientKeys = array_values(array_unique($clientKeys));
        if ($clientKeys === []) {
            throw new ClientGroupException('追加する得意先を選んでください。');
        }

        return DB::connection('sales')->transaction(function () use ($groupId, $clientKeys, $userId) {
            $group = $this->findGroup($groupId);
            $names = $this->resolveUngrouped($clientKeys);
            foreach ($clientKeys as $key) {
                ClientGroupMember::create(['group_id' => $group->id, 'client_key' => $key, 'client_name' => $names[$key]]);
                $this->event($userId, 'add', $group, $key, $names[$key]);
            }
            $this->audit($userId, 'client_group_add', $group->id, ['members' => count($clientKeys)]);

            return $group;
        });
    }

    /** メンバーを外す。外した結果が1社以下になるグループは、自動で解散する。 @return array{dissolved: bool} */
    public function removeMember(int $groupId, string $clientKey, int $userId): array
    {
        return DB::connection('sales')->transaction(function () use ($groupId, $clientKey, $userId) {
            $group = $this->findGroup($groupId);
            $member = ClientGroupMember::query()->where('group_id', $group->id)->where('client_key', $clientKey)->first();
            if ($member === null) {
                throw new ClientGroupException('このグループに、その得意先は含まれていません。');
            }
            $member->delete();
            $this->event($userId, 'remove', $group, $member->client_key, $member->client_name);
            $this->audit($userId, 'client_group_remove', $group->id, []);

            if (ClientGroupMember::query()->where('group_id', $group->id)->count() < 2) {
                $this->dissolveGroup($group, $userId, '（メンバーが1社以下になったため自動で解散）');

                return ['dissolved' => true];
            }

            return ['dissolved' => false];
        });
    }

    public function dissolve(int $groupId, int $userId): void
    {
        DB::connection('sales')->transaction(function () use ($groupId, $userId) {
            $this->dissolveGroup($this->findGroup($groupId), $userId, '');
        });
    }

    private function dissolveGroup(ClientGroup $group, int $userId, string $note): void
    {
        $count = ClientGroupMember::query()->where('group_id', $group->id)->count();
        ClientGroupMember::query()->where('group_id', $group->id)->delete();
        $this->event($userId, 'dissolve', $group, null, $note !== '' ? $note : null);
        $group->delete();
        $this->audit($userId, 'client_group_dissolve', $group->id, ['members' => $count]);
    }

    private function findGroup(int $id): ClientGroup
    {
        $group = ClientGroup::query()->whereKey($id)->lockForUpdate()->first();
        if ($group === null) {
            throw new ClientGroupException('グループが見つかりません。');
        }

        return $group;
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new ClientGroupException('グループ名を1〜255文字で入力してください。');
        }

        return $name;
    }

    /**
     * 売上の行に実在し、まだどのグループにも属していない得意先であることを確かめて、表示名を返す。
     *
     * @param  array<int, string>  $clientKeys
     * @return array<string, string> client_key => 表示名
     */
    private function resolveUngrouped(array $clientKeys): array
    {
        $names = Fact::query()->whereIn('client_key', $clientKeys)->selectRaw('client_key, MAX(client_name) as name')->groupBy('client_key')->pluck('name', 'client_key')->all();
        foreach ($clientKeys as $key) {
            if (! isset($names[$key])) {
                throw new ClientGroupException('売上データに無い得意先が含まれています。');
            }
        }
        if (ClientGroupMember::query()->whereIn('client_key', $clientKeys)->exists()) {
            throw new ClientGroupException('すでに別のグループに入っている得意先が含まれています。先にそのグループから外してください。');
        }

        return array_map('strval', $names);
    }

    private function event(int $userId, string $action, ClientGroup $group, ?string $clientKey = null, ?string $clientName = null, ?string $groupNameOverride = null): void
    {
        ClientGroupEvent::create([
            'user_id' => $userId, 'action' => $action, 'group_id' => $group->id,
            'group_name' => $groupNameOverride ?? $group->name, 'client_key' => $clientKey, 'client_name' => $clientName,
        ]);
    }

    /** @param  array<string, mixed>  $context 件数だけ（得意先名・金額は入れない） */
    private function audit(int $userId, string $action, int $groupId, array $context): void
    {
        AuditLog::create(['user_id' => $userId, 'action' => $action, 'target_type' => 'suna_sl_client_groups', 'target_id' => $groupId, 'context' => $context]);
    }
}
