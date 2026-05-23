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
     */
    public function view(User $user, Client $client): bool
    {
        if (in_array($user->user_role, ['superadmin', 'admin'])) return true;

        if (in_array($user->user_role, ['leader', 'coordinator', 'clerk'])) {
            if ($user->company_id && $client->company_id) {
                return intval($user->company_id) === intval($client->company_id);
            }
            return true;
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
     * Update only allowed when same company or superadmin/admin
     */
    public function update(User $user, Client $client): bool
    {
        if (in_array($user->user_role, ['superadmin', 'admin'])) return true;

        if (in_array($user->user_role, ['leader', 'coordinator', 'clerk'])) {
            if ($user->company_id && $client->company_id) {
                return intval($user->company_id) === intval($client->company_id);
            }
            return true;
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
