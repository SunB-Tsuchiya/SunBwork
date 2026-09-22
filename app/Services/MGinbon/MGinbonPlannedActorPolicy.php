<?php

namespace App\Services\MGinbon;

class MGinbonPlannedActorPolicy
{
    /**
     * 別ユーザーまたは外注先の仮担当を、自己登録で置き換える場合だけ確認する。
     */
    public function requiresReplacementConfirmation(iterable $plannedPackages, int $registeringUserId): bool
    {
        foreach ($plannedPackages as $package) {
            if ($package->subcontractor_id !== null || (int) ($package->user_id ?? 0) !== $registeringUserId) {
                return true;
            }
        }

        return false;
    }
}
