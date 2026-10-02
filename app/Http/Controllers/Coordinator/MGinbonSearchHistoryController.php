<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\MGinbon\MGinbonSearchHistory;
use App\Services\MGinbon\MGinbonSearchCriteria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MGinbonSearchHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        if (! Schema::connection('mginbon')->hasTable('mginbon_search_histories')) return response()->json(['histories' => []]);
        $rows = MGinbonSearchHistory::query()->where('user_id', $request->user()->id)->where('mginbon_project_id', $data['project_id'])
            ->latest('executed_at')->limit(20)->get()->map(fn ($row) => [
                'id' => $row->id, 'criteria' => $row->criteria, 'summary' => $row->summary, 'result_count' => $row->result_count,
                'display_mode' => $row->display_mode, 'executed_at' => $row->executed_at?->format('Y-m-d H:i'),
            ]);

        return response()->json(['histories' => $rows]);
    }

    public function store(Request $request, MGinbonSearchCriteria $criteria): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'], 'criteria' => ['required', 'array'],
            'result_count' => ['required', 'integer', 'min:0'], 'display_mode' => ['required', Rule::in(['single', 'list', 'table'])],
        ]);
        if (! MGinbonProject::query()->whereKey($data['project_id'])->exists()) throw ValidationException::withMessages(['project_id' => '指定年度が見つかりません。']);
        $normalized = $criteria->normalize($data['criteria']);
        if ($normalized['requests'] === []) throw ValidationException::withMessages(['criteria' => '検索条件がありません。']);
        $hash = $criteria->hash($normalized);
        $latest = MGinbonSearchHistory::query()->where('user_id', $request->user()->id)->where('mginbon_project_id', $data['project_id'])->latest('executed_at')->first();
        $values = ['criteria_version' => 2, 'criteria' => $normalized, 'criteria_hash' => $hash, 'summary' => $criteria->summary($normalized), 'result_count' => $data['result_count'], 'display_mode' => $data['display_mode'], 'executed_at' => now()];
        if ($latest?->criteria_hash === $hash) $latest->update($values);
        else $latest = MGinbonSearchHistory::create(['mginbon_project_id' => $data['project_id'], 'user_id' => $request->user()->id, ...$values]);
        $keepIds = MGinbonSearchHistory::query()->where('user_id', $request->user()->id)->where('mginbon_project_id', $data['project_id'])->latest('executed_at')->limit(20)->pluck('id');
        MGinbonSearchHistory::query()->where('user_id', $request->user()->id)->where('mginbon_project_id', $data['project_id'])->whereNotIn('id', $keepIds)->delete();

        return response()->json(['history_id' => $latest->id], 201);
    }

    public function clear(Request $request): JsonResponse
    {
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        MGinbonSearchHistory::query()->where('user_id', $request->user()->id)->where('mginbon_project_id', $data['project_id'])->delete();

        return response()->json([], 204);
    }
}
