<?php

namespace App\Support;

use App\Models\PersonalWorkEntry;

/** Trial-only measure of recorded work, separate from WorkloadAnalyzer scores. */
class PersonalWorkPoints
{
    public const POINTS_PER_HOUR = 10;

    public const DEFAULT_COEFFICIENT = 1.0;

    public const COEFFICIENTS = [
        'new_creation' => 1.2,
        'proof_revision_heavy' => 1.3,
        'proof_revision_light' => 0.9,
        'manuscript_check' => 1.1,
        'meeting' => 0.8,
        'office_work' => 0.8,
        'document_processing' => 0.9,
        'voucher_check' => 0.9,
        'customer_contact' => 0.8,
        'other' => 0.8,
        'my_job' => 1.0,
    ];

    public static function coefficient(?string $activityType): float
    {
        return self::COEFFICIENTS[$activityType] ?? self::DEFAULT_COEFFICIENT;
    }

    public static function points(PersonalWorkEntry $entry): float
    {
        if (! filled($entry->result_note) || ! filled($entry->activity_type)
            || ! PersonalWorkActivities::allows($entry->work_category, $entry->activity_type)
            || (int) $entry->actual_minutes <= 0) {
            return 0.0;
        }

        return round($entry->actual_minutes / 60 * self::POINTS_PER_HOUR * self::coefficient($entry->activity_type), 1);
    }
}
