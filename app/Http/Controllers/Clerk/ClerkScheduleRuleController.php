<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClerkScheduleRuleRequest;
use App\Models\ClerkCalendarColor;
use App\Models\ClerkScheduleRule;
use App\Services\ClerkScheduleDates;
use App\Services\ClerkScheduleGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClerkScheduleRuleController extends Controller
{
    use ResolvesContextCompany;

    public function index()
    {
        return Inertia::render('Clerk/Calendar/ScheduleRules/Index', [
            'rules' => ClerkScheduleRule::where('company_id', $this->companyId())->latest('id')->get(),
        ]);
    }

    public function create()
    {
        return $this->form();
    }

    public function edit(ClerkScheduleRule $scheduleRule)
    {
        $this->checkCompany($scheduleRule);

        return $this->form($scheduleRule);
    }

    private function form(?ClerkScheduleRule $rule = null)
    {
        return Inertia::render('Clerk/Calendar/ScheduleRules/Form', [
            'scheduleRule' => $rule,
            'colorSettings' => ClerkCalendarColor::where('company_id', $this->companyId())->orderBy('sort_order')->get(['color_key', 'label']),
            'today' => now('Asia/Tokyo')->toDateString(),
        ]);
    }

    public function preview(ClerkScheduleRuleRequest $request, ClerkScheduleDates $dates)
    {
        $this->companyId();
        $rule = new ClerkScheduleRule($request->payload());
        $from = max(now('Asia/Tokyo')->toDateString(), $rule->starts_on->format('Y-m-d'));

        return response()->json(['dates' => array_slice($dates->between($rule, $from, '2100-12-31'), 0, 12)]);
    }

    public function store(ClerkScheduleRuleRequest $request, ClerkScheduleGenerator $generator)
    {
        DB::transaction(function () use ($request, $generator) {
            $rule = ClerkScheduleRule::create($request->payload() + ['company_id' => $this->companyId(), 'created_by' => Auth::id()]);
            $generator->generate($rule->id);
        });

        return to_route('clerk.calendar.schedule_rules.index')->with('success', '予定日設定を登録しました。');
    }

    public function update(ClerkScheduleRuleRequest $request, ClerkScheduleRule $scheduleRule, ClerkScheduleGenerator $generator)
    {
        $this->checkCompany($scheduleRule);
        $generator->changeRule($scheduleRule, $request->payload());

        return to_route('clerk.calendar.schedule_rules.index')->with('success', '予定日設定を更新しました。');
    }

    public function toggle(Request $request, ClerkScheduleRule $scheduleRule, ClerkScheduleGenerator $generator)
    {
        $this->checkCompany($scheduleRule);
        $data = $request->validate(['is_active' => 'required|boolean']);
        $generator->changeRule($scheduleRule, $data);

        return to_route('clerk.calendar.schedule_rules.index');
    }

    public function destroy(ClerkScheduleRule $scheduleRule, ClerkScheduleGenerator $generator)
    {
        $this->checkCompany($scheduleRule);
        $generator->changeRule($scheduleRule, [], true);

        return to_route('clerk.calendar.schedule_rules.index')->with('success', '予定日設定を削除しました。');
    }

    private function checkCompany(ClerkScheduleRule $rule): void
    {
        abort_unless((int) $rule->company_id === $this->companyId(), 404);
    }

    private function companyId(): int
    {
        $id = $this->contextCompanyId() ?? Auth::user()->company_id;
        abort_unless($id, 403, '会社を選択してください。');

        return (int) $id;
    }
}
