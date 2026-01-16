<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectJobAssignmentByMyself;

class EventPolicy
{
    /**
     * イベント閲覧権限
     */
    public function view(User $user, Event $event)
    {
        if ($event->user_id === $user->id) return true;
        // If this event is linked to a project job assignment, allow the assignment owner to view
        try {
            if (isset($event->project_job_assignment_id)) {
                // check canonical assignment table
                if (Schema::hasTable('project_job_assignments')) {
                    $pa = ProjectJobAssignment::find($event->project_job_assignment_id);
                    if ($pa && $pa->user_id === $user->id) return true;
                }
                // check user-specific assignments
                if (Schema::hasTable('project_job_assignment_by_myself')) {
                    $pmy = ProjectJobAssignmentByMyself::find($event->project_job_assignment_id);
                    if ($pmy && $pmy->user_id === $user->id) return true;
                }
            }
        } catch (\Throwable $__e) {
            // ignore and fallthrough
        }
        return false;
    }

    /**
     * イベント更新権限
     */
    public function update(User $user, Event $event)
    {
        if ($event->user_id === $user->id) return true;
        try {
            if (isset($event->project_job_assignment_id)) {
                if (Schema::hasTable('project_job_assignments')) {
                    $pa = ProjectJobAssignment::find($event->project_job_assignment_id);
                    if ($pa && $pa->user_id === $user->id) return true;
                }
                if (Schema::hasTable('project_job_assignment_by_myself')) {
                    $pmy = ProjectJobAssignmentByMyself::find($event->project_job_assignment_id);
                    if ($pmy && $pmy->user_id === $user->id) return true;
                }
            }
        } catch (\Throwable $__e) {}
        return false;
    }

    /**
     * イベント削除権限
     */
    public function delete(User $user, Event $event)
    {
        if ($event->user_id === $user->id) return true;
        try {
            if (isset($event->project_job_assignment_id)) {
                if (Schema::hasTable('project_job_assignments')) {
                    $pa = ProjectJobAssignment::find($event->project_job_assignment_id);
                    if ($pa && $pa->user_id === $user->id) return true;
                }
                if (Schema::hasTable('project_job_assignment_by_myself')) {
                    $pmy = ProjectJobAssignmentByMyself::find($event->project_job_assignment_id);
                    if ($pmy && $pmy->user_id === $user->id) return true;
                }
            }
        } catch (\Throwable $__e) {}
        return false;
    }
}
