<?php

namespace App\Support\SunA\Sales;

use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SunA\Sales\SunaCompanyContext;

/**
 * サンエー印刷 売上レポートの利用権限の判定。
 *
 * - 会社: SuperAdmin はセッションの会社切替（superadmin_context）、それ以外は自分の所属会社。売上レポートの対象会社
 *   （サンエー印刷のような一般会社）でなければ、すべて不可
 * - 閲覧/取込: 管理者が「利用メンバー設定」で個別に許可した人だけ（既定は全員OFF。Admin も同じ）。
 *   SuperAdmin は、開発・確認のため、対象会社であれば常に可
 * - 許可設定の管理: Admin と SuperAdmin（対象会社のコンテキストで）
 */
class ReportAccess
{
    /** その人の「いまの会社」ID（SuperAdmin は会社切替の値）。未設定は null */
    public static function companyIdFor(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        return $user->isSuperAdmin() ? session('superadmin_context.company_id') : $user->company_id;
    }

    /** 売上レポートの対象会社にいるか（サンエー印刷のような一般会社） */
    public static function inEligibleCompany(?User $user): bool
    {
        $companyId = self::companyIdFor($user);

        return $companyId !== null && SunaCompanyContext::isEligible((int) $companyId);
    }

    public static function canView(?User $user): bool
    {
        return self::decide($user, 'enabled');
    }

    public static function canImport(?User $user): bool
    {
        return self::decide($user, 'can_import');
    }

    /** 許可設定を管理できるか（Admin / SuperAdmin。対象会社のコンテキストで） */
    public static function canManage(?User $user): bool
    {
        return $user !== null && ($user->isSuperAdmin() || $user->isAdmin()) && self::inEligibleCompany($user);
    }

    private static function decide(?User $user, string $column): bool
    {
        if ($user === null || ! self::inEligibleCompany($user)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = ReportPermission::where('user_id', $user->id)->first();

        // 取込は、閲覧も許可されていることが前提（閲覧OFFで取込ONの状態は、閲覧OFF扱い）
        return $permission !== null && $permission->enabled && ($column === 'enabled' || (bool) $permission->{$column});
    }
}
