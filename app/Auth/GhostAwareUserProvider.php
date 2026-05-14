<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

class GhostAwareUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        return $this->createModel()->withGhosts()->find($identifier);
    }

    public function retrieveByToken($identifier, $token)
    {
        $retrieved = $this->createModel()->withGhosts()->find($identifier);

        if (!$retrieved) {
            return null;
        }

        $rememberToken = $retrieved->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token)
            ? $retrieved
            : null;
    }
}
