<?php

namespace Tests\Unit\MGinbon;

use App\Services\MGinbon\MGinbonPlannedActorPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MGinbonPlannedActorPolicyTest extends TestCase
{
    #[Test]
    public function empty_or_same_user_plans_do_not_require_confirmation(): void
    {
        $policy = new MGinbonPlannedActorPolicy;

        $this->assertFalse($policy->requiresReplacementConfirmation([], 10));
        $this->assertFalse($policy->requiresReplacementConfirmation([
            (object) ['user_id' => 10, 'subcontractor_id' => null],
        ], 10));
    }

    #[Test]
    public function another_user_or_subcontractor_requires_confirmation(): void
    {
        $policy = new MGinbonPlannedActorPolicy;

        $this->assertTrue($policy->requiresReplacementConfirmation([
            (object) ['user_id' => 11, 'subcontractor_id' => null],
        ], 10));
        $this->assertTrue($policy->requiresReplacementConfirmation([
            (object) ['user_id' => null, 'subcontractor_id' => 20],
        ], 10));
    }
}
