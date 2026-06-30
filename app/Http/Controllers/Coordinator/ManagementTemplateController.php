<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ManagementTemplateController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $templates = ProgressTemplate::with('creator:id,name')
            ->where('sheet_type', 'management')
            ->where(function ($query) use ($userId) {
                $query->where('is_shared', true)
                    ->orWhere('created_by', $userId);
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (ProgressTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_shared' => $template->is_shared,
                'created_by' => $template->created_by,
                'creator_name' => $template->creator?->name,
                'updated_at' => $template->updated_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Coordinator/ManagementTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return Inertia::render('Coordinator/ManagementTemplates/Edit', [
            'template' => null,
            'defaultColumnConfig' => $this->defaultColumnConfig(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        ProgressTemplate::create([
            ...$validated,
            'row_config' => [],
            'sheet_type' => 'management',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('coordinator.management_templates.index')
            ->with('success', '管理シートテンプレートを作成しました。');
    }

    public function edit(Request $request, ProgressTemplate $template)
    {
        $this->authorizeManagementTemplate($request->user(), $template);

        return Inertia::render('Coordinator/ManagementTemplates/Edit', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'column_config' => $template->column_config,
                'is_shared' => $template->is_shared,
            ],
            'defaultColumnConfig' => [],
        ]);
    }

    public function update(Request $request, ProgressTemplate $template)
    {
        $this->authorizeManagementTemplate($request->user(), $template);

        $template->update([
            ...$this->validateTemplate($request),
            'row_config' => [],
            'sheet_type' => 'management',
        ]);

        return back()->with('success', '管理シートテンプレートを更新しました。');
    }

    public function destroy(Request $request, ProgressTemplate $template)
    {
        $this->authorizeManagementTemplate($request->user(), $template);
        $template->delete();

        return redirect()->route('coordinator.management_templates.index')
            ->with('success', '管理シートテンプレートを削除しました。');
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'column_config' => 'required|array|min:1',
            'is_shared' => 'boolean',
        ]);
    }

    private function authorizeManagementTemplate(User $user, ProgressTemplate $template): void
    {
        abort_unless($template->sheet_type === 'management', 404);

        $isCreator = $template->created_by === $user->id;
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isCreator || $isAdmin, 403);
    }

    private function defaultColumnConfig(): array
    {
        return [[
            'key' => (string) Str::uuid(),
            'label' => '初校',
            'item_label' => '',
            'type' => 'stage',
            'children' => [
                ['key' => (string) Str::uuid(), 'label' => '進行', 'type' => 'coordinator'],
                ['key' => (string) Str::uuid(), 'label' => '組版', 'type' => 'worker'],
                ['key' => (string) Str::uuid(), 'label' => '校正', 'type' => 'proof_v2'],
                ['key' => (string) Str::uuid(), 'label' => '校正２', 'type' => 'proof_v2'],
            ],
        ]];
    }
}
