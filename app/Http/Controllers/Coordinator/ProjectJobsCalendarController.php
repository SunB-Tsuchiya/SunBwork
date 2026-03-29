<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\ProjectJob;
use App\Models\ProjectSchedule;

class ProjectJobsCalendarController extends Controller
{
    // Distinct color palette for projects (12 colors)
    private const PALETTE = [
        '#e11d48', // rose
        '#2563eb', // blue
        '#16a34a', // green
        '#d97706', // amber
        '#7c3aed', // violet
        '#0891b2', // cyan
        '#dc2626', // red
        '#65a30d', // lime
        '#9333ea', // purple
        '#0284c7', // sky
        '#ca8a04', // yellow
        '#059669', // emerald
    ];

    public function index()
    {
        $user = Auth::user();

        // Coordinator's own project jobs
        $projectJobs = ProjectJob::where('user_id', $user->id)
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get();

        // Assign colors from palette (cycle if more than 12 projects)
        $projects = [];
        $colorMap  = [];
        foreach ($projectJobs as $i => $pj) {
            $color = self::PALETTE[$i % count(self::PALETTE)];
            $colorMap[$pj->id] = $color;
            $projects[] = [
                'id'          => $pj->id,
                'title'       => $pj->title,
                'jobcode'     => $pj->jobcode ?? null,
                'client_name' => $pj->client?->name ?? null,
                'color'       => $color,
                'completed'   => $pj->completed,
            ];
        }

        // Fetch all schedules that belong to those projects
        $projectJobIds = $projectJobs->pluck('id')->toArray();
        $schedules = ProjectSchedule::whereIn('project_job_id', $projectJobIds)
            ->get()
            ->map(function ($s) use ($colorMap) {
                $fmt = function ($v) {
                    if (!$v) return null;
                    if ($v instanceof \Illuminate\Support\Carbon) return $v->toDateString();
                    return substr((string) $v, 0, 10);
                };
                return [
                    'id'             => $s->id,
                    'name'           => $s->name ?? null,
                    'start_date'     => $fmt($s->start_date),
                    'end_date'       => $fmt($s->end_date),
                    'project_job_id' => $s->project_job_id,
                    'color'          => $colorMap[$s->project_job_id] ?? '#2563eb',
                    'progress'       => $s->progress ?? 0,
                ];
            })
            ->values();

        return Inertia::render('Coordinator/ProjectJobs/CalendarAll', [
            'projects'  => $projects,
            'schedules' => $schedules,
        ]);
    }
}
