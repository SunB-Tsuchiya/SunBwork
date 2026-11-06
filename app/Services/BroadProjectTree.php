<?php

namespace App\Services;

use App\Models\BroadProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BroadProjectTree
{
    public function canManage(?User $user): bool
    {
        return $user && in_array($user->user_role, ['superadmin', 'coordinator', 'leader'], true)
            && $user->company && $user->company->company_type !== 'general'
            && DB::table('departments')->where('id', $user->department_id)
                ->where('company_id', $user->company_id)->where('code', 'INFO')->exists();
    }

    public function authorize(User $user): void
    {
        if (! $this->canManage($user)) throw new AuthorizationException;
    }

    public function scoped(User $user)
    {
        $this->authorize($user);
        return BroadProject::where('company_id', $user->company_id)->where('department_id', $user->department_id);
    }

    private function own(User $user, BroadProject $node): void
    {
        $this->authorize($user);
        if ((int) $node->company_id !== (int) $user->company_id
            || (int) $node->department_id !== (int) $user->department_id) throw new AuthorizationException;
    }

    public function rows(User $user): array
    {
        $nodes = $this->scoped($user)->with('parent')->inDisplayOrder()->get()
            ->sortBy(fn ($node) => $node->displayOrderKey())->values();
        $clients = new BroadProjectClients;
        $clients->load($nodes, (int) $user->company_id);
        return $nodes->map(function ($node) use ($nodes, $clients) {
            $direct = $clients->direct($node);
            $active = collect($direct); $all = collect($direct);
            if (! $node->parent_id) {
                foreach ($nodes->where('parent_id', $node->id) as $child) {
                    $all = $all->concat($clients->direct($child));
                    if ($child->activePath()) $active = $active->concat($clients->direct($child));
                }
            }
            return [
                'id' => $node->id, 'parent_id' => $node->parent_id, 'name' => $node->name,
                'client_id' => $node->client_id, 'client' => $node->client ? ['id' => $node->client->id, 'name' => $node->client->name] : null,
                'clients' => $direct,
                'display_clients' => $active->unique('id')->sortBy('name')->values()->all(),
                'all_display_clients' => $all->unique('id')->sortBy('name')->values()->all(),
                'fiscal_year' => $node->fiscal_year, 'is_active' => $node->is_active,
                'sort_order' => $node->sort_order,
                'path' => $node->pathLabel(), 'selectable' => $node->activePath(),
            ];
        })->all();
    }

    private function lockDepartment(User $user): void
    {
        DB::table('departments')->where('id', $user->department_id)->lockForUpdate()->first();
    }

    private function root(User $user, int $id): BroadProject
    {
        $parent = $this->scoped($user)->find($id);
        if (! $parent || $parent->parent_id !== null) {
            throw ValidationException::withMessages(['parent_id' => '同じ部署の大グループを選んでください。']);
        }
        return $parent;
    }

    private function checkDuplicate(User $user, array $data, ?int $except = null): void
    {
        if ($this->scoped($user)->where('parent_id', $data['parent_id'])->where('name', $data['name'])
            ->where('fiscal_year', $data['fiscal_year'])
            ->when($except, fn ($q) => $q->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['name' => '同じグループ内に同名・同年度の分類があります。休止中も確認してください。']);
        }
    }

    private function nextOrder(User $user, ?int $parent): int
    {
        return ((int) $this->scoped($user)->where('parent_id', $parent)->max('sort_order')) + 10;
    }

    public function save(User $user, array $input, ?BroadProject $existing = null): BroadProject
    {
        $this->authorize($user);
        if ($existing) $this->own($user, $existing);
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'min:1'], 'fiscal_year' => ['nullable', 'integer', 'between:1900,2100'],
            'is_active' => ['required', 'boolean'],
        ];
        if ($existing) {
            // Editing metadata can never silently move a group, even with a crafted request.
            if (array_key_exists('parent_id', $input) || array_key_exists('group_type', $input)) {
                throw ValidationException::withMessages(['parent_id' => '所属の変更は「グループ移動」から行ってください。']);
            }
        } else {
            $rules['group_type'] = ['required', 'in:large,middle'];
            $rules['parent_id'] = ['nullable', 'integer', 'min:1', 'required_if:group_type,middle', 'prohibited_if:group_type,large'];
        }
        $data = Validator::make($input, $rules)->validate();
        $data['name'] = trim(preg_replace('/[\s\x{3000}]+/u', ' ', $data['name']));
        $data['client_id'] = array_key_exists('client_id', $input) ? ($data['client_id'] ?? null) : $existing?->client_id;
        $data['fiscal_year'] = $data['fiscal_year'] ?? null;
        if ($data['name'] === '') throw ValidationException::withMessages(['name' => '名前を入力してください。']);

        return DB::transaction(function () use ($user, $data, $existing, $input) {
            $this->lockDepartment($user);
            $node = $existing ? $this->scoped($user)->findOrFail($existing->id) : new BroadProject;
            $parentId = $existing ? $node->parent_id : ($data['parent_id'] ?? null);
            if ($parentId !== null) $this->root($user, (int) $parentId);
            $attributes = array_intersect_key($data, array_flip(['name', 'fiscal_year', 'is_active']));
            if (! $existing || $node->name !== $attributes['name'] || (string) $node->fiscal_year !== (string) $attributes['fiscal_year']) {
                $this->checkDuplicate($user, $attributes + ['parent_id' => $parentId], $node->id);
            }
            $node->fill($attributes);
            if (! $existing) {
                $node->company_id = $user->company_id;
                $node->department_id = $user->department_id;
                $node->parent_id = $parentId;
                $node->sort_order = $this->nextOrder($user, $parentId);
            }
            $node->save();
            (new BroadProjectClients)->update($node, $input);
            return $node;
        });
    }

    public function move(User $user, BroadProject $existing, array $input): void
    {
        $this->own($user, $existing);
        $data = Validator::make($input, ['parent_id' => ['required', 'integer', 'min:1']])->validate();
        DB::transaction(function () use ($user, $existing, $data) {
            $this->lockDepartment($user);
            $node = $this->scoped($user)->findOrFail($existing->id);
            if ($node->parent_id === null || $node->children()->exists()) {
                throw ValidationException::withMessages(['parent_id' => '移動できるのは中グループだけです。']);
            }
            $parent = $this->root($user, (int) $data['parent_id']);
            if ((int) $node->parent_id === (int) $parent->id) return;
            $this->checkDuplicate($user, [
                'name' => $node->name, 'parent_id' => $parent->id,
                'client_id' => $node->client_id, 'fiscal_year' => $node->fiscal_year,
            ], $node->id);
            $node->parent_id = $parent->id;
            $node->sort_order = $this->nextOrder($user, $parent->id);
            $node->save();
        });
    }

    public function reorder(User $user, array $input): void
    {
        $this->authorize($user);
        $data = Validator::make($input, [
            'parent_id' => ['present', 'nullable', 'integer', 'min:1'],
            'current_ids' => ['required', 'array', 'list', 'min:1'], 'current_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'ordered_ids' => ['required', 'array', 'list', 'min:1'], 'ordered_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();
        DB::transaction(function () use ($user, $data) {
            $this->lockDepartment($user);
            $parentId = $data['parent_id'] ?? null;
            if ($parentId !== null) $this->root($user, (int) $parentId);
            $current = $this->scoped($user)->where('parent_id', $parentId)->inDisplayOrder()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $submitted = array_map('intval', $data['current_ids']);
            $ordered = array_map('intval', $data['ordered_ids']);
            $currentSet = $current; $orderedSet = $ordered; sort($currentSet); sort($orderedSet);
            if ($current !== $submitted || $currentSet !== $orderedSet) {
                throw ValidationException::withMessages(['ordered_ids' => '分類や並びが変更されています。一覧を再読み込みしてから並べ替えてください。']);
            }
            foreach ($ordered as $index => $id) {
                $this->scoped($user)->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
            }
        });
    }
}
