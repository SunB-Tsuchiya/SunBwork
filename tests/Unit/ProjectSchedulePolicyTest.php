<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Policies\ProjectSchedulePolicy;
use App\Models\ProjectSchedule;
use App\Models\User;

class ProjectSchedulePolicyTest extends TestCase
{
    public function test_roles_allow_update()
    {
        $policy = new ProjectSchedulePolicy();

        // Create a ProjectSchedule stub that does not touch the DB.
        $schedule = new class extends ProjectSchedule {
            public $project_job_id = null;
            public function assignments()
            {
                return new class {
                    public function where($col, $val)
                    {
                        return $this;
                    }
                    public function exists()
                    {
                        return false;
                    }
                };
            }
        };

        $user = new User();
        $user->user_role = 'coordinator';
        $this->assertTrue($policy->update($user, $schedule));

        $user->user_role = 'leader';
        $this->assertTrue($policy->update($user, $schedule));

        $user->user_role = 'admin';
        $this->assertTrue($policy->update($user, $schedule));

        $user->user_role = 'superadmin';
        $this->assertTrue($policy->update($user, $schedule));

        $user->user_role = 'user';
        // Without assignments or project team membership, should be false
        $this->assertFalse($policy->update($user, $schedule));
    }
}
