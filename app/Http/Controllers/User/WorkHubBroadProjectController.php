<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\UserWorkHub;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkHubBroadProjectController extends Controller
{
    public function __construct(private UserWorkHub $hub) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->hub->authorize($user);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'show_inactive' => ['nullable', 'boolean']]);
        $term = trim($filters['q'] ?? '');
        $showInactive = $request->boolean('show_inactive');
        $projects = $this->hub->broadProjects($user)
            ->when(! $showInactive, fn ($q) => $q->where('is_active', true))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($match) => $match->where('name', 'like', $like)
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $like))
                    ->orWhere('fiscal_year', 'like', $like));
            })
            ->with('client:id,name')->orderByDesc('is_active')->orderBy('name')->orderBy('id')
            ->paginate(30)->withQueryString();

        return Inertia::render('User/WorkHub/BroadProjects', [
            'projects' => $projects, 'filters' => ['q' => $term, 'show_inactive' => $showInactive],
        ]);
    }

    public function clients(Request $request)
    {
        $this->hub->authorize($request->user());
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');

        return response()->json($this->hub->clients($request->user(), $term));
    }

    public function store(Request $request)
    {
        $this->hub->createBroadProject($request->user(), $request->all());

        return to_route('user.work.broad_projects.index')->with('success', '大まかな案件を追加しました。');
    }
}
