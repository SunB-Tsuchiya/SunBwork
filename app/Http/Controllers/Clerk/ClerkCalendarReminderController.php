<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClerkCalendarReminderRequest;
use App\Models\ClerkCalendarReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClerkCalendarReminderController extends Controller
{
    use ResolvesContextCompany;

    public function index()
    {
        return Inertia::render('Clerk/Calendar/Reminders/Index', [
            'reminders' => ClerkCalendarReminder::forCompany($this->companyId())
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->get(),
            'today' => now('Asia/Tokyo')->toDateString(),
        ]);
    }

    public function create()
    {
        return $this->form();
    }

    public function store(ClerkCalendarReminderRequest $request)
    {
        ClerkCalendarReminder::create($request->validated() + [
            'company_id' => $this->companyId(),
            'created_by' => Auth::id(),
        ]);

        return to_route('clerk.reminders.index')->with('success', 'リマインダーを登録しました。');
    }

    public function edit(ClerkCalendarReminder $reminder)
    {
        $this->checkCompany($reminder);

        return $this->form($reminder);
    }

    public function update(ClerkCalendarReminderRequest $request, ClerkCalendarReminder $reminder)
    {
        $this->checkCompany($reminder);
        $reminder->update($request->validated());

        return to_route('clerk.reminders.index')->with('success', 'リマインダーを更新しました。');
    }

    public function toggle(Request $request, ClerkCalendarReminder $reminder)
    {
        $this->checkCompany($reminder);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $reminder->update($data);

        return to_route('clerk.reminders.index')->with('success', $data['is_active'] ? 'リマインダーを再開しました。' : 'リマインダーを停止しました。');
    }

    public function destroy(ClerkCalendarReminder $reminder)
    {
        $this->checkCompany($reminder);
        $reminder->delete();

        return to_route('clerk.reminders.index')->with('success', 'リマインダーを削除しました。');
    }

    private function form(?ClerkCalendarReminder $reminder = null)
    {
        return Inertia::render('Clerk/Calendar/Reminders/Form', [
            'reminder' => $reminder,
            'today' => now('Asia/Tokyo')->toDateString(),
        ]);
    }

    private function checkCompany(ClerkCalendarReminder $reminder): void
    {
        abort_unless((int) $reminder->company_id === $this->companyId(), 404);
    }

    private function companyId(): int
    {
        $id = $this->contextCompanyId() ?? Auth::user()->company_id;
        abort_unless($id, 403, '会社を選択してください。');

        return (int) $id;
    }
}
