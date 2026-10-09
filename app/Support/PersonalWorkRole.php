<?php

namespace App\Support;

use App\Models\User;

class PersonalWorkRole
{
    private const LABELS = [
        'shinko' => '進行管理',
        'operator' => 'オペレーター',
        'kousei' => '校正',
        'seisaku' => '制作',
        'eigyo' => '営業',
        'jimu' => '事務',
        'other' => 'その他',
    ];

    public static function forUser(User $user): array
    {
        $assignmentCode = $user->assignment?->code;
        $code = match (true) {
            in_array($assignmentCode, ['shinko', 'operator', 'kousei', 'seisaku', 'eigyo'], true) => $assignmentCode,
            $user->user_role === 'clerk' => 'jimu',
            $user->user_role === 'coordinator' => 'shinko',
            $user->user_role === 'proof_coordinator' => 'kousei',
            default => 'other',
        };

        return ['code' => $code, 'label' => self::LABELS[$code]];
    }
}
