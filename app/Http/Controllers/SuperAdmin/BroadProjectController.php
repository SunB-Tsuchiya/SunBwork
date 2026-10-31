<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BroadProject;
use App\Models\Client;
use App\Services\BroadProjectTree;
use App\Services\PersonalWorkSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BroadProjectController extends Controller
{
    protected string $defaultPrefix = 'superadmin.broad_projects';

    public function __construct(protected BroadProjectTree $tree, protected PersonalWorkSchedule $schedule) {}

    protected function prefix(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';
        foreach (['coordinator.broad_projects', 'leader.broad_projects'] as $prefix) {
            if (str_starts_with($name, $prefix.'.')) return $prefix;
        }
        return $this->defaultPrefix;
    }

    protected function authorizeContext(Request $request): void
    {
        $this->tree->authorize($request->user());
        if (str_starts_with($this->prefix($request), 'user.')) {
            abort_unless($this->schedule->available($request->user()), 403);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeContext($request);
        return Inertia::render(str_starts_with($this->prefix($request), 'user.') ? 'User/WorkHub/BroadProjects' : 'SuperAdmin/BroadProjects/Index', [
            'projects' => $this->tree->rows($request->user()),
            'routePrefix' => $this->prefix($request),
            'canManage' => $this->tree->canManage($request->user()),
        ]);
    }

    public function clients(Request $request)
    {
        $this->authorizeContext($request);
        $params = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($params['q'] ?? '');
        return response()->json(Client::forCompany($request->user()->company_id)->active()
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name']));
    }

    public function store(Request $request)
    {
        $this->authorizeContext($request);
        $this->tree->save($request->user(), $request->all());
        return to_route($this->prefix($request).'.index')->with('success', '大まかな案件を追加しました。');
    }

    public function update(Request $request, BroadProject $broadProject)
    {
        $this->authorizeContext($request);
        $this->tree->save($request->user(), $request->all(), $broadProject);
        return to_route($this->prefix($request).'.index')->with('success', '大まかな案件を更新しました。');
    }

    public function move(Request $request, BroadProject $broadProject)
    {
        $this->authorizeContext($request);
        $this->tree->move($request->user(), $broadProject, $request->all());
        return to_route($this->prefix($request).'.index')->with('success', '中グループを移動しました。');
    }

    public function reorder(Request $request)
    {
        $this->authorizeContext($request);
        $this->tree->reorder($request->user(), $request->all());
        return to_route($this->prefix($request).'.index')->with('success', '部署共通の並び順を保存しました。');
    }

}
