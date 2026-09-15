<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class ClerkCalendarRange
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function forYear(int $year): array
    {
        $timezone = config('app.timezone', 'Asia/Tokyo');
        $january = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone);
        $nextJanuary = CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, $timezone);

        return [
            $january->subDays($january->dayOfWeek)->subDays(35),
            $nextJanuary->subDays($nextJanuary->dayOfWeek)->addDays(35),
        ];
    }
}
