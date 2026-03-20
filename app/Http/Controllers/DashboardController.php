<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use Illuminate\Support\Facades\Schema;

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

        // カレンダー用データ（User Dashboard のみ）
        $diaries = [];
        $events = [];
        $jobs = [];
        if ($component === 'Dashboard') {
            $diary_from = now()->subDays(20)->startOfDay();
            $diary_to   = now()->endOfDay();
            $event_from = now()->subMonth(1)->startOfMonth();
            $event_to   = now()->addMonth(1)->endOfMonth();

            $diaries = Diary::where('user_id', $user->id)
                ->when(Schema::hasColumn('diaries', 'date'), fn($q) =>
                    $q->whereBetween('date', [$diary_from, $diary_to])
                )
                ->get();

            $eventQuery = Event::where('user_id', $user->id);
            $select = ['id', 'title'];
            if (Schema::hasColumn('events', 'starts_at')) {
                $eventQuery->whereBetween('starts_at', [$event_from, $event_to]);
                $select[] = 'starts_at';
            } elseif (Schema::hasColumn('events', 'start')) {
                $eventQuery->whereBetween('start', [$event_from, $event_to]);
                $select[] = 'start';
            }
            foreach (['ends_at', 'end', 'body', 'description', 'project_job_assignment_id'] as $col) {
                if (Schema::hasColumn('events', $col)) $select[] = $col;
            }
            $events = $eventQuery->get($select)->map(function ($e) {
                $arr = $e->toArray();
                $startVal = $e->start ?? $arr['start'] ?? $arr['starts_at'] ?? $arr['startsAt'] ?? null;
                $endVal   = $e->end   ?? $arr['end']   ?? $arr['ends_at']   ?? $arr['endsAt']   ?? null;
                $descVal  = $e->description ?? $arr['description'] ?? $arr['body'] ?? null;
                $pjaId    = $arr['project_job_assignment_id'] ?? ($e->project_job_assignment_id ?? null);
                return [
                    'id'                         => $e->id,
                    'title'                      => $e->title,
                    'start'                      => $startVal,
                    'end'                        => $endVal,
                    'allDay'                     => $arr['allDay'] ?? false,
                    'description'                => $descVal,
                    'color'                      => $arr['color'] ?? ($e->color ?? null),
                    'project_job_assignment_id'  => $pjaId,
                    'extendedProps'              => array_merge($arr['extendedProps'] ?? [], [
                        'project_job_assignment_id' => $pjaId,
                        'description'               => $descVal,
                    ]),
                ];
            })->values();

            $jobs = ProjectJobAssignment::where('user_id', $user->id)
                ->where(fn($q) => $q->where('accepted', true)->orWhere('assigned', true))
                ->with('projectJob')
                ->get()
                ->map(fn($a) => [
                    'id'             => $a->id,
                    'title'          => $a->title ?: ($a->projectJob?->name ?? '無題'),
                    'preferred_date' => $a->desired_start_date?->format('Y-m-d'),
                ]);
        }

        return Inertia::render($component, [
            'user'    => $user,
            'diaries' => $diaries,
            'events'  => $events,
            'jobs'    => $jobs,
        ]);
    }
}
