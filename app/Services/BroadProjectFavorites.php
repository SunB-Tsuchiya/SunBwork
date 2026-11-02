<?php

namespace App\Services;

use App\Models\BroadProject;
use App\Models\BroadProjectFavorite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BroadProjectFavorites
{
    public function __construct(private BroadProjectTree $tree) {}

    public function ids(User $user): array
    {
        $this->tree->authorize($user);
        return BroadProjectFavorite::where('user_id', $user->id)
            ->whereHas('broadProject', fn ($q) => $q->where('company_id', $user->company_id)->where('department_id', $user->department_id))
            ->pluck('broad_project_id')->map(fn ($id) => (int) $id)->all();
    }

    public function decorate(User $user, array $rows): array
    {
        $ids = array_fill_keys($this->ids($user), true);
        return array_map(fn ($row) => $row + ['is_favorite' => isset($ids[$row['id']])], $rows);
    }

    public function set(User $user, BroadProject $project, bool $enabled): bool
    {
        $this->tree->authorize($user);
        if ((int) $project->company_id !== (int) $user->company_id
            || (int) $project->department_id !== (int) $user->department_id) throw new AuthorizationException;
        return DB::transaction(function () use ($user, $project, $enabled) {
            // Use the same department lock as pause/move edits so additions check the latest tree.
            DB::table('departments')->where('id', $user->department_id)->lockForUpdate()->first();
            $node = $this->tree->scoped($user)->with('parent')->findOrFail($project->id);
            $favorite = BroadProjectFavorite::where('user_id', $user->id)->where('broad_project_id', $node->id);
            if ($enabled) {
                if (! $favorite->exists() && ! $node->activePath()) {
                    throw ValidationException::withMessages(['is_favorite' => '休止中の分類は新しくお気に入りに追加できません。']);
                }
                BroadProjectFavorite::firstOrCreate(['user_id' => $user->id, 'broad_project_id' => $node->id]);
            } else {
                $favorite->delete();
            }
            return $enabled;
        });
    }

    public function candidates(User $user, string $term = '', int $selectedId = 0, ?int $limit = 30): array
    {
        $ids = array_fill_keys($this->ids($user), true);
        $normalize = fn ($value) => mb_strtolower(mb_convert_kana($value, 'asKV', 'UTF-8'));
        $words = preg_split('/\s+/u', trim($normalize($term)), -1, PREG_SPLIT_NO_EMPTY);
        $rows = $this->tree->scoped($user)->with(['client:id,name', 'parent'])->inDisplayOrder()->get()
            ->sortBy(fn ($node) => $node->displayOrderKey())
            ->filter(function ($node) use ($selectedId, $words, $normalize) {
                if ((int) $node->id === $selectedId) return true;
                if (! $node->activePath()) return false;
                $label = $normalize($node->pathLabel().' '.($node->client?->name ?? ''));
                foreach ($words as $word) if (! str_contains($label, $word)) return false;
                return true;
            })->map(fn ($node) => [
                'id' => $node->id, 'parent_id' => $node->parent_id, 'name' => $node->name, 'path' => $node->pathLabel(),
                'client_name' => $node->client?->name, 'fiscal_year' => $node->fiscal_year,
                'is_active' => $node->activePath(), 'is_favorite' => isset($ids[$node->id]),
            ])->values();
        if ($limit === null) return $rows->all();
        // All matching favorites survive the normal 30-row limit; the current selection survives too.
        return $rows->where('id', $selectedId)->concat($rows->where('is_favorite', true))
            ->concat($rows->where('is_favorite', false)->take($limit))->unique('id')->values()->all();
    }
}
