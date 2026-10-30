<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClerkCalendarReminderRequest;
use App\Models\ClerkCalendarReminder;
use App\Models\User;
use App\Services\AnnouncementBlocks;
use App\Services\HtmlSanitizer;
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
                ->withCount('users')
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
        $data = $this->prepare($request->validated());
        $userIds = $this->companyUserIds($data['user_ids'] ?? []);
        unset($data['user_ids']);

        $reminder = ClerkCalendarReminder::create($data + [
            'company_id' => $this->companyId(),
            'created_by' => Auth::id(),
        ]);
        $reminder->users()->sync($data['target_type'] === 'individual' ? $userIds : []);

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
        $data = $this->prepare($request->validated());
        $userIds = $this->companyUserIds($data['user_ids'] ?? []);
        unset($data['user_ids']);

        $reminder->update($data);
        $reminder->users()->sync($data['target_type'] === 'individual' ? $userIds : []);

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
            'reminder' => $reminder?->setAttribute('user_ids', $reminder->users()->pluck('users.id')->all()),
            'showBlocks' => AnnouncementBlocks::appliesTo(Auth::user()),
            'users' => User::with(['assignment', 'department'])->where('company_id', $this->companyId())
                ->whereNotNull('department_id')->ordered()->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'company_id' => $u->company_id,
                    'assignment_name' => $u->assignment?->name ?? '',
                    'employment_type' => $u->employment_type ?? 'regular',
                    'blocks' => AnnouncementBlocks::blocksOf($u),
                ]),
            'today' => now('Asia/Tokyo')->toDateString(),
        ]);
    }

    /** 本文を無害化する（文字色などの基本装飾のみ許可） */
    private function prepare(array $data): array
    {
        $data['content'] = app(HtmlSanitizer::class)->purifyRich($data['content']) ?? '';
        // 対象範囲はサン・ブレーン所属のときだけ有効（それ以外は全社）
        $data['target_block'] = AnnouncementBlocks::fromRequest(request(), Auth::user());

        return $data;
    }

    /** 宛先は自社ユーザーに限る */
    private function companyUserIds(array $ids): array
    {
        return User::where('company_id', $this->companyId())->whereIn('id', $ids)->pluck('id')->all();
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
