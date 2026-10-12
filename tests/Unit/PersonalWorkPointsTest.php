<?php

namespace Tests\Unit;

use App\Models\PersonalWorkEntry;
use App\Support\PersonalWorkPoints;
use PHPUnit\Framework\TestCase;

class PersonalWorkPointsTest extends TestCase
{
    public function test_recorded_minutes_count_without_an_optional_result_or_activity(): void
    {
        $entry = new PersonalWorkEntry([
            'work_category' => 'dtp', 'plan_slot' => 'hour',
            'planned_start_time' => '09:00', 'planned_end_time' => '10:00',
            'actual_minutes' => 60,
        ]);

        $this->assertSame(10.0, PersonalWorkPoints::points($entry));
    }

    public function test_proof_heavy_and_light_have_simple_visible_coefficients(): void
    {
        $heavy = new PersonalWorkEntry([
            'work_category' => 'kousei', 'activity_type' => 'proof_revision_heavy',
            'actual_minutes' => 60, 'result_note' => '赤字を反映して全体を再確認',
        ]);
        $light = new PersonalWorkEntry([
            'work_category' => 'kousei', 'activity_type' => 'proof_revision_light',
            'actual_minutes' => 60, 'result_note' => '修正箇所を確認',
        ]);

        $this->assertSame(13.0, PersonalWorkPoints::points($heavy));
        $this->assertSame(9.0, PersonalWorkPoints::points($light));
    }

    public function test_quantity_does_not_change_points(): void
    {
        $entry = new PersonalWorkEntry([
            'work_category' => 'dtp', 'activity_type' => 'new_creation',
            'actual_minutes' => 90, 'result_note' => '新規ページを制作',
            'actual_quantity' => 20, 'actual_unit' => 'ページ',
        ]);

        $this->assertSame(18.0, PersonalWorkPoints::points($entry));
        $entry->actual_quantity = 2000;
        $this->assertSame(18.0, PersonalWorkPoints::points($entry));
    }
}
