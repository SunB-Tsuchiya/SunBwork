<?php

namespace App\Policies;

use App\Models\ProjectMemo;
use App\Models\User;

class ProjectMemoPolicy
{
    /**
     * Determine whether the user can view the memo.
     */
    public function view(User $user, ProjectMemo $memo)
    {
        // Owner can view; coordinators/admins/leaders can view in this PoC
        if ($user->id === $memo->user_id) return true;
        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) return true;
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;
        if (method_exists($user, 'isLeader') && $user->isLeader()) return true;
        return false;
    }

    /**
     * Determine whether the user can update the memo.
     */
    public function update(User $user, ProjectMemo $memo)
    {
        // Same rules as view for this PoC
        return $this->view($user, $memo);
    }

    /**
     * Determine whether the user can delete the memo.
     */
    public function delete(User $user, ProjectMemo $memo)
    {
        // Owner may delete
        if ($user->id === $memo->user_id) return true;
        // Coordinators/admins/leaders may delete
        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) return true;
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return true;
        if (method_exists($user, 'isLeader') && $user->isLeader()) return true;
        return false;
    }
}
