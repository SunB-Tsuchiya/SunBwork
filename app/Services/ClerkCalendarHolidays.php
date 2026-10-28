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

    /**
     * 会社の祝日を、年の前後を含めて返す（[{date, name}]）。ストリップ前後の余白も含め、年跨ぎの祝日を返す。
     * $seed が偽（既定）のときは、DB を書き換えない（閲覧のみの画面用）。未設定の年は、元の祝日データから補う。
     * Clerk のカレンダーは $seed = true で、年ごとの初期投入をしたうえで、DB の祝日を返す。
     */
    public function forCompany(int $companyId, int $year, bool $seed = false): array
    {
        $years = [$year - 1, $year, $year + 1];
        if ($seed) {
            foreach ($years as $y) {
                $this->initialize($companyId, $y);
            }
        }

        $out = [];
        $initialized = ClerkCalendarYear::where('company_id', $companyId)->whereIn('year', $years)->whereNotNull('initialized_at')->pluck('year')->all();
        $rows = ClerkCalendarHoliday::where('company_id', $companyId)
            ->whereBetween('date', [($year - 1).'-01-01', ($year + 1).'-12-31'])->orderBy('date')->get(['date', 'name']);
        foreach ($rows as $r) {
            $out[$r->date->format('Y-m-d')] = $r->name;
        }
        // 未設定の年（まだ Clerk が開いていない年）は、元の祝日データで補う（DB は書き換えない）
        foreach (array_diff($years, $initialized) as $y) {
            foreach ($this->defaults()['years'][$y] ?? [] as $date => $name) {
                $out[$date] ??= $name;
            }
        }
        ksort($out);

        return array_map(fn ($date, $name) => ['date' => $date, 'name' => $name], array_keys($out), $out);
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
