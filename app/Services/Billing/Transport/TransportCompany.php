<?php

namespace App\Services\Billing\Transport;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;

/** 交通費で扱う「会社」と、その会社の部門（請求先）の解決 */
class TransportCompany
{
    /**
     * 申請の会社 ID。SuperAdmin は切り替え中の会社（未選択なら自分の所属）、一般ユーザーは自分の所属会社。
     */
    public static function idFor(User $user): ?int
    {
        if ($user->isSuperAdmin() && session('superadmin_context.company_id')) {
            return (int) session('superadmin_context.company_id');
        }
        return $user->company_id ?? $user->home_company_id;
    }

    /** その会社に登録された有効な部署（並び順どおり）。会社が決まらなければ空 */
    public static function departments(?int $companyId): Collection
    {
        if (!$companyId) {
            return collect();
        }
        return Department::where('company_id', $companyId)->where('active', true)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
    }

    /** サン・ブレーン様式（部門コードの数字つき）の用紙を使う会社か */
    public static function usesNumberedForm(?Company $company): bool
    {
        return $company?->company_type === 'sunbrain';
    }

    /** 管理画面で絞り込む会社 ID。SuperAdmin は切り替え中の会社（未選択なら null = 全社）、他は自分の所属会社 */
    public static function manageScopeId(User $user): ?int
    {
        if ($user->isSuperAdmin()) {
            $ctx = session('superadmin_context.company_id');
            return $ctx ? (int) $ctx : null;
        }
        return $user->company_id ?? $user->home_company_id;
    }

    /** 総務・経理（Clerk）・Admin・SuperAdmin か（交通費の受理などの管理操作ができる人） */
    public static function isManager(User $user): bool
    {
        return $user->isClerk() || $user->isAdmin() || $user->isSuperAdmin();
    }

    /** その申請を管理できるか（管理権限があり、同じ会社。SuperAdmin は全社） */
    public static function canManage(User $user, \App\Models\TransportExpense $expense): bool
    {
        if (!self::isManager($user)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        $expenseCompany = $expense->company_id ?? $expense->user?->company_id ?? $expense->user?->home_company_id;
        return $expenseCompany !== null && (int) $expenseCompany === (int) ($user->company_id ?? $user->home_company_id);
    }
}
