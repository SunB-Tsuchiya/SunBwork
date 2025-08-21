<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Diary;
use App\Models\Event;

class CalendarController extends Controller
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
            // Do not select a physical `date` column because some DBs may not have it.
            // Compute date from `start` via model accessor if needed.
            $events = Event::where('user_id', $user->id)
                ->where('start', '>=', $event_from)
                ->where('start', '<=', $event_to)
                ->get(['id', 'title', 'start', 'end', 'description']);
        }
        return Inertia::render('Calendar', [
            'user' => $user,
            'diaries' => $diaries,
            'events' => $events,
        ]);
    }
}
