<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportDeadlineExtension;
use App\Services\Billing\Transport\TransportDeadline;
use App\Services\Billing\Transport\TransportNotifier;
use Illuminate\Http\Request;

/** 申請者が、期限（当月末日）を過ぎた月の申請を、総務・経理に依頼する（総務・経理は、依頼の一覧から許可・却下する） */
class DeadlineRequestController extends Controller
{
    public function store(Request $request, TransportNotifier $notifier)
    {
        $user = $request->user();
        $data = $request->validate([
            'billing_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'reason'        => ['required', 'string', 'max:255'],
        ], [
            'billing_month.required' => '対象の月が指定されていません。',
            'billing_month.regex'    => '対象の月が指定されていません。',
            'reason.required'        => '依頼の理由を入力してください。',
        ]);

        if (!TransportDeadline::passed($data['billing_month'])) {
            return back()->withErrors(['billing_month' => 'この月は、まだ期限内です。そのまま申請できます。']);
        }
        if (!TransportDeadline::requestable($data['billing_month'])) {
            return back()->withErrors(['billing_month' => TransportDeadline::notRequestableMessage()]);
        }
        $existing = TransportDeadlineExtension::where('user_id', $user->id)->where('billing_month', $data['billing_month'])->first();
        if ($existing?->status === TransportDeadlineExtension::STATUS_GRANTED) {
            return back()->withErrors(['billing_month' => 'この月は、すでに期限が解除されています。']);
        }

        // 依頼中・却下されたものがあれば、依頼し直す（同じ人・同じ月は 1 件）
        $ext = TransportDeadlineExtension::updateOrCreate(
            ['user_id' => $user->id, 'billing_month' => $data['billing_month']],
            [
                'company_id' => $user->company_id ?? $user->home_company_id, 'status' => TransportDeadlineExtension::STATUS_REQUESTED,
                'reason' => $data['reason'], 'requested_at' => now(),
                'granted_by' => null, 'decided_at' => null, 'decision_comment' => null,
            ],
        );
        $notifier->deadlineRequested($ext, $user);

        return back()->with('success', '期限後の申請を、総務・経理に依頼しました。確認をお待ちください（結果は、お知らせでお知らせします）。');
    }

    /** 依頼中の依頼を、取り下げる（本人の分だけ） */
    public function destroy(Request $request, TransportDeadlineExtension $extension)
    {
        abort_unless($extension->user_id === $request->user()->id, 403);
        abort_unless($extension->status === TransportDeadlineExtension::STATUS_REQUESTED, 422, '依頼中のものだけ、取り下げられます。');
        $extension->delete();

        return back()->with('success', '依頼を取り下げました。');
    }
}
