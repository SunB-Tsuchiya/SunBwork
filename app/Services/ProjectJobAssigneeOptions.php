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
        $projectJob->loadMissing('coordinators:id');

        $userIds = $projectJob->teamMembers()->pluck('user_id')
            ->merge($projectJob->coordinators->pluck('id'))
            ->when($projectJob->user_id, fn (Collection $ids) => $ids->push($projectJob->user_id))
            ->filter()->unique()->values();

        $users = User::query()->whereIn('id', $userIds)->ordered()->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'is_ghost' => false]);
        $ghostUsers = User::withGhosts()->where('ghost_owner_id', $actor->id)->ordered()->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'is_ghost' => true]);

        $subcontractors = Subcontractor::query()->managedBy($actor->id)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Subcontractor $subcontractor) => [
                'id' => $subcontractor->id,
                'name' => $subcontractor->name,
                'is_subcontractor' => true,
            ]);

        return [
            'users' => $users->concat($ghostUsers)->unique('id')->values(),
            'subcontractors' => $subcontractors->values(),
        ];
    }
}
