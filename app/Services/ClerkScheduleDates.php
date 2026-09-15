<?php

namespace App\Services;

use App\Models\ClerkScheduleRule;
use Carbon\CarbonImmutable;

class ClerkScheduleDates
{
    public function between(ClerkScheduleRule $rule, string $from, string $to): array
    {
        $from = max($from, $rule->starts_on->format('Y-m-d'));
        $to = min($to, $rule->ends_on?->format('Y-m-d') ?? '2100-12-31');
        if ($from > $to) {
            return [];
        }
        if ($rule->recurrence === 'custom_dates') {
            $dates = array_filter($rule->custom_dates ?? [], fn ($date) => $date >= $from && $date <= $to);
            sort($dates);

            return array_values(array_unique($dates));
        }
        $result = [];
        $month = CarbonImmutable::parse($from, 'Asia/Tokyo')->startOfMonth();
        while ($month->format('Y-m-d') <= $to) {
            $day = match ($rule->recurrence) {
                'month_end' => $month->daysInMonth,
                'monthly_day' => (int) $rule->day_of_month,
                'monthly_weekday' => (int) $rule->ordinal === 0
                    ? $month->daysInMonth - (($month->endOfMonth()->dayOfWeek - (int) $rule->day_of_week + 7) % 7)
                    : 1 + (((int) $rule->day_of_week - $month->dayOfWeek + 7) % 7) + 7 * ((int) $rule->ordinal - 1),
                default => 0,
            };
            if ($day >= 1 && $day <= $month->daysInMonth) {
                $date = $month->day($day)->format('Y-m-d');
                if ($date >= $from && $date <= $to) {
                    $result[] = $date;
                }
            }
            $month = $month->addMonth();
        }

        return $result;
    }
}
