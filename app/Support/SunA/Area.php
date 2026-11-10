<?php

namespace App\Support\SunA;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;

/**
 * サンエー印刷（一般会社）の「エリア」（総務／経理／営業）の判定（SUNAROLE_PLAN1.md §4.2）。
 *
 * サンエー印刷では、ロールの上下ではなく、**所属部署のエリア**で見える範囲を決める。
 *  - 部署の `area` が soumu / keiri / sales のどれか（NULL = エリアなし = ユーザーの画面だけ）
 *  - 管理者も自分のエリアだけ（他部署のエリアは見えない）。SuperAdmin は全エリア
 *  - 一般会社以外（サン・ブレーン、会社未設定）では常に「判定なし」＝従来の仕組みのまま（呼び出し側が isGeneral で分岐する）
 */
class Area
{
    public const SOUMU = 'soumu';

    public const KEIRI = 'keiri';

    public const SALES = 'sales';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [self::SOUMU, self::KEIRI, self::SALES];
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return [
            ['value' => self::SOUMU, 'label' => '総務'],
            ['value' => self::KEIRI, 'label' => '経理'],
            ['value' => self::SALES, 'label' => '営業'],
        ];
    }

    public static function label(?string $area): string
    {
        return collect(self::options())->firstWhere('value', $area)['label'] ?? 'なし';
    }

    /** その人の「いまの会社」がサンエー印刷（一般会社）か。SuperAdmin はセッションの会社切替の値 */
    public static function isGeneral(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        $companyId = $user->isSuperAdmin() ? session('superadmin_context.company_id') : $user->company_id;

        return $companyId !== null && Company::find($companyId)?->company_type === 'general';
    }

    /** 所属部署のエリア（soumu/keiri/sales）。エリアなし・部署なしは null。SuperAdmin は null（全エリア可なので canEnter を使う） */
    public static function of(?User $user): ?string
    {
        if ($user === null || $user->department_id === null || $user->isSuperAdmin()) {
            return null;
        }
        $area = Department::find($user->department_id)?->area;

        return in_array($area, self::all(), true) ? $area : null;
    }

    /** そのエリアに入れるか（SuperAdmin は全エリア。それ以外は自分のエリアだけ。管理者も同じ） */
    public static function canEnter(?User $user, string $area): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin() || self::of($user) === $area;
    }

    /** 総務・経理（Clerk 系の画面）に入れるか */
    public static function canEnterClerk(?User $user): bool
    {
        return self::canEnter($user, self::SOUMU) || self::canEnter($user, self::KEIRI);
    }

    /** 総務／経理のどちらの画面か（Clerk 系）。SuperAdmin は null（呼び出し側でセッションの切替を使う） */
    public static function clerkKind(?User $user): ?string
    {
        $area = self::of($user);

        return in_array($area, [self::SOUMU, self::KEIRI], true) ? $area : null;
    }

    /** 管理者の画面に入れるか（管理者と SuperAdmin） */
    public static function canUseAdminArea(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isSuperAdmin());
    }
}
