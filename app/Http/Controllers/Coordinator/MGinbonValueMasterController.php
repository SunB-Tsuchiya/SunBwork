<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Models\MGinbon\MGinbonValueList;
use App\Models\MGinbon\MGinbonValueListItem;
use App\Services\MGinbon\MGinbonValueListDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonValueMasterController extends Controller
{
    public function index(Request $request, MGinbonValueListDefaults $defaults): Response
    {
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $projects = MGinbonProject::query()->orderByDesc('year')->get(['id', 'year', 'name']);
        $project = isset($validated['year']) ? $projects->firstWhere('year', (int) $validated['year']) : $projects->first();
        abort_unless($project, 404);

        $defaults->ensureForProject($project, $request->user()?->id);
        $lists = MGinbonValueList::query()->with('items')->where('mginbon_project_id', $project->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Coordinator/MGinbon/ValueMasters/Index', [
            'project' => $project, 'projects' => $projects, 'lists' => $lists,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['value_list_id' => ['required', 'integer'], 'value' => ['required', 'string', 'max:255']]);
        $list = MGinbonValueList::findOrFail($data['value_list_id']);
        $value = trim($data['value']);
        $duplicate = $list->items()->where('value', $value)->exists();
        if ($duplicate) return back()->withErrors(['value' => '同じ値が既に登録されています。']);

        $list->items()->create([
            'value' => $value, 'sort_order' => ((int) $list->items()->max('sort_order')) + 10, 'is_active' => true,
            'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', '値を追加しました。');
    }

    public function update(Request $request, MGinbonValueListItem $item): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:255', Rule::unique('mginbon.mginbon_value_list_items', 'value')->where('mginbon_value_list_id', $item->mginbon_value_list_id)->ignore($item->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        $item->update(['value' => trim($data['value']), 'is_active' => $data['is_active'], 'updated_by' => $request->user()?->id]);

        return back()->with('success', '値を更新しました。');
    }

    public function destroy(Request $request, MGinbonValueListItem $item): RedirectResponse
    {
        $item->update(['is_active' => false, 'updated_by' => $request->user()?->id]);

        return back()->with('success', '値を削除しました。');
    }

    public function reorder(Request $request, MGinbonValueList $valueList): RedirectResponse
    {
        $data = $request->validate(['item_ids' => ['required', 'array'], 'item_ids.*' => ['integer']]);
        $actual = $valueList->items()->pluck('id')->sort()->values()->all();
        $submitted = collect($data['item_ids'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        abort_unless($actual === $submitted, 422, '並べ替え対象が一致しません。');

        DB::connection('mginbon')->transaction(function () use ($data, $request) {
            foreach ($data['item_ids'] as $index => $id) {
                MGinbonValueListItem::whereKey($id)->update(['sort_order' => ($index + 1) * 10, 'updated_by' => $request->user()?->id]);
            }
        });

        return back()->with('success', '表示順を変更しました。');
    }
    public function copy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_project_id' => ['required', 'integer', 'different:target_project_id'],
            'target_project_id' => ['required', 'integer'],
        ]);
        $source = MGinbonProject::findOrFail($data['source_project_id']);
        $target = MGinbonProject::findOrFail($data['target_project_id']);
        $sourceLists = MGinbonValueList::query()->with('items')->where('mginbon_project_id', $source->id)->orderBy('sort_order')->get();
        if ($sourceLists->isEmpty()) return back()->withErrors(['source_project_id' => 'コピー元年度に値一覧がありません。']);

        DB::connection('mginbon')->transaction(function () use ($sourceLists, $target, $request) {
            MGinbonValueList::query()->where('mginbon_project_id', $target->id)->delete();
            foreach ($sourceLists as $sourceList) {
                $list = MGinbonValueList::create([
                    'mginbon_project_id' => $target->id, 'code' => $sourceList->code, 'name' => $sourceList->name,
                    'sort_order' => $sourceList->sort_order, 'is_active' => $sourceList->is_active,
                ]);
                foreach ($sourceList->items as $sourceItem) {
                    $list->items()->create([
                        'value' => $sourceItem->value, 'sort_order' => $sourceItem->sort_order, 'is_active' => $sourceItem->is_active,
                        'linked_user_id' => $sourceItem->linked_user_id, 'linked_subcontractor_id' => $sourceItem->linked_subcontractor_id,
                        'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id,
                    ]);
                }
            }
        });

        return back()->with('success', $source->year.'年の値一覧を'.$target->year.'年へコピーしました。');
    }
}
