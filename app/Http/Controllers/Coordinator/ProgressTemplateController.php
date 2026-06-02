<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgressTemplateController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $templates = ProgressTemplate::with('creator:id,name')
            ->where('is_shared', true)
            ->orWhere('created_by', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'is_shared'    => $t->is_shared,
                'created_by'   => $t->created_by,
                'creator_name' => $t->creator?->name,
                'updated_at'   => $t->updated_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Coordinator/ProgressTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(Request $request)
    {
        $companyId = $request->user()->company_id;
        $companyFilter = function ($q) use ($companyId) {
            $q->whereNull('company_id');
            if ($companyId) {
                $q->orWhere('company_id', $companyId);
            }
        };

        return Inertia::render('Coordinator/ProgressTemplates/Edit', [
            'template'      => null,
            'stages'        => \App\Models\Stage::where($companyFilter)->orderBy('id')->get(['id', 'name']),
            'sizes'         => \App\Models\Size::where($companyFilter)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']),
            'assignments'   => \App\Models\Assignment::orderBy('name')->get(['id', 'name', 'code']),
            'workItemTypes' => \App\Models\WorkItemType::where($companyFilter)->orderBy('id')->get(['id', 'name', 'group']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'column_config' => 'required|array',
            'row_config'    => 'nullable|array',
            'is_shared'     => 'boolean',
        ]);

        $template = ProgressTemplate::create([
            'name'          => $validated['name'],
            'description'   => $validated['description'] ?? null,
            'column_config' => $validated['column_config'],
            'row_config'    => $validated['row_config'] ?? [],
            'created_by'    => $request->user()->id,
            'is_shared'     => $validated['is_shared'] ?? false,
        ]);

        return redirect()->route('coordinator.progress_templates.index')
            ->with('success', 'テンプレートを作成しました。');
    }

    public function show(Request $request, ProgressTemplate $template)
    {
        $userId = $request->user()->id;
        $isOwner = $template->created_by === $userId;
        $isAdmin = in_array($request->user()->user_role, ['admin', 'superadmin']);
        abort_unless($isOwner || $isAdmin || $template->is_shared, 403);

        return Inertia::render('Coordinator/ProgressTemplates/Show', [
            'template' => [
                'id'            => $template->id,
                'name'          => $template->name,
                'description'   => $template->description,
                'column_config' => $template->column_config,
                'row_config'    => $template->row_config ?? [],
                'is_shared'     => $template->is_shared,
                'created_by'    => $template->created_by,
                'creator_name'  => $template->creator?->name,
                'updated_at'    => $template->updated_at?->format('Y-m-d'),
            ],
            'canEdit' => $isOwner || $isAdmin,
        ]);
    }

    public function edit(Request $request, ProgressTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $companyId = $request->user()->company_id;
        $companyFilter = function ($q) use ($companyId) {
            $q->whereNull('company_id');
            if ($companyId) {
                $q->orWhere('company_id', $companyId);
            }
        };

        return Inertia::render('Coordinator/ProgressTemplates/Edit', [
            'template' => [
                'id'            => $template->id,
                'name'          => $template->name,
                'description'   => $template->description,
                'column_config' => $template->column_config,
                'row_config'    => $template->row_config ?? [],
                'is_shared'     => $template->is_shared,
                'created_by'    => $template->created_by,
            ],
            'stages'        => \App\Models\Stage::where($companyFilter)->orderBy('id')->get(['id', 'name']),
            'sizes'         => \App\Models\Size::where($companyFilter)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']),
            'assignments'   => \App\Models\Assignment::orderBy('name')->get(['id', 'name', 'code']),
            'workItemTypes' => \App\Models\WorkItemType::where($companyFilter)->orderBy('id')->get(['id', 'name', 'group']),
        ]);
    }

    public function update(Request $request, ProgressTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'column_config' => 'required|array',
            'row_config'    => 'nullable|array',
            'is_shared'     => 'boolean',
        ]);

        $template->update($validated);

        return back()->with('success', 'テンプレートを更新しました。');
    }

    public function destroy(Request $request, ProgressTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $template->delete();

        return redirect()->route('coordinator.progress_templates.index')
            ->with('success', 'テンプレートを削除しました。');
    }

    // ─────

    private function authorizeEdit(User $user, ProgressTemplate $template): void
    {
        $isCreator = $template->created_by === $user->id;
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isCreator || $isAdmin, 403);
    }
}
