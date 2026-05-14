<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectItemEntry;
use Illuminate\Http\Request;

class ItemEntryController extends Controller
{
    public function index(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJob($request->user(), $projectJob);

        $entries = $projectJob->itemEntries()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        return response()->json(['entries' => $entries]);
    }

    public function update(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJob($request->user(), $projectJob);

        $validated = $request->validate([
            'entries'             => 'required|array',
            'entries.*.id'        => 'nullable|integer',
            'entries.*.name'      => 'required|string|max:255',
            'entries.*.sort_order'=> 'integer',
        ]);

        $incoming = collect($validated['entries']);
        $existingIds = $projectJob->itemEntries()->pluck('id');

        $keptIds = $incoming->pluck('id')->filter()->values();
        $projectJob->itemEntries()->whereNotIn('id', $keptIds)->delete();

        foreach ($incoming as $idx => $data) {
            $attrs = [
                'name'       => $data['name'],
                'sort_order' => $data['sort_order'] ?? $idx,
            ];
            if (!empty($data['id'])) {
                ProjectItemEntry::where('id', $data['id'])
                    ->where('project_job_id', $projectJob->id)
                    ->update($attrs);
            } else {
                $projectJob->itemEntries()->create($attrs);
            }
        }

        return response()->json(['message' => '保存しました。']);
    }

    public function suggestions(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeJob($request->user(), $projectJob);

        $q = $request->query('q', '');
        $entries = $projectJob->itemEntries()
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('sort_order')
            ->limit(30)
            ->pluck('name');

        return response()->json(['suggestions' => $entries]);
    }

    private function authorizeJob($user, ProjectJob $projectJob): void
    {
        $role = $user->user_role;
        abort_unless(in_array($role, ['coordinator', 'clerk', 'leader', 'admin', 'superadmin']), 403);

        if (in_array($role, ['coordinator', 'clerk', 'leader'])) {
            $isMember = $projectJob->user_id === $user->id
                || $projectJob->coordinators()->where('user_id', $user->id)->exists()
                || $projectJob->teamMembers()->where('user_id', $user->id)->exists();
            abort_unless($isMember, 403);
        }
    }
}
