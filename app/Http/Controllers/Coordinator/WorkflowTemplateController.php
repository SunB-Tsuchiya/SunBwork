<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\WorkflowTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = WorkflowTemplate::with('creator:id,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'stage_config' => $t->stage_config,
                'created_by'   => $t->created_by,
                'creator_name' => $t->creator?->name,
                'updated_at'   => $t->updated_at?->format('Y-m-d'),
            ]);

        return Inertia::render('Coordinator/WorkflowTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'stage_config' => 'required|array',
            'stage_config.stages'          => 'required|array|min:1',
            'stage_config.stages.*.key'    => 'required|string|max:64',
            'stage_config.stages.*.label'  => 'required|string|max:255',
            'stage_config.stages.*.type'   => 'required|in:coordinator,worker',
        ]);

        $template = WorkflowTemplate::create([
            'name'         => $validated['name'],
            'stage_config' => $validated['stage_config'],
            'created_by'   => $request->user()->id,
        ]);

        return response()->json([
            'template' => [
                'id'           => $template->id,
                'name'         => $template->name,
                'stage_config' => $template->stage_config,
                'created_by'   => $template->created_by,
                'updated_at'   => $template->updated_at?->format('Y-m-d'),
            ],
        ]);
    }

    public function update(Request $request, WorkflowTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'stage_config' => 'sometimes|array',
            'stage_config.stages'          => 'required_with:stage_config|array|min:1',
            'stage_config.stages.*.key'    => 'required_with:stage_config|string|max:64',
            'stage_config.stages.*.label'  => 'required_with:stage_config|string|max:255',
            'stage_config.stages.*.type'   => 'required_with:stage_config|in:coordinator,worker',
        ]);

        $template->update($validated);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, WorkflowTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $template->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeEdit(User $user, WorkflowTemplate $template): void
    {
        $isCreator = $template->created_by === $user->id;
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isCreator || $isAdmin, 403);
    }
}
