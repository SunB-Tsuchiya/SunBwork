<?php

namespace App\Support\SunA\GeneralAffairs;

use App\Models\SunA\GeneralAffairs\Nencho\StaffPermission;
use App\Models\User;

/**
 * 総務の社員情報・年末調整の権限判定。
 *  - SuperAdmin・管理者(admin): 社員情報（一般項目）の閲覧・編集と年末調整の管理は常に可。**マイナンバーだけは権限（can_view_my_number）が必要**
 *  - 総務・経理(clerk): 権限が未設定なら何も見られない（初期値オフ）
 *  - それ以外のロール: すべて不可
 */
class StaffAccess
{
    public function __construct(
        public bool $viewEmployees = false,
        public bool $editEmployees = false,
        public bool $viewMyNumber = false,
        public bool $manageNencho = false,
    ) {}

    public static function for(?User $user): self
    {
        if (! $user) {
            return new self();
        }

        $perm = StaffPermission::where('user_id', $user->id)->first();
        $myNumber = (bool) $perm?->can_view_my_number;

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return new self(true, true, $myNumber, true);
        }
        if ($user->isClerk()) {
            return new self((bool) $perm?->can_view_employees, (bool) $perm?->can_edit_employees, $myNumber, (bool) $perm?->can_manage_nencho);
        }

        return new self();
    }

    /** 能力名（view_employees など）で判定 */
    public function can(string $ability): bool
    {
        return match ($ability) {
            'view_employees' => $this->viewEmployees,
            'edit_employees' => $this->editEmployees,
            'view_my_number' => $this->viewMyNumber,
            'manage_nencho' => $this->manageNencho,
            default => false,
        };
    }

    public function toArray(): array
    {
        return [
            'viewEmployees' => $this->viewEmployees,
            'editEmployees' => $this->editEmployees,
            'viewMyNumber' => $this->viewMyNumber,
            'manageNencho' => $this->manageNencho,
        ];
    }
}
