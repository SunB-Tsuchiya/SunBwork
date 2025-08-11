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
            Log::info('DashboardController@index user_id', ['user_id' => $user->id]);
            Log::info('DashboardController@index diary date range', ['from' => $diary_from, 'to' => $diary_to]);
            Log::info('DashboardController@index event date range', ['from' => $event_from, 'to' => $event_to]);
            $diaries = Diary::where('user_id', $user->id)
                ->where('date', '>=', $diary_from)
                ->where('date', '<=', $diary_to)
                ->get();
            Log::info('DashboardController@index diaries count', ['count' => $diaries->count(), 'data' => $diaries]);

            $events = \App\Models\Event::where('user_id', $user->id)
                ->where('start', '>=', $event_from)
                ->where('start', '<=', $event_to)
                ->get(['id', 'title', 'start', 'end', 'date']);
            Log::info('DashboardController@index events count', ['count' => $events->count(), 'data' => $events]);
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
