<?php

namespace App\Services\Billing\Transport;

use App\Models\TransportExpense;
use App\Models\TransportExpenseEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 交通費の状態遷移（下書き → 申請済み →（受理／差戻し）→ 請求済み）。
 * 操作の瞬間に状態を再確認し（他の人が先に操作していたら止める）、履歴を残し、通知を送る。
 */
class TransportExpenseWorkflow
{
    /** 操作 => [実行できる今の状態, 遷移後の状態] */
    private const TRANSITIONS = [
        'submit'    => [[TransportExpense::STATUS_DRAFT, TransportExpense::STATUS_RETURNED], TransportExpense::STATUS_SUBMITTED],
        'withdraw'  => [[TransportExpense::STATUS_SUBMITTED], TransportExpense::STATUS_DRAFT],
        'approve'   => [[TransportExpense::STATUS_SUBMITTED], TransportExpense::STATUS_APPROVED],
        'return'    => [[TransportExpense::STATUS_SUBMITTED], TransportExpense::STATUS_RETURNED],
        'bill'      => [[TransportExpense::STATUS_APPROVED], TransportExpense::STATUS_BILLED],
        'unapprove' => [[TransportExpense::STATUS_APPROVED], TransportExpense::STATUS_SUBMITTED],
        'unbill'    => [[TransportExpense::STATUS_BILLED], TransportExpense::STATUS_APPROVED],
    ];

    public function __construct(private TransportNotifier $notifier)
    {
    }

    /** 今の状態で、その操作ができるか（画面のボタン出し分け用） */
    public static function can(TransportExpense $expense, string $action): bool
    {
        return isset(self::TRANSITIONS[$action]) && in_array($expense->status, self::TRANSITIONS[$action][0], true);
    }

    public function apply(TransportExpense $expense, User $actor, string $action, ?string $comment = null): TransportExpense
    {
        abort_unless(isset(self::TRANSITIONS[$action]), 500, '未対応の操作です。');
        $comment = $comment !== null ? trim($comment) : null;
        $comment = $comment === '' ? null : $comment;

        if ($action === 'return' && $comment === null) {
            throw ValidationException::withMessages(['comment' => '差戻しの理由（コメント）を入力してください。']);
        }

        $fromStatus = null;
        $fresh = DB::transaction(function () use ($expense, $actor, $action, $comment, &$fromStatus) {
            $fresh = TransportExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            [$allowedFrom, $to] = self::TRANSITIONS[$action];
            if (!in_array($fresh->status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'action' => '他の操作で状態が変わっています（現在: ' . (TransportExpense::STATUS_LABELS[$fresh->status] ?? $fresh->status) . '）。画面を更新して確認してください。',
                ]);
            }
            $from = $fresh->status;
            $fromStatus = $from;

            $attrs = ['status' => $to];
            switch ($action) {
                case 'submit':
                    $attrs += ['submitted_at' => now(), 'billing_date' => now()->toDateString(),
                        'reviewed_by' => null, 'reviewed_at' => null, 'review_comment' => null, 'billed_at' => null];
                    break;
                case 'withdraw':
                    $attrs += ['submitted_at' => null];
                    break;
                case 'approve':
                case 'return':
                    $attrs += ['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_comment' => $comment];
                    break;
                case 'bill':
                    $attrs += ['billed_at' => now()];
                    break;
                case 'unapprove':
                    $attrs += ['reviewed_by' => null, 'reviewed_at' => null, 'review_comment' => null];
                    break;
                case 'unbill':
                    $attrs += ['billed_at' => null];
                    break;
            }
            $fresh->update($attrs);

            TransportExpenseEvent::create([
                'transport_expense_id' => $fresh->id,
                'user_id'              => $actor->id,
                'action'               => $action,
                'from_status'          => $from,
                'to_status'            => $to,
                'comment'              => $comment,
            ]);

            return $fresh;
        });

        // 通知（確定後）
        $fresh->loadMissing('user');
        if ($action === 'submit') {
            try {
                app(TransportFareStock::class)->recordFromExpense($fresh);   // 運賃マスタに無い経路を「未検査」として溜める
            } catch (\Throwable $e) {
                logger()->warning('TransportFareStock: 運賃マスタへの自動登録に失敗しました', ['error' => $e->getMessage()]);
            }
            $this->notifier->submitted($fresh, $actor, $fromStatus === TransportExpense::STATUS_RETURNED);   // 差戻しのあとなら「再申請」
        } else {
            $this->notifier->reviewed($fresh, $actor, $action, $comment);
        }

        return $fresh;
    }
}
