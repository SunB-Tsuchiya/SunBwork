<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
// Log used for debugging during investigation (removed when cleanup)
use App\Models\Diary;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use Illuminate\Support\Facades\Schema;

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
            $diaryQuery = Diary::where('user_id', $user->id);
            if (Schema::hasColumn('diaries', 'date')) {
                $diaryQuery->where('date', '>=', $diary_from)->where('date', '<=', $diary_to);
            }
            $diaries = $diaryQuery->get();
            // Do not select a physical `date` column because some DBs may not have it.
            // Compute date from `start` via model accessor if needed.
            $eventQuery = Event::where('user_id', $user->id);
            $select = ['id', 'title'];
            if (Schema::hasColumn('events', 'start')) {
                $eventQuery->where('start', '>=', $event_from)->where('start', '<=', $event_to);
                $select[] = 'start';
            }
            if (Schema::hasColumn('events', 'end')) {
                $select[] = 'end';
            }
            if (Schema::hasColumn('events', 'description')) {
                $select[] = 'description';
            }
            $events = $eventQuery->get($select);

            // load assigned jobs that the user accepted or that are marked assigned
            $jobs = ProjectJobAssignment::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->where('accepted', true)->orWhere('assigned', true);
                })
                ->with('projectJob')
                ->get()
                ->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'title' => $a->title ?: ($a->projectJob ? $a->projectJob->name : '無題'),
                        'details' => $a->detail ?? ($a->projectJob ? $a->projectJob->detail : null),
                        'preferred_date' => $a->desired_start_date ? $a->desired_start_date->format('Y-m-d') : null,
                        'scheduled_at' => null,
                        'assigned_at' => $a->created_at,
                    ];
                });
        }
        // Debug logging removed after investigation
        return Inertia::render('Calendar', [
            'user' => $user,
            'diaries' => $diaries,
            'events' => $events,
            'jobs' => $jobs ?? [],
        ]);
    }
}
