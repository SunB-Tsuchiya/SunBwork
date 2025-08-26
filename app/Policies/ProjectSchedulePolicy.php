<?php

namespace App\Policies;

use App\Models\ProjectSchedule;
use App\Models\User;

class ProjectSchedulePolicy
{
    /**
     * Determine whether the user can update the project schedule.
     */
    public function update(User $user, ProjectSchedule $projectSchedule)
    {
        // Coordinators may update any schedule in this PoC
        // Use the model helper method isCoordinator() instead of a non-existent property
        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) {
            return true;
        }

        // Allow admins and superadmins
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }
        // Allow leaders (leader is a higher role compatible with coordinator)
        if (method_exists($user, 'isLeader') && $user->isLeader()) {
            return true;
        }

        // Otherwise allow if the user is assigned to the schedule
        if ($projectSchedule->assignments()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Also allow if the user is a member of the parent project_job (project_team_members)
        if ($projectSchedule->project_job_id) {
            return \App\Models\ProjectTeamMember::where('project_job_id', $projectSchedule->project_job_id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }
}
