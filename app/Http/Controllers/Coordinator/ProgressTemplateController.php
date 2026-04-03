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

    public function create()
    {
        return Inertia::render('Coordinator/ProgressTemplates/Edit', [
            'template' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'column_config' => 'required|array',
            'is_shared'     => 'boolean',
        ]);

        $template = ProgressTemplate::create([
            'name'          => $validated['name'],
            'description'   => $validated['description'] ?? null,
            'column_config' => $validated['column_config'],
            'created_by'    => $request->user()->id,
            'is_shared'     => $validated['is_shared'] ?? false,
        ]);

        return redirect()->route('coordinator.progress_templates.edit', $template->id)
            ->with('success', 'テンプレートを作成しました。');
    }

    public function edit(Request $request, ProgressTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        return Inertia::render('Coordinator/ProgressTemplates/Edit', [
            'template' => [
                'id'            => $template->id,
                'name'          => $template->name,
                'description'   => $template->description,
                'column_config' => $template->column_config,
                'is_shared'     => $template->is_shared,
                'created_by'    => $template->created_by,
            ],
        ]);
    }

    public function update(Request $request, ProgressTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'column_config' => 'required|array',
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
