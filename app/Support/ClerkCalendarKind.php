<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * いまのリクエストが、どの「クラーク系カレンダー」を操作するか（calendar_kind）を決める。
 * サンエー印刷は、総務・経理・営業がそれぞれ別のカレンダーと予定を持つ（SUNASALES_PLAN1.md §14）。
 *
 *  - 一般会社（company_type='general'。サンエー印刷）でなければ空文字 = 従来の、会社単位のカレンダー（サン・ブレーンは変わらない）
 *  - 営業のルート（suna.sales.*）なら 'sales'
 *  - クラークのルートなら: Clerk は所属部署名に「経理」を含むか（'keiri'）、含まなければ 'soumu'。
 *    Admin/SuperAdmin は、画面の総務/経理の切替（セッションの clerk_kind。既定は 'soumu'）
 */
class ClerkCalendarKind
{
    public const SOUMU = 'soumu';

    public const KEIRI = 'keiri';

    public const SALES = 'sales';

    /** 従来のカレンダー（サン・ブレーン）を表す値 */
    public const LEGACY = '';

    public static function forRequest(?Request $request = null): string
    {
        $request ??= request();
        $user = $request->user();
        if ($user === null) {
            return self::LEGACY;
        }

        $companyId = $user->isSuperAdmin() ? (session('superadmin_context.company_id') ?? $user->company_id) : $user->company_id;
        if ($companyId === null || Company::find($companyId)?->company_type !== 'general') {
            return self::LEGACY;
        }

        if ($request->routeIs('suna.sales.*')) {
            return self::SALES;
        }

        return self::clerkKind($user);
    }

    /** クラークのルートでの種類（総務/経理）。サンエー印刷（一般会社）でだけ使う */
    public static function clerkKind(User $user): string
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $kind = session('clerk_kind', self::SOUMU);

            return in_array($kind, [self::SOUMU, self::KEIRI], true) ? $kind : self::SOUMU;
        }

        return str_contains((string) Department::find($user->department_id)?->name, '経理') ? self::KEIRI : self::SOUMU;
    }
}
