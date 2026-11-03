<?php

namespace App\Services;

use App\Support\PersonalWorkPoints;

/** Shared minute allocation; default output preserves the existing analysis contract. */
class WorkMinuteLedger
{
    public function calculate(array $items, ?array $window, ?array $overtimeRange = null, bool $withRecords = false): array
    {
        $lunchFrom = $window['break_start'] ?? 720;
        $lunchTo = $window['break_end'] ?? 780;
        $activities = [];
        $recordMinutes = [];
        $covered = [];
        $points = 0.0;
        $timeBreakdown = ['job' => 0.0, 'quick' => 0.0, 'meeting' => 0.0, 'other' => 0.0];
        $overtimeBreakdown = $timeBreakdown;
        for ($minute = 0; $minute < 1440; $minute++) {
            if ($minute >= $lunchFrom && $minute < $lunchTo) continue;
            $active = array_values(array_filter($items, fn ($item) => $item['from'] <= $minute && $item['to'] > $minute));
            if (! $active) continue;
            $priority = max(array_column($active, 'priority'));
            $active = array_values(array_filter($active, fn ($item) => $item['priority'] === $priority));
            $covered[] = $minute;
            $share = 1 / count($active);
            foreach ($active as $item) {
                if ($withRecords) $recordMinutes[$item['key']] = ($recordMinutes[$item['key']] ?? 0) + $share;
                $timeBreakdown[$item['time_group'] ?? 'other'] += $share;
                if ($overtimeRange && $minute >= $overtimeRange[0] && $minute < $overtimeRange[1]) {
                    $overtimeBreakdown[$item['time_group'] ?? 'other'] += $share;
                }
                $key = $item['work_category'].'|'.$item['activity_type'];
                $activities[$key]['work_category'] = $item['work_category'];
                $activities[$key]['activity_type'] = $item['activity_type'];
                $activities[$key]['minutes'] = ($activities[$key]['minutes'] ?? 0) + $share;
                $activities[$key]['points'] = ($activities[$key]['points'] ?? 0)
                    + $share / 60 * PersonalWorkPoints::POINTS_PER_HOUR * $item['coefficient'];
                $activities[$key]['records'][$item['key']] = true;
                $points += $share / 60 * PersonalWorkPoints::POINTS_PER_HOUR * $item['coefficient'];
            }
        }
        return ['minutes' => count($covered), 'points' => $points, 'activities' => $activities,
            'time_breakdown_minutes' => $timeBreakdown,
            'overtime_breakdown_minutes' => $overtimeBreakdown,
            'covered' => $covered,
            ...($withRecords ? ['record_minutes' => $recordMinutes] : [])];
    }
}
