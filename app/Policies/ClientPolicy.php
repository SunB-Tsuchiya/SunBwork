<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the client.
     * Allow when user's company_id matches client's company_id or when user is superadmin.
     */
    public function view(User $user, Client $client)
    {
        if ($user->user_role === 'superadmin') return true;
        if (isset($user->company_id) && isset($client->company_id)) {
            return intval($user->company_id) === intval($client->company_id);
        }
        return false;
    }

    /**
     * Determine whether the user can create clients.
     */
    public function create(User $user)
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isLeader()
            || $user->isCoordinator() || $user->isClerk()
            || isset($user->company_id);
    }

    /**
     * Update only allowed when same company or superadmin
     */
    public function update(User $user, Client $client)
    {
        if ($user->user_role === 'superadmin') return true;
        if (isset($user->company_id) && isset($client->company_id)) {
            return intval($user->company_id) === intval($client->company_id);
        }
        return false;
    }

    /**
     * Delete only allowed when same company or superadmin
     */
    public function delete(User $user, Client $client)
    {
        return $this->update($user, $client);
    }
}
