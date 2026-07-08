<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Prepress\Concerns\AuthorizesPrepress;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class PrepressDashboardController extends Controller
{
    use AuthorizesPrepress;

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

}
