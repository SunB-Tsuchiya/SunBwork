<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function search(Request $request)
    {
        $q = $request->query('q', '');
        $companyId = $request->query('company_id');

        $query = User::query()->with(['company', 'department', 'assignment']);

        if ($q) {
            $query->where(function ($r) use ($q) {
                $r->where('name', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        // if company_id provided, filter by it unless current user is superadmin
        $user = $request->user();
        if ($companyId && !($user && $user->isSuperAdmin())) {
            $query->where('company_id', $companyId);
        }

        // superadmin can see all users
        if (!($user && $user->isSuperAdmin()) && !$companyId) {
            // default: restrict to same company as current user if available
            if ($user && $user->company_id) {
                $query->where('company_id', $user->company_id);
            }
        }

        $users = $query->limit(50)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'company_name' => $u->company?->name,
                'department_name' => $u->department?->name,
                'role_name' => $u->user_role,
                'assignment_name' => $u->assignment?->name,
            ];
        });

        return response()->json($users);
    }
}
