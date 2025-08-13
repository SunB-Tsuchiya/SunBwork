<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Diary;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $company = $user->company_id ? \App\Models\Company::find($user->company_id) : null;
        $department = $user->department_id ? \App\Models\Department::find($user->department_id) : null;
        $part = $user->role_id ? \App\Models\Role::find($user->role_id) : null;
        $current_team = $user->current_team_id ? \App\Models\Team::find($user->current_team_id) : null;

        $userArray = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_role' => $user->user_role,
            'current_team' => $current_team,
            'available_team' => $user->teams ? $user->teams->map(fn($t) => $t->toArray())->toArray() : [],
            'company' => $company,
            'department' => $department,
            'part' => $part,
        ];
        
        $user = Auth::user();
        $user->current_team = $user->currentTeam; // JetstreamのcurrentTeamリレーション
        $user->available_teams = $user->teams; // Eloquentリレーション
        $user->company = $company;
        $user->department = $department;
        $user->part = $part;
        
        $diaries = [];
        $events = [];
        if ($user) {
            $diary_from = now()->subDays(20)->startOfDay();
            $diary_to = now()->endOfDay();
            $event_from = now()->startOfMonth();
            $event_to = now()->endOfMonth();
            $diaries = Diary::where('user_id', $user->id)
                ->where('date', '>=', $diary_from)
                ->where('date', '<=', $diary_to)
                ->get();
            $events = \App\Models\Event::where('user_id', $user->id)
                ->where('start', '>=', $event_from)
                ->where('start', '<=', $event_to)
                ->get(['id', 'title', 'start', 'end', 'date']);
        }

        return Inertia::render('Dashboard', [
            'user' => $user,
            'diaries' => $diaries,
            'events' => $events,
        ]);
    }
}
