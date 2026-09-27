<?php

namespace App\Services;

use App\Models\ProjectJob;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectJobAssigneeOptions
{
    /**
     * Coordinator進行表と同じ範囲の担当候補を返す。
     *
     * @return array{users: Collection<int, array<string, mixed>>, subcontractors: Collection<int, array<string, mixed>>}
     */
    public function for(ProjectJob $projectJob, User $actor): array
    {
        $projectJob->loadMissing(['coordinators:id', 'subcontractors:id,name']);

        $userIds = $projectJob->teamMembers()->pluck('user_id')
            ->merge($projectJob->coordinators->pluck('id'))
            ->when($projectJob->user_id, fn (Collection $ids) => $ids->push($projectJob->user_id))
            ->filter()->unique()->values();

        $users = User::withGhosts()->whereIn('id', $userIds)->ordered()->get(['id', 'name', 'is_ghost'])
            ->map(fn (User $user) => [
                'id' => $user->id, 'name' => $user->name, 'is_ghost' => (bool) $user->is_ghost,
            ]);
        $subcontractors = $projectJob->subcontractors->map(fn (Subcontractor $subcontractor) => [
            'id' => $subcontractor->id, 'name' => $subcontractor->name, 'is_subcontractor' => true,
        ]);

        return [
            'users' => $users->unique('id')->values(),
            'subcontractors' => $subcontractors->unique('id')->values(),
        ];
    }
}
