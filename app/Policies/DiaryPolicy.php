<?php
namespace App\Policies;

use App\Models\Diary;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DiaryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(\App\Models\User $user, \App\Models\Diary $diary)
    {
        return $user->id === $diary->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(\App\Models\User $user, \App\Models\Diary $diary)
    {
        return $user->id === $diary->user_id;
    }
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(\App\Models\User $user, \App\Models\Diary $diary)
    {
        return $user->id === $diary->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Diary $diary): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Diary $diary): bool
    {
        return false;
    }
}
