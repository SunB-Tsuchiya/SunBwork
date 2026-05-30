<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Client $client): bool
    {
        if ($user->user_role === 'superadmin') return true;
        if (!$user->company_id) return false;
        return $client->companies()->where('companies.id', $user->company_id)->exists();
    }

    public function create(User $user)
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isLeader()
            || $user->isCoordinator() || $user->isClerk()
            || isset($user->company_id);
    }

    public function update(User $user, Client $client): bool
    {
        if ($user->user_role === 'superadmin') return true;
        if (!$user->company_id) return false;
        return $client->companies()->where('companies.id', $user->company_id)->exists();
    }

    public function delete(User $user, Client $client)
    {
        return $this->update($user, $client);
    }
}
