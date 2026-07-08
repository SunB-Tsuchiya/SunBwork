<?php

namespace App\Http\Controllers\Prepress\Concerns;

use App\Models\Department;

trait AuthorizesPrepress
{
    protected function authorizePrepress($user, bool $allowOperationalRoles = false): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isAdmin()) {
            if ($this->prepressDepartmentForCompany($user->company_id)) {
                return;
            }

            abort(403, 'Prepress エリアは同じ会社のAdminのみアクセスできます。');
        }

        if ($allowOperationalRoles && ($user->isCoordinator() || $user->isLeader() || $user->isClerk())) {
            return;
        }

        if (!$this->userBelongsToPrepressDepartment($user)) {
            abort(403, 'Prepress エリアは製版部署のみアクセスできます。');
        }
    }

    protected function getPrepressDeptId($user): ?int
    {
        if ($user->isSuperAdmin()) {
            return $this->firstPrepressDepartment()?->id;
        }

        if ($user->isAdmin()) {
            return $this->prepressDepartmentForCompany($user->company_id)?->id;
        }

        return $this->userBelongsToPrepressDepartment($user) ? $user->department_id : null;
    }

    protected function firstPrepressDepartment(): ?Department
    {
        return Department::where('module', 'prepress')
            ->orWhere('name', '製版')
            ->orderByRaw("CASE WHEN module = 'prepress' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    protected function prepressDepartmentForCompany(?int $companyId): ?Department
    {
        if (!$companyId) {
            return null;
        }

        return Department::where('company_id', $companyId)
            ->where(function ($query) {
                $query->where('module', 'prepress')
                    ->orWhere('name', '製版');
            })
            ->orderByRaw("CASE WHEN module = 'prepress' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    protected function userBelongsToPrepressDepartment($user): bool
    {
        $department = $user->relationLoaded('department')
            ? $user->department
            : $user->department()->first();

        return (bool) $department
            && ($department->module === 'prepress' || $department->name === '製版');
    }
}
