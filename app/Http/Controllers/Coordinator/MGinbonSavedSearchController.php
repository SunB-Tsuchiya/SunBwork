<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\MGinbon\MGinbonSavedSearch;
use App\Services\MGinbon\MGinbonSearchCriteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MGinbonSavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer']]);
        if (! Schema::connection('mginbon')->hasTable('mginbon_saved_searches')) {
            return response()->json(['saved_searches' => []]);
        }
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : null;
        $rows = MGinbonSavedSearch::query()
            ->where(fn ($query) => $query->where('owner_user_id', $request->user()->id)->orWhere('scope', 'shared'))
            ->where(fn ($query) => $query->whereNull('mginbon_project_id')->when($projectId, fn ($inner) => $inner->orWhere('mginbon_project_id', $projectId)))
            ->latest('updated_at')->get()->map(fn ($row) => $this->resource($request, $row));

        return response()->json(['saved_searches' => $rows]);
    }

    public function store(Request $request, MGinbonSearchCriteria $criteria): JsonResponse
    {
        $data = $this->validated($request);
        $this->ensureProjectExists($data['project_id'] ?? null);
        $this->ensureCanShare($request, $data['scope']);
        $this->ensureUniqueName($request->user()->id, $data['project_id'] ?? null, $data['scope'], $data['name']);
        $normalized = $criteria->normalize($data['criteria']);
        $saved = MGinbonSavedSearch::create([
            'mginbon_project_id' => $data['project_id'] ?? null, 'owner_user_id' => $request->user()->id,
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'scope' => $data['scope'],
            'criteria_version' => 2, 'criteria' => $normalized, 'display_mode' => $data['display_mode'], 'sort_key' => $data['sort_key'] ?? null,
        ]);

        return response()->json(['saved_search' => $this->resource($request, $saved)], 201);
    }

    public function update(Request $request, MGinbonSavedSearch $savedSearch, MGinbonSearchCriteria $criteria): JsonResponse
    {
        $this->authorizeChange($request, $savedSearch);
        $data = $this->validated($request);
        $this->ensureProjectExists($data['project_id'] ?? null);
        $this->ensureCanShare($request, $data['scope']);
        $this->ensureUniqueName($savedSearch->owner_user_id, $data['project_id'] ?? null, $data['scope'], $data['name'], $savedSearch->id);
        $savedSearch->update([
            'mginbon_project_id' => $data['project_id'] ?? null, 'name' => $data['name'],
            'description' => $data['description'] ?? null, 'scope' => $data['scope'],
            'criteria_version' => 2, 'criteria' => $criteria->normalize($data['criteria']),
            'display_mode' => $data['display_mode'], 'sort_key' => $data['sort_key'] ?? null,
        ]);

        return response()->json(['saved_search' => $this->resource($request, $savedSearch->fresh())]);
    }

    public function destroy(Request $request, MGinbonSavedSearch $savedSearch): JsonResponse
    {
        $this->authorizeChange($request, $savedSearch);
        $savedSearch->delete();

        return response()->json([], 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'], 'scope' => ['required', Rule::in(['personal', 'shared'])],
            'criteria' => ['required', 'array'], 'display_mode' => ['required', Rule::in(['single', 'list', 'table'])],
            'sort_key' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function ensureProjectExists(?int $projectId): void
    {
        if ($projectId !== null && ! MGinbonProject::query()->whereKey($projectId)->exists()) throw ValidationException::withMessages(['project_id' => '指定年度が見つかりません。']);
    }

    private function ensureCanShare(Request $request, string $scope): void
    {
        if ($scope === 'shared') abort_unless(in_array($request->user()->user_role, ['coordinator', 'admin', 'superadmin'], true), 403);
    }

    private function authorizeChange(Request $request, MGinbonSavedSearch $savedSearch): void
    {
        $isOwner = $savedSearch->owner_user_id === $request->user()->id;
        $isSharedManager = $savedSearch->scope === 'shared' && in_array($request->user()->user_role, ['coordinator', 'admin', 'superadmin'], true);
        abort_unless($isOwner || $isSharedManager, 403);
    }

    private function ensureUniqueName(int $ownerId, ?int $projectId, string $scope, string $name, ?int $ignoreId = null): void
    {
        $query = MGinbonSavedSearch::query()->where('owner_user_id', $ownerId)->where('scope', $scope)->where('name', $name)
            ->when($projectId === null, fn ($q) => $q->whereNull('mginbon_project_id'), fn ($q) => $q->where('mginbon_project_id', $projectId))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));
        if ($query->exists()) throw ValidationException::withMessages(['name' => '同じ範囲に同名の保存検索があります。']);
    }

    private function resource(Request $request, MGinbonSavedSearch $row): array
    {
        return [
            'id' => $row->id, 'project_id' => $row->mginbon_project_id, 'name' => $row->name, 'description' => $row->description,
            'scope' => $row->scope, 'criteria' => $row->criteria, 'display_mode' => $row->display_mode, 'updated_at' => $row->updated_at?->format('Y-m-d H:i'),
            'can_edit' => $row->owner_user_id === $request->user()->id || ($row->scope === 'shared' && in_array($request->user()->user_role, ['coordinator', 'admin', 'superadmin'], true)),
        ];
    }
}
