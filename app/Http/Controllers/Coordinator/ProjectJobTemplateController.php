<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJobTemplate;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectJobTemplateController extends Controller
{
    /** 自分所有 or 共有テンプレート一覧（JSON） */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $templates = ProjectJobTemplate::with('creator:id,name')
            ->where('is_shared', true)
            ->orWhere('created_by', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'fixed_fields' => $t->fixed_fields ?? [],
                'team_members' => $t->team_members ?? [],
                'is_shared'    => $t->is_shared,
                'created_by'   => $t->created_by,
                'creator_name' => $t->creator?->name,
                'updated_at'   => $t->updated_at?->format('Y-m-d'),
            ]);

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'fixed_fields' => 'nullable|array',
            'team_members' => 'nullable|array',
            'is_shared'    => 'boolean',
        ]);

        $template = ProjectJobTemplate::create([
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'fixed_fields' => $validated['fixed_fields'] ?? [],
            'team_members' => $validated['team_members'] ?? [],
            'is_shared'    => $validated['is_shared'] ?? false,
            'created_by'   => $request->user()->id,
        ]);

        return response()->json([
            'id'           => $template->id,
            'name'         => $template->name,
            'description'  => $template->description,
            'fixed_fields' => $template->fixed_fields,
            'team_members' => $template->team_members,
            'is_shared'    => $template->is_shared,
            'created_by'   => $template->created_by,
            'updated_at'   => $template->updated_at?->format('Y-m-d'),
        ], 201);
    }

    public function update(Request $request, ProjectJobTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'fixed_fields' => 'nullable|array',
            'team_members' => 'nullable|array',
            'is_shared'    => 'boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'id'           => $template->id,
            'name'         => $template->name,
            'description'  => $template->description,
            'fixed_fields' => $template->fixed_fields,
            'team_members' => $template->team_members,
            'is_shared'    => $template->is_shared,
            'created_by'   => $template->created_by,
            'updated_at'   => $template->updated_at?->format('Y-m-d'),
        ]);
    }

    public function destroy(Request $request, ProjectJobTemplate $template)
    {
        $this->authorizeEdit($request->user(), $template);
        $template->delete();
        return response()->json(null, 204);
    }

    private function authorizeEdit(User $user, ProjectJobTemplate $template): void
    {
        $isCreator = $template->created_by === $user->id;
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isCreator || $isAdmin, 403);
    }
}
