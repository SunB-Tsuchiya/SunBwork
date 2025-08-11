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

        $roles = \App\Models\Role::all();
        return Inertia::render('Dashboard', [
            'user' => $user,
            'diaries' => $diaries,
            'events' => $events,
            'roles' => $roles,
        ]);
    }
}
