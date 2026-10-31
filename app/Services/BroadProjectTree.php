<?php

namespace App\Services;

use App\Models\BroadProject;
use App\Models\Client;
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

    public function rows(User $user): array
    {
        $nodes = $this->scoped($user)->with(['client:id,name', 'parent.parent'])->orderBy('name')->orderBy('id')->get();
        return $nodes->map(fn ($node) => [
            'id' => $node->id, 'parent_id' => $node->parent_id, 'name' => $node->name,
            'client_id' => $node->client_id, 'client' => $node->client,
            'fiscal_year' => $node->fiscal_year, 'is_active' => $node->is_active,
            'path' => $node->pathLabel(), 'selectable' => $node->activePath(),
        ])->all();
    }

    public function save(User $user, array $input, ?BroadProject $existing = null): BroadProject
    {
        $this->authorize($user);
        if ($existing && ((int) $existing->company_id !== (int) $user->company_id
            || (int) $existing->department_id !== (int) $user->department_id)) throw new AuthorizationException;
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'], 'parent_id' => ['nullable', 'integer', 'min:1'],
            'client_id' => ['nullable', 'integer', 'min:1'], 'fiscal_year' => ['nullable', 'integer', 'between:1900,2100'],
            'is_active' => ['required', 'boolean'],
        ])->validate();
        $data['name'] = trim(preg_replace('/[\s\x{3000}]+/u', ' ', $data['name']));
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['client_id'] = $data['client_id'] ?? null;
        $data['fiscal_year'] = $data['fiscal_year'] ?? null;
        if ($data['name'] === '') throw ValidationException::withMessages(['name' => '名前を入力してください。']);
        if ($data['client_id'] && ! Client::forCompany($user->company_id)->whereKey($data['client_id'])
            ->where(fn ($q) => $q->where('is_dormant', false)->when($existing?->client_id, fn ($q) => $q->orWhere('id', $existing->client_id)))->exists()) {
            throw ValidationException::withMessages(['client_id' => 'この取引先は選択できません。']);
        }

        return DB::transaction(function () use ($user, $data, $existing) {
            // Serialize tree edits and the approved import within the department.
            DB::table('departments')->where('id', $user->department_id)->lockForUpdate()->first();
            $nodes = $this->scoped($user)->get()->keyBy('id');
            $id = $existing?->id ?? 0;
            $parents = $nodes->mapWithKeys(fn ($n) => [$n->id => $n->parent_id])->all();
            $parents[$id] = $data['parent_id'];
            foreach (array_keys($parents) as $start) {
                $seen = []; $cursor = $start;
                while ($cursor !== null) {
                    if (isset($seen[$cursor]) || ! array_key_exists($cursor, $parents)) {
                        throw ValidationException::withMessages(['parent_id' => '同じ部署のグループを選び、循環しないようにしてください。']);
                    }
                    $seen[$cursor] = true;
                    if (count($seen) > 3) throw ValidationException::withMessages(['parent_id' => 'グループは3段までにしてください。']);
                    $cursor = $parents[$cursor];
                }
            }
            $duplicate = $this->scoped($user)->where('parent_id', $data['parent_id'])->where('name', $data['name'])
                ->where('client_id', $data['client_id'])->where('fiscal_year', $data['fiscal_year'])
                ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists();
            if ($duplicate) throw ValidationException::withMessages(['name' => '同じ親・名前・取引先・年度の案件が登録されています。休止中も確認してください。']);
            $node = $existing ? $this->scoped($user)->findOrFail($existing->id) : new BroadProject;
            $node->fill($data);
            $node->company_id = $user->company_id;
            $node->department_id = $user->department_id;
            $node->save();
            return $node;
        });
    }
}
