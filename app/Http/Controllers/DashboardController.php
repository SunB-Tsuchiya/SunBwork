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
        $user = Auth::user();
        $company = $user->company_id ? \App\Models\Company::find($user->company_id) : null;
        $department = $user->department_id ? \App\Models\Department::find($user->department_id) : null;
        $assignment = $user->assignment_id ? \App\Models\Assignment::find($user->assignment_id) : null;
        $user->current_team = $user->currentTeam;
        $user->available_teams = $user->teams;
        $user->company = $company;
        $user->department = $department;
        $user->assignment = $assignment;

        // URLパスでVueコンポーネントを振り分け
        $path = $request->path();
        if (str_starts_with($path, 'admin/')) {
            $component = 'Admin/Dashboard';
        } elseif (str_starts_with($path, 'leader/')) {
            $component = 'Leader/Dashboard';
        } elseif (str_starts_with($path, 'user/')) {
            $component = 'Dashboard';
        } else {
            // ログイン直後など: user_roleで自動リダイレクト
            if ($user->user_role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->user_role === 'leader') {
                return redirect()->route('leader.dashboard');
            } else {
                $component = 'Dashboard';
            }
        }

        return Inertia::render($component, [
            'user' => $user,
        ]);
    }
}
