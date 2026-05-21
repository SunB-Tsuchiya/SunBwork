<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class PrepressDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('department');

        $this->authorizePrepress($user);

        $departments = [];
        try {
            $departments = Department::where('company_id', $user->company_id)
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->toArray();
        } catch (\Throwable $e) {
            \Log::error('PrepressDashboard departments error: ' . $e->getMessage());
        }

        return inertia('Prepress/Dashboard', [
            'user'             => $user,
            'departments'      => $departments,
            'userDepartmentId' => $user->department_id,
        ]);
    }

    protected function authorizePrepress($user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($user->isAdmin()) {
            $prepressCompanyId = \App\Models\Department::where('name', '製版')->value('company_id');
            if (!$prepressCompanyId || $user->company_id == $prepressCompanyId) {
                return;
            }
            abort(403, 'Prepress エリアは同じ会社のAdminのみアクセスできます。');
        }
        if (!$user->department || $user->department->name !== '製版') {
            abort(403, 'Prepress エリアは製版部署のみアクセスできます。');
        }
    }
}
