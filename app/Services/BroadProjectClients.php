<?php

namespace App\Services;

use App\Models\BroadProject;
use App\Models\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Related clients are search hints, never a work item's actual client. */
class BroadProjectClients
{
    public function load($nodes, int $companyId): void
    {
        $scope = fn ($q) => $q->forCompany($companyId);
        (new \Illuminate\Database\Eloquent\Collection($nodes->all()))->load(['clients' => $scope, 'client' => $scope]);
    }

    public function direct(BroadProject $node): array
    {
        return $node->clients->concat($node->client ? [$node->client] : [])
            ->unique('id')->sortBy('name')->values()
            ->map(fn ($client) => ['id' => (int) $client->id, 'name' => $client->name])->all();
    }

    /** Called only under the department lock, using the freshly reloaded node. */
    public function update(BroadProject $node, array $input): void
    {
        $hasMany = array_key_exists('client_ids', $input);
        $hasOne = array_key_exists('client_id', $input);
        if (! $hasMany && ! $hasOne) return;
        $data = Validator::make($input, [
            'client_ids' => ['sometimes', 'array', 'list', 'max:200'],
            'client_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'client_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();
        $this->load(collect([$node]), (int) $node->company_id);
        $current = array_column($this->direct($node), 'id');
        $legacy = isset($data['client_id']) ? (int) $data['client_id'] : null;
        if ($hasMany) {
            $ids = array_map('intval', $data['client_ids']);
            if ($hasOne && ($legacy !== null ? ! in_array($legacy, $ids, true) : count($ids) > 0)) {
                throw ValidationException::withMessages(['client_ids' => '取引先の単一指定と複数指定が一致しません。']);
            }
        } else {
            // An old form may replace its single value, but cannot erase other associations.
            $ids = array_values(array_diff($current, array_filter([(int) $node->client_id])));
            if ($legacy !== null) $ids[] = $legacy;
            $ids = array_values(array_unique($ids));
        }
        $allowed = Client::forCompany($node->company_id)->whereIn('id', $ids)
            ->where(fn ($q) => $q->where('is_dormant', false)->orWhereIn('id', $current))->pluck('id')->map('intval')->all();
        if (array_diff($ids, $allowed)) {
            throw ValidationException::withMessages(['client_ids' => '同じ会社の有効な取引先を選択してください。既存の休眠取引先は保持・解除できます。']);
        }
        $node->clients()->sync($ids);
        // Preserve an explicit existing legacy value; never infer a primary from several clients.
        $node->client_id = $hasOne ? $legacy : (in_array((int) $node->client_id, $ids, true) ? $node->client_id : null);
        $node->save();
        $node->unsetRelation('clients'); $node->unsetRelation('client');
    }
}
