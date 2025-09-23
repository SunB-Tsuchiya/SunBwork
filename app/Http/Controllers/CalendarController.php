<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            // Ensure we retrieve timestamp and body columns that the frontend expects.
            // Some schemas use `starts_at`/`ends_at`/`body` while older code used `start`/`end`/`description`.
            $eventQuery = Event::where('user_id', $user->id);
            $select = ['id', 'title'];
            if (Schema::hasColumn('events', 'starts_at')) {
                $eventQuery->where('starts_at', '>=', $event_from)->where('starts_at', '<=', $event_to);
                $select[] = 'starts_at';
            } elseif (Schema::hasColumn('events', 'start')) {
                $eventQuery->where('start', '>=', $event_from)->where('start', '<=', $event_to);
                $select[] = 'start';
            }

            if (Schema::hasColumn('events', 'ends_at')) {
                $select[] = 'ends_at';
            } elseif (Schema::hasColumn('events', 'end')) {
                $select[] = 'end';
            }

            if (Schema::hasColumn('events', 'body')) {
                $select[] = 'body';
            } elseif (Schema::hasColumn('events', 'description')) {
                $select[] = 'description';
            }

            // Include linkage columns if present so the frontend can detect linked assignments
            if (Schema::hasColumn('events', 'project_job_assignment_by_myself_id')) {
                $select[] = 'project_job_assignment_by_myself_id';
            }
            if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                $select[] = 'project_job_assignment_id';
            }

            $events = $eventQuery->get($select);

            // Map events to plain arrays and ensure linkage ids are present both top-level and in extendedProps
            $events = $events->map(function ($e) {
                // Convert to array first to capture appended attributes like start/end/description
                $arr = $e->toArray();
                return [
                    'id' => $e->id,
                    'title' => $e->title,
                    'start' => $e->start ?? ($arr['start'] ?? null),
                    'end' => $e->end ?? ($arr['end'] ?? null),
                    'allDay' => $arr['allDay'] ?? false,
                    'description' => $e->description ?? ($arr['description'] ?? null),
                    // keep server-provided color if present, but client may override for linked assignments
                    'color' => $arr['color'] ?? ($e->color ?? null),
                    // top-level linkage fields (some clients check top-level)
                    'project_job_assignment_by_myself_id' => $arr['project_job_assignment_by_myself_id'] ?? ($e->project_job_assignment_by_myself_id ?? null),
                    'project_job_assignment_id' => $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null),
                    // Extended props for FullCalendar compatibility
                    'extendedProps' => array_merge($arr['extendedProps'] ?? [], [
                        'project_job_assignment_by_myself_id' => $arr['project_job_assignment_by_myself_id'] ?? ($e->project_job_assignment_by_myself_id ?? null),
                        'project_job_assignment_id' => $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null),
                        'description' => $e->description ?? ($arr['description'] ?? null),
                    ]),
                ];
            })->values();

            // Temporary debug: log first event to ensure linkage keys are present for Inertia props
            // sample logging removed

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
