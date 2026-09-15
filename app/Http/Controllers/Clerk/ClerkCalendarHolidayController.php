<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Models\ClerkCalendarHoliday;
use App\Services\ClerkCalendarHolidays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClerkCalendarHolidayController extends Controller
{
    use ResolvesContextCompany;

    public function settings()
    {
        return Inertia::render('Clerk/Calendar/Settings');
    }

    public function index(Request $request, ClerkCalendarHolidays $holidays)
    {
        $year = $this->year($request);
        $companyId = $this->companyId();
        $holidays->initialize($companyId, $year);

        return Inertia::render('Clerk/Calendar/Holidays', [
            'year' => $year,
            'holidays' => $this->queryYear($companyId, $year)->get(['id', 'date', 'name']),
            'defaultYears' => array_keys($holidays->defaults()['years']),
        ]);
    }

    public function data(Request $request, ClerkCalendarHolidays $holidays)
    {
        $year = $this->year($request);
        $companyId = $this->companyId();
        // ストリップ前後の余白も含め、年跨ぎの祝日を返す。
        foreach ([$year - 1, $year, $year + 1] as $adjacentYear) {
            $holidays->initialize($companyId, $adjacentYear);
        }

        return response()->json(ClerkCalendarHoliday::where('company_id', $companyId)
            ->whereBetween('date', [($year - 1).'-01-01', ($year + 1).'-12-31'])
            ->orderBy('date')->get(['date', 'name']));
    }

    public function store(Request $request, ClerkCalendarHolidays $holidays)
    {
        $companyId = $this->companyId();
        $year = $this->year($request);
        $holidays->initialize($companyId, $year);
        ClerkCalendarHoliday::create(['company_id' => $companyId] + $this->validated($request, $companyId, $year));

        return to_route('clerk.calendar.holidays.index', ['year' => $year]);
    }

    public function update(Request $request, ClerkCalendarHoliday $holiday)
    {
        $companyId = $this->companyId();
        abort_unless($holiday->company_id === $companyId, 404);
        $year = $this->year($request);
        abort_unless((int) $holiday->date->format('Y') === $year, 422);
        $holiday->update($this->validated($request, $companyId, $year, $holiday));

        return to_route('clerk.calendar.holidays.index', ['year' => $year]);
    }

    public function destroy(ClerkCalendarHoliday $holiday)
    {
        abort_unless($holiday->company_id === $this->companyId(), 404);
        $year = (int) $holiday->date->format('Y');
        $holiday->delete();

        return to_route('clerk.calendar.holidays.index', ['year' => $year]);
    }

    private function validated(Request $request, int $companyId, int $year, ?ClerkCalendarHoliday $holiday = null): array
    {
        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$year.'-01-01', 'before_or_equal:'.$year.'-12-31',
                Rule::unique('clerk_calendar_holidays', 'date')->where('company_id', $companyId)->ignore($holiday?->id)],
            'name' => ['required', 'string', 'max:100'],
        ], ['date.unique' => 'この日付は登録済みです。', 'name.required' => '祝日名を入力してください。']);
    }

    private function year(Request $request): int
    {
        $request->validate(['year' => 'sometimes|required|integer|min:1900|max:2100']);

        return (int) $request->input('year', now('Asia/Tokyo')->year);
    }

    private function queryYear(int $companyId, int $year)
    {
        return ClerkCalendarHoliday::where('company_id', $companyId)->whereBetween('date', [$year.'-01-01', $year.'-12-31'])->orderBy('date');
    }

    private function companyId(): int
    {
        $id = $this->contextCompanyId() ?? Auth::user()->company_id;
        abort_unless($id, 403, '会社を選択してください。');

        return $id;
    }
}
