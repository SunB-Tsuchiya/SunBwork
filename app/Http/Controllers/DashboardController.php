<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Diary;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Eager load related models so Vue pages can safely access them without extra queries
        $user = Auth::user()->load([
            'company',
            'department',
            'assignment',
            'teams.company',
            'teams.department',
            'currentTeam.company',
            'currentTeam.department',
        ]);

        // Build a simple current_team payload (company/department names included) for the frontend
        $currentTeam = $user->currentTeam;
        if ($currentTeam) {
            $user->current_team = [
                'id' => $currentTeam->id,
                'name' => $currentTeam->name,
                'team_type' => $currentTeam->team_type,
                'company_name' => $currentTeam->company->name ?? null,
                'department_name' => $currentTeam->department->name ?? null,
            ];
        } else {
            $user->current_team = null;
        }

        // available_teams: include loaded teams (with company/department relations)
        $user->available_teams = $user->teams->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'team_type' => $t->team_type,
                'company' => $t->company ? ['id' => $t->company->id, 'name' => $t->company->name] : null,
                'department' => $t->department ? ['id' => $t->department->id, 'name' => $t->department->name] : null,
            ];
        })->toArray();

        // URLパスでVueコンポーネントを振り分け
        $path = $request->path();
        if (str_starts_with($path, 'superadmin/')) {
            $component = 'SuperAdmin/Dashboard';
        } elseif (str_starts_with($path, 'admin/')) {
            $component = 'Admin/Dashboard';
        } elseif (str_starts_with($path, 'leader/')) {
            $component = 'Leader/Dashboard';
        } elseif (str_starts_with($path, 'coordinator/')) {
            $component = 'Coordinator/Dashboard';
        } elseif (str_starts_with($path, 'user/')) {
            $component = 'Dashboard';
        } else {
            // ログイン直後など: user_roleで自動リダイレクト
            if ($user->user_role === 'superadmin') {
                return redirect()->route('superadmin.dashboard');
            } elseif ($user->user_role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->user_role === 'leader') {
                return redirect()->route('leader.dashboard');
            } elseif ($user->user_role === 'coordinator') {
                return redirect()->route('coordinator.dashboard');
            } else {
                $component = 'Dashboard';
            }
        }

        return Inertia::render($component, [
            'user' => $user,
        ]);
    }
}
