<?php

namespace App\Policies;

use App\Models\JobRequest;
use App\Models\User;

class JobRequestPolicy
{
    public function view(User $user, JobRequest $jobRequest)
    {
        return $user->id === $jobRequest->to_user_id || $user->id === $jobRequest->from_user_id;
    }

    public function update(User $user, JobRequest $jobRequest)
    {
        // accept/update only allowed to recipient
        return $user->id === $jobRequest->to_user_id;
    }
}
