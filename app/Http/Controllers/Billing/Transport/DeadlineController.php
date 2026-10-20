<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportDeadlineExtension;
use App\Models\User;
use App\Services\Billing\Transport\TransportCompany;
use App\Services\Billing\Transport\TransportDeadline;
use App\Services\Billing\Transport\TransportNotifier;
use Illuminate\Http\Request;

/** 総務・経理が、特定の人・特定の月について、期限（当月末日）を解除する（期限後でも申請してよいと許可する）。記録が残る */
class DeadlineController extends Controller
{
    public function store(Request $request, TransportNotifier $notifier)
    {
        $actor = $this->manager($request);
        $data = $request->validate([
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'billing_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'reason'        => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.required'       => '許可する人を選んでください。',
            'billing_month.required' => '対象の月を選んでください。',
            'billing_month.regex'    => '対象の月を選んでください。',
        ]);

        $target = User::findOrFail($data['user_id']);
        abort_unless($this->sameCompany($actor, $target), 403);

        if (!TransportDeadline::passed($data['billing_month'])) {
            return back()->withErrors(['billing_month' => 'この月は、まだ期限内です（解除は、期限を過ぎた月だけです）。']);
        }

        if (!TransportDeadline::requestable($data['billing_month'])) {
            return back()->withErrors(['billing_month' => TransportDeadline::notRequestableMessage()]);
        }

        $ext = TransportDeadlineExtension::updateOrCreate(
            ['user_id' => $target->id, 'billing_month' => $data['billing_month']],
            ['company_id' => $target->company_id ?? $target->home_company_id, 'status' => TransportDeadlineExtension::STATUS_GRANTED,
                'granted_by' => $actor->id, 'reason' => $data['reason'] ?? null, 'decided_at' => now(), 'decision_comment' => null],
        );
        $notifier->deadlineGranted($ext, $actor);

        return back()->with('success', $target->name . ' さんの ' . self::monthLabel($data['billing_month']) . 'の期限を解除しました（本人に、お知らせで通知しました）。');
    }

    /** 依頼を許可する（期限を解除） */
    public function approve(Request $request, TransportDeadlineExtension $extension, TransportNotifier $notifier)
    {
        return $this->decide($request, $extension, TransportDeadlineExtension::STATUS_GRANTED, $notifier);
    }

    /** 依頼を却下する */
    public function reject(Request $request, TransportDeadlineExtension $extension, TransportNotifier $notifier)
    {
        return $this->decide($request, $extension, TransportDeadlineExtension::STATUS_REJECTED, $notifier);
    }

    private function decide(Request $request, TransportDeadlineExtension $extension, string $status, TransportNotifier $notifier)
    {
        $actor = $this->manager($request);
        abort_unless($this->sameCompany($actor, $extension->user), 403);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:255']]);
        if ($extension->status !== TransportDeadlineExtension::STATUS_REQUESTED) {
            return back()->withErrors(['action' => '他の操作で、状態が変わっています。画面を更新して確認してください。']);
        }

        $extension->update(['status' => $status, 'granted_by' => $actor->id, 'decided_at' => now(), 'decision_comment' => $data['comment'] ?? null]);
        $status === TransportDeadlineExtension::STATUS_GRANTED ? $notifier->deadlineGranted($extension, $actor) : $notifier->deadlineRejected($extension, $actor);

        $name = $extension->user?->name;
        return back()->with('success', $status === TransportDeadlineExtension::STATUS_GRANTED
            ? "{$name} さんの " . self::monthLabel($extension->billing_month) . 'の依頼を許可しました（本人に、お知らせで通知しました）。'
            : "{$name} さんの " . self::monthLabel($extension->billing_month) . 'の依頼を却下しました（本人に、お知らせで通知しました）。');
    }

    public function destroy(Request $request, TransportDeadlineExtension $extension)
    {
        $actor = $this->manager($request);
        abort_unless($this->sameCompany($actor, $extension->user), 403);
        $extension->delete();

        return back()->with('success', '期限の解除を取り消しました。');
    }

    public static function monthLabel(string $ym): string
    {
        [$y, $m] = array_map('intval', explode('-', $ym));
        return "{$y}年{$m}月分";
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless(TransportCompany::isManager($user), 403);
        return $user;
    }

    /** 操作する人と対象の人が、同じ会社か（SuperAdmin は全社） */
    private function sameCompany(User $actor, ?User $target): bool
    {
        if (!$target) {
            return false;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }
        $mine = $actor->company_id ?? $actor->home_company_id;
        $theirs = $target->company_id ?? $target->home_company_id;
        return $mine !== null && (int) $mine === (int) $theirs;
    }
}
