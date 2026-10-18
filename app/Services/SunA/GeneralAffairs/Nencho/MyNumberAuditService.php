<?php

namespace App\Services\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\MyNumberAccessLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * マイナンバーの閲覧・登録・変更の記録（だれが・いつ・だれの番号か）
 */
class MyNumberAuditService
{
    public function record(User $viewer, EmployeeProfile $profile, string $action, ?Request $request = null): void
    {
        MyNumberAccessLog::create([
            'viewer_user_id' => $viewer->id,
            'viewer_name' => $viewer->name,
            'employee_profile_id' => $profile->id,
            'employee_name' => $profile->name ?: ($profile->user?->name ?? '（氏名未登録）'),
            'action' => $action,
            'ip_address' => $request?->ip(),
            'accessed_at' => now(),
        ]);
    }
}
