<?php

namespace App\Services\Billing\Transport;

use App\Models\TransportDeadlineExtension;
use App\Models\TransportExpense;
use Illuminate\Support\Carbon;

/**
 * 交通費の期限: 当月末日（JST の 23:59:59）を過ぎたら、その月は、新規の保存・申請ができない（会社のルール）。
 *  - 差戻しされたものは、月末を過ぎても、直して再申請できる（総務・経理からの依頼のため）
 *  - 総務・経理が、特定の人・特定の月について、期限を解除できる
 *  - 総務・経理の受理・差戻し・請求済みの操作は、期限の影響を受けない
 */
class TransportDeadline
{
    /** その月の期限（月末の最後の瞬間。アプリのタイムゾーン = Asia/Tokyo） */
    public static function endOf(string $month): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $month . '-01')->endOfMonth();
    }

    public static function enforced(): bool
    {
        return (bool) config('transport.enforce_deadline', true);
    }

    /** 期限を過ぎているか */
    public static function passed(string $month): bool
    {
        return self::enforced() && now()->greaterThan(self::endOf($month));
    }

    /**
     * 期限後の申請を、依頼・許可できる月か（期限を過ぎていて、先月分まで。設定 request_months_back）。
     * それより前の月は、申請できない（支払いの対象外）。
     */
    public static function requestable(string $month): bool
    {
        $oldest = now()->startOfMonth()->subMonths(max(1, (int) config('transport.request_months_back', 1)))->format('Y-m');
        return self::passed($month) && $month >= $oldest;
    }

    public static function notRequestableMessage(): string
    {
        return '期限後の申請を依頼・許可できるのは、先月分までです。それより前の月は、申請できません（支払いの対象外です）。';
    }

    /** 総務・経理が、期限を解除しているか */
    public static function extended(int $userId, string $month): bool
    {
        return TransportDeadlineExtension::where('user_id', $userId)->where('billing_month', $month)
            ->where('status', TransportDeadlineExtension::STATUS_GRANTED)->exists();
    }

    /** その月に、新規の保存・申請ができるか（期限内、または解除されている） */
    public static function isOpen(int $userId, string $month): bool
    {
        return !self::passed($month) || self::extended($userId, $month);
    }

    /**
     * 申請者が、その月を編集（保存・申請）できるか。
     * 差戻しされたものは、期限後でも可。下書き・新規は、期限内か、解除されているとき。
     */
    public static function canApplicantEdit(int $userId, string $month, ?TransportExpense $expense): bool
    {
        if ($expense) {
            if ($expense->status === TransportExpense::STATUS_RETURNED) {
                return true;
            }
            return $expense->status === TransportExpense::STATUS_DRAFT && self::isOpen($userId, $month);
        }
        return self::isOpen($userId, $month);
    }

    public static function lockedMessage(string $month): string
    {
        [$y, $m] = array_map('intval', explode('-', $month));
        return "{$y}年{$m}月分は、期限（月末）を過ぎているため、保存・申請できません。やむを得ない場合は、総務・経理に、期限の解除を相談してください。";
    }
}
