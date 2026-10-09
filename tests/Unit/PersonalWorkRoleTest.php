<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\User;
use App\Support\PersonalWorkRole;
use PHPUnit\Framework\TestCase;

class PersonalWorkRoleTest extends TestCase
{
    public function test_registered_assignment_takes_priority_over_permission_role(): void
    {
        $user = new User(['user_role' => 'clerk']);
        $user->setRelation('assignment', new Assignment(['code' => 'operator']));

        $this->assertSame(['code' => 'operator', 'label' => 'オペレーター'], PersonalWorkRole::forUser($user));
    }

    public function test_clerk_without_recognized_assignment_is_office_work(): void
    {
        $user = new User(['user_role' => 'clerk']);
        $user->setRelation('assignment', new Assignment(['code' => 'other']));

        $this->assertSame(['code' => 'jimu', 'label' => '事務'], PersonalWorkRole::forUser($user));
    }
}
