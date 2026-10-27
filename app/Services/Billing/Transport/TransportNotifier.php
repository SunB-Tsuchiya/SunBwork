<?php

namespace App\Services\Billing\Transport;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\TransportExpense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** 交通費の申請・受理などを、「お知らせ」で知らせる（失敗しても業務処理は止めない）。未読バッジも年末調整のお知らせと同じ */
class TransportNotifier
{
    /**
     * 申請・再申請 → 同じ会社の総務・経理（Clerk）へ。
     *
     * @param bool $resubmit 差戻しのあとの再申請か（文面を分ける）
     */
    public function submitted(TransportExpense $expense, User $actor, bool $resubmit = false, ?string $comment = null): void
    {
        $verb = $resubmit ? '再申請しました' : '申請しました';
        $name = $expense->user->name;
        $body = $name . 'さんが' . self::monthLabel($expense) . 'の交通費（合計 ' . number_format($expense->total_amount) . ' 円）を' . $verb . '。';
        if ($resubmit) {
            $body .= "\n差戻しのあと、修正して再申請されました。";
        }
        if ($comment) {
            $body .= "\n\n申請者のコメント:\n" . $comment;
        }

        $this->send($actor, $this->managers($expense->company_id, $actor->id),
            "【交通費】{$name}さんが" . self::monthLabel($expense) . "を{$verb}",
            $body . "\n\n確認するには、「申請」→「交通費」を開いてください。",
            $expense->company_id,
        );
    }

    /**
     * 通知を受け取る管理側の人: 同じ会社の総務・経理（Clerk）だけ。Admin・SuperAdmin には送らない（操作はできる）。操作した本人は除く。
     */
    private function managers(?int $companyId, int $exceptUserId): array
    {
        return User::query()
            ->where('user_role', 'clerk')->where('company_id', $companyId)
            ->where('id', '!=', $exceptUserId)
            ->pluck('id')->all();
    }

    /** 受理・差戻し・請求済み → 申請者へ */
    public function reviewed(TransportExpense $expense, User $actor, string $action, ?string $comment): void
    {
        $verb = ['approve' => '受理されました', 'return' => '差戻しされました', 'bill' => '請求済みになりました'][$action] ?? null;
        if (!$verb || $expense->user_id === $actor->id) {
            return;
        }
        $body = self::monthLabel($expense) . 'の交通費が' . $verb . '。';
        if ($action === 'return') {
            $body .= "\n修正して、もう一度申請してください。";
        }
        if ($comment) {
            $body .= "\n\nコメント:\n" . $comment;
        }

        $this->send($actor, [$expense->user_id], '【交通費】' . self::monthLabel($expense) . 'が' . $verb, $body, $expense->company_id);
    }

    /** 期限後の申請の依頼 → 同じ会社の総務・経理へ */
    public function deadlineRequested(\App\Models\TransportDeadlineExtension $ext, User $applicant): void
    {
        $label = \App\Http\Controllers\Billing\Transport\DeadlineController::monthLabel($ext->billing_month);
        $this->send($applicant, $this->managers($ext->company_id, $applicant->id), "【交通費】{$applicant->name}さんが、{$label}の期限後の申請を依頼しました",
            "{$applicant->name}さんが、{$label}の交通費について、期限（月末）を過ぎているため、期限後の申請を依頼しました。\n\n理由:\n{$ext->reason}\n\n許可・却下するには、「申請」→「交通費」を開いてください。",
            $ext->company_id);
    }

    /** 期限の解除（許可） → 申請者へ */
    public function deadlineGranted(\App\Models\TransportDeadlineExtension $ext, User $actor): void
    {
        $label = \App\Http\Controllers\Billing\Transport\DeadlineController::monthLabel($ext->billing_month);
        $body = "{$label}の交通費は、期限（月末）を過ぎていますが、総務・経理が期限を解除しました。\n期限後でも、保存・申請できます。「申請」→「交通費」から入力してください。";
        if ($ext->decision_comment) {
            $body .= "\n\nコメント:\n" . $ext->decision_comment;
        } elseif ($ext->reason && !$ext->requested_at) {
            $body .= "\n\n理由:\n" . $ext->reason;   // 総務・経理が、依頼なしで直接許可した場合の理由
        }
        $this->send($actor, [$ext->user_id], "【交通費】{$label}の期限が解除されました", $body, $ext->company_id);
    }

    /** 期限後の申請の依頼の却下 → 申請者へ */
    public function deadlineRejected(\App\Models\TransportDeadlineExtension $ext, User $actor): void
    {
        $label = \App\Http\Controllers\Billing\Transport\DeadlineController::monthLabel($ext->billing_month);
        $body = "{$label}の交通費の、期限後の申請の依頼は、見送られました。";
        if ($ext->decision_comment) {
            $body .= "\n\nコメント:\n" . $ext->decision_comment;
        }
        $this->send($actor, [$ext->user_id], "【交通費】{$label}の期限後の申請の依頼が、見送られました", $body, $ext->company_id);
    }

    private function send(User $from, array $toUserIds, string $title, string $content, ?int $companyId): void
    {
        $toUserIds = array_values(array_unique($toUserIds));
        if (!$toUserIds) {
            return;
        }
        try {
            DB::transaction(function () use ($from, $toUserIds, $title, $content, $companyId) {
                $announcement = Announcement::create([
                    'sender_id'         => $from->id,
                    'target_type'       => 'individual',
                    'title'             => $title,
                    'content'           => $content,
                    'target_company_id' => $companyId,
                    'status'            => 'sent',
                ]);
                AnnouncementRecipient::upsert(array_map(fn ($uid) => [
                    'announcement_id' => $announcement->id, 'user_id' => $uid, 'created_at' => now(), 'updated_at' => now(),
                ], $toUserIds), ['announcement_id', 'user_id'], ['updated_at']);
            });
        } catch (\Throwable $e) {
            logger()->warning('TransportNotifier: 通知の送信に失敗しました', ['error' => $e->getMessage()]);
        }
    }

    private static function monthLabel(TransportExpense $expense): string
    {
        [$y, $m] = array_map('intval', explode('-', $expense->billing_month));
        return "{$y}年{$m}月分";
    }
}
