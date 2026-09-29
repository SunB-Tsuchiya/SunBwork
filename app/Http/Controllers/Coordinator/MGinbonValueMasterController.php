<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\MGinbon\MGinbonProjectAccess;
use App\Models\MGinbon\MGinbonProject;
use App\Models\MGinbon\MGinbonValueList;
use App\Models\MGinbon\MGinbonValueListItem;
use App\Models\ProjectJob;
use App\Services\MGinbon\MGinbonValueListDefaults;
use App\Services\ProjectJobAssigneeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonValueMasterController extends Controller
{
    private const ACTOR_LIST_CODES = ['checker', 'drawing_text_operator', 'composition_operator', 'original_scan_operator', 'proofreader'];

    public function index(Request $request, MGinbonValueListDefaults $defaults, MGinbonProjectAccess $access, ProjectJobAssigneeOptions $assigneeOptions): Response
    {
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $projects = MGinbonProject::query()->orderByDesc('year')->get(['id', 'project_job_id', 'year', 'name']);
        $project = isset($validated['year']) ? $projects->firstWhere('year', (int) $validated['year']) : $projects->first();
        abort_unless($project, 404);
        $access->requireLinked($project);

        $defaults->ensureForProject($project, $request->user()?->id);
        $lists = MGinbonValueList::query()->with('items')->where('mginbon_project_id', $project->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        $projectJob = ProjectJob::findOrFail($project->project_job_id);

        return Inertia::render('Coordinator/MGinbon/ValueMasters/Index', [
            'project' => $project, 'projects' => $projects, 'lists' => $lists,
            'actorOptions' => $assigneeOptions->for($projectJob, $request->user()),
            'actorListCodes' => self::ACTOR_LIST_CODES,
        ]);
    }

    public function store(Request $request, MGinbonProjectAccess $access): RedirectResponse
    {
        $data = $request->validate(['value_list_id' => ['required', 'integer'], 'value' => ['required', 'string', 'max:255'], 'linked_user_id' => ['nullable', 'integer'], 'linked_subcontractor_id' => ['nullable', 'integer']]);
        $list = MGinbonValueList::findOrFail($data['value_list_id']);
        $project = MGinbonProject::findOrFail($list->mginbon_project_id);
        $access->requireLinked($project);
        $links = $this->validateActorLinks($request, $project, $list->code, $data);
        $value = trim($data['value']);
        $duplicate = $list->items()->where('value', $value)->exists();
        if ($duplicate) return back()->withErrors(['value' => '同じ値が既に登録されています。']);

        $list->items()->create([
            'value' => $value, 'sort_order' => ((int) $list->items()->max('sort_order')) + 10, 'is_active' => true,
            ...$links, 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', '値を追加しました。');
    }

    public function update(Request $request, MGinbonValueListItem $item, MGinbonProjectAccess $access): RedirectResponse
    {
        $access->requireLinked(MGinbonProject::findOrFail($item->valueList->mginbon_project_id));
        $data = $request->validate([
            'value' => ['required', 'string', 'max:255', Rule::unique('mginbon.mginbon_value_list_items', 'value')->where('mginbon_value_list_id', $item->mginbon_value_list_id)->ignore($item->id)],
            'is_active' => ['required', 'boolean'],
            'linked_user_id' => ['nullable', 'integer'],
            'linked_subcontractor_id' => ['nullable', 'integer'],
        ]);
        $project = MGinbonProject::findOrFail($item->valueList->mginbon_project_id);
        $links = $this->validateActorLinks($request, $project, $item->valueList->code, $data);
        $item->update(['value' => trim($data['value']), 'is_active' => $data['is_active'], ...$links, 'updated_by' => $request->user()?->id]);

        return back()->with('success', '値を更新しました。');
    }

    public function destroy(Request $request, MGinbonValueListItem $item, MGinbonProjectAccess $access): RedirectResponse
    {
        $access->requireLinked(MGinbonProject::findOrFail($item->valueList->mginbon_project_id));
        $item->update(['is_active' => false, 'updated_by' => $request->user()?->id]);

        return back()->with('success', '値を削除しました。');
    }

    public function reorder(Request $request, MGinbonValueList $valueList, MGinbonProjectAccess $access): RedirectResponse
    {
        $access->requireLinked(MGinbonProject::findOrFail($valueList->mginbon_project_id));
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
    public function copy(Request $request, MGinbonProjectAccess $access): RedirectResponse
    {
        $data = $request->validate([
            'source_project_id' => ['required', 'integer', 'different:target_project_id'],
            'target_project_id' => ['required', 'integer'],
        ]);
        $source = MGinbonProject::findOrFail($data['source_project_id']);
        $target = MGinbonProject::findOrFail($data['target_project_id']);
        $access->requireLinked($target);
        $targetJob = ProjectJob::find($target->project_job_id);
        $targetOptions = $targetJob && $request->user()
            ? app(ProjectJobAssigneeOptions::class)->for($targetJob, $request->user())
            : ['users' => collect(), 'subcontractors' => collect()];
        $allowedUserIds = $targetOptions['users']->pluck('id')->map(fn ($id) => (int) $id);
        $allowedSubcontractorIds = $targetOptions['subcontractors']->pluck('id')->map(fn ($id) => (int) $id);
        $sourceLists = MGinbonValueList::query()->with('items')->where('mginbon_project_id', $source->id)->orderBy('sort_order')->get();
        if ($sourceLists->isEmpty()) return back()->withErrors(['source_project_id' => 'コピー元年度に値一覧がありません。']);

        DB::connection('mginbon')->transaction(function () use ($sourceLists, $target, $request, $allowedUserIds, $allowedSubcontractorIds) {
            MGinbonValueList::query()->where('mginbon_project_id', $target->id)->delete();
            foreach ($sourceLists as $sourceList) {
                $list = MGinbonValueList::create([
                    'mginbon_project_id' => $target->id, 'code' => $sourceList->code, 'name' => $sourceList->name,
                    'sort_order' => $sourceList->sort_order, 'is_active' => $sourceList->is_active,
                ]);
                foreach ($sourceList->items as $sourceItem) {
                    $list->items()->create([
                        'value' => $sourceItem->value, 'sort_order' => $sourceItem->sort_order, 'is_active' => $sourceItem->is_active,
                        'linked_user_id' => $sourceItem->linked_user_id && $allowedUserIds->contains((int) $sourceItem->linked_user_id) ? $sourceItem->linked_user_id : null,
                        'linked_subcontractor_id' => $sourceItem->linked_subcontractor_id && $allowedSubcontractorIds->contains((int) $sourceItem->linked_subcontractor_id) ? $sourceItem->linked_subcontractor_id : null,
                        'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id,
                    ]);
                }
            }
        });

        return back()->with('success', $source->year.'年の値一覧を'.$target->year.'年へコピーしました。');
    }
    /** @param array<string, mixed> $data */
    private function validateActorLinks(Request $request, MGinbonProject $project, string $listCode, array $data): array
    {
        $userId = isset($data['linked_user_id']) ? (int) $data['linked_user_id'] : null;
        $subcontractorId = isset($data['linked_subcontractor_id']) ? (int) $data['linked_subcontractor_id'] : null;
        if ($userId && $subcontractorId) throw ValidationException::withMessages(['actor' => '社員と外注先は同時に指定できません。']);
        if (! in_array($listCode, self::ACTOR_LIST_CODES, true)) {
            if ($userId || $subcontractorId) throw ValidationException::withMessages(['actor' => 'この値一覧には担当者をリンクできません。']);
            return ['linked_user_id' => null, 'linked_subcontractor_id' => null];
        }
        $options = app(ProjectJobAssigneeOptions::class)->for(ProjectJob::findOrFail($project->project_job_id), $request->user());
        if ($userId && ! $options['users']->pluck('id')->contains($userId)) throw ValidationException::withMessages(['actor' => '接続案件に所属しない社員です。']);
        if ($subcontractorId && ! $options['subcontractors']->pluck('id')->contains($subcontractorId)) throw ValidationException::withMessages(['actor' => '接続案件に所属しない外注先です。']);
        return ['linked_user_id' => $userId, 'linked_subcontractor_id' => $subcontractorId];
    }
}
