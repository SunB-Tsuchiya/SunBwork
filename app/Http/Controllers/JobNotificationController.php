<?php

namespace App\Http\Controllers;

use App\Models\JobNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class JobNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $group = $request->query('group', 'day');
        $days  = (int) $request->query('days', 30);
        if (! in_array($days, [7, 30, 90])) {
            $days = 30;
        }

        $since = Carbon::now()->subDays($days)->startOfDay();

        $notifications = JobNotification::where('recipient_id', $user->id)
            ->where('created_at', '>=', $since)
            ->with(['sender', 'projectJob'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('JobNotifications/Index', [
            'notifications' => $notifications,
            'filters'       => [
                'group' => $group,
                'days'  => $days,
            ],
        ]);
    }

    public function show(Request $request, JobNotification $jobNotification)
    {
        $user = $request->user();

        if ($jobNotification->recipient_id !== $user->id) {
            abort(403);
        }

        if (is_null($jobNotification->read_at)) {
            $jobNotification->update(['read_at' => now()]);
        }

        $routeName = match (true) {
            $user->isAdmin() || $user->isSuperAdmin() => 'coordinator.project_jobs.show',
            $user->isCoordinator() || $user->isClerk() => 'coordinator.project_jobs.show',
            $user->isLeader() => 'leader.project_jobs.show',
            default => 'user.project_jobs.show',
        };

        try {
            return redirect()->route($routeName, ['projectJob' => $jobNotification->project_job_id]);
        } catch (\Throwable $e) {
            return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $jobNotification->project_job_id]);
        }
    }
}
