<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        
        $user = Auth::user();
        $user->current_team = $user->currentTeam; // JetstreamのcurrentTeamリレーション
        $user->available_teams = $user->teams; // Eloquentリレーション
        return Inertia::render('Admin/Dashboard', [
            'user' => $user,
        ]);
    }
}
