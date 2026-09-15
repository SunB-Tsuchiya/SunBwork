<?php

namespace App\Services;

use App\Models\ClerkCalendarHoliday;
use App\Models\ClerkCalendarYear;
use Illuminate\Support\Facades\DB;

class ClerkCalendarHolidays
{
    public function defaults(): array
    {
        return json_decode(file_get_contents(resource_path('data/clerk_holidays.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function initialize(int $companyId, int $year): void
    {
        // 年ごとに一度だけ投入し、利用者が削除した祝日を復活させない。
        if (ClerkCalendarYear::where('company_id', $companyId)->where('year', $year)->whereNotNull('initialized_at')->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId, $year) {
            ClerkCalendarYear::firstOrCreate(['company_id' => $companyId, 'year' => $year]);
            $state = ClerkCalendarYear::where('company_id', $companyId)->where('year', $year)->lockForUpdate()->firstOrFail();
            if ($state->initialized_at) {
                return;
            }
            foreach ($this->defaults()['years'][$year] ?? [] as $date => $name) {
                ClerkCalendarHoliday::firstOrCreate(['company_id' => $companyId, 'date' => $date], ['name' => $name]);
            }
            $state->update(['initialized_at' => now()]);
        });
    }
}
