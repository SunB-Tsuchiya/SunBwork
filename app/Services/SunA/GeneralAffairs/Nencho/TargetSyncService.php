<?php

namespace App\Services\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Models\SunA\GeneralAffairs\Nencho\Target;
use App\Models\User;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;

/**
 * 受付の対象者の自動登録・取り込み直し。
 * 対象: 同じ会社の 社員(regular)・契約社員(contract)。SuperAdmin も所属会社（company_id）の社員として含む。派遣・業務委託は対象外。
 * 取り込み直しは「まだ対象者テーブルにいない人」を追加するだけ（入力を始めた人・手動で除外した人は変更しない）。
 */
class TargetSyncService
{
    /** 新しく追加した人数を返す */
    public function sync(Campaign $campaign): int
    {
        $existing = Target::where('campaign_id', $campaign->id)->pluck('user_id')->all();

        $users = User::where('company_id', $campaign->company_id)
            ->whereIn('employment_type', NenchoRules::TARGET_EMPLOYMENT_TYPES)
            ->whereNotIn('id', $existing)
            ->get(['id', 'name']);

        foreach ($users as $user) {
            Target::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'user_name' => $user->name]);
        }

        return $users->count();
    }
}
