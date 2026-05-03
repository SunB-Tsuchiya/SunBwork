<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ProjectJobController extends Controller
{
    public function index(Request $request)
    {
        $departments    = Team::where('team_type', 'department')->orderBy('id')->get(['id', 'name']);
        $selectedDeptId = (int) $request->input('department', $departments->first()?->id);
        $q              = $request->input('q', '');
        $period         = $request->input('period', 'all');

        // 部署チームのメンバーIDを取得（team_user pivot 経由）
        $memberIds = DB::table('team_user')
            ->where('team_id', $selectedDeptId)
            ->pluck('user_id')
            ->toArray();

        $query = ProjectJob::with(['client', 'user'])
            ->where(function ($sub) use ($memberIds) {
                $sub->whereIn('user_id', $memberIds)
                    ->orWhereHas('coordinators', fn ($c) => $c->whereIn('users.id', $memberIds));
            });

        if ($q) {
            $query->where(function ($q2) use ($q) {
                $q2->where('title', 'like', "%{$q}%")
                    ->orWhere('jobcode', 'like', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($period && $period !== 'all') {
            [$y, $m] = explode('-', $period);
            $query->whereYear('created_at', $y)->whereMonth('created_at', $m);
        }

        $jobs = $query->orderBy('created_at', 'desc')->get();

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d              = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $d->format('Y-m'),
                'label' => $d->format('Y年n月'),
            ];
        }

        return Inertia::render('Admin/ProjectJobs/Index', [
            'jobs'               => $jobs,
            'departments'        => $departments,
            'selectedDepartment' => $selectedDeptId,
            'monthOptions'       => $monthOptions,
            'q'                  => $q,
            'period'             => $period,
        ]);
    }

    public function show(ProjectJob $projectJob)
    {
        $projectJob->load(['client', 'user', 'coordinators', 'teamMembers.user']);

        $assignmentEvents = [];
        try {
            $assignments = ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->with(['user', 'statusModel', 'stage'])
                ->get();

            $userAssignmentIds = $assignments->map(fn ($a) => $a->user?->assignment_id)
                ->filter()->unique()->values()->all();

            $assignmentNameMap = [];
            $assignmentCodeMap = [];
            if (!empty($userAssignmentIds)) {
                $roleRecords       = Assignment::whereIn('id', $userAssignmentIds)->get(['id', 'name', 'code']);
                $assignmentNameMap = $roleRecords->pluck('name', 'id')->toArray();
                $assignmentCodeMap = $roleRecords->pluck('code', 'id')->toArray();
            }

            if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                foreach ($assignments as $a) {
                    $events = Event::where('project_job_assignment_id', $a->id)
                        ->orderBy('starts_at')
                        ->get();

                    foreach ($events as $ev) {
                        $userAssignmentId   = $a->user?->assignment_id ?? null;
                        $userAssignmentCode = $userAssignmentId ? ($assignmentCodeMap[$userAssignmentId] ?? null) : null;
                        $userAssignmentName = $userAssignmentId ? ($assignmentNameMap[$userAssignmentId] ?? null) : null;

                        $eventDate = null;
                        try {
                            if ($ev->starts_at) {
                                $eventDate = Carbon::parse($ev->starts_at)->toDateString();
                            }
                        } catch (\Throwable $_) {}

                        $assignmentEvents[] = [
                            'assignment_id'   => $a->id,
                            'user_id'         => $a->user?->id ?? $a->user_id ?? null,
                            'user_name'       => $a->user?->name ?? null,
                            'assignment_name' => $userAssignmentName ?? $a->title ?? null,
                            'assignment_code' => $userAssignmentCode,
                            'role_category'   => $this->toRoleCategory($userAssignmentCode),
                            'stage_id'        => $a->stage_id ?? null,
                            'stage_name'      => $a->stage?->name ?? null,
                            'stage_sort'      => $a->stage?->sort_order ?? 99,
                            'status_name'     => $a->statusModel?->name ?? null,
                            'date'            => $eventDate,
                            'start'           => $ev->start ?? $ev->starts_at ?? null,
                            'end'             => $ev->end ?? $ev->ends_at ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Admin: Failed to build assignmentEvents', [
                'error'          => $e->getMessage(),
                'project_job_id' => $projectJob->id,
            ]);
            $assignmentEvents = [];
        }

        return Inertia::render('Admin/ProjectJobs/Show', [
            'job'              => $projectJob,
            'assignmentEvents' => $assignmentEvents,
            'roleConfig'       => [
                ['key' => 'coordinator',  'label' => '進行管理'],
                ['key' => 'production',   'label' => '組版・制作'],
                ['key' => 'proofreading', 'label' => '校正'],
                ['key' => 'other',        'label' => 'その他'],
            ],
        ]);
    }

    private function toRoleCategory(?string $code): string
    {
        return match ($code) {
            'shinko'   => 'coordinator',
            'operator' => 'production',
            'kousei'   => 'proofreading',
            default    => 'other',
        };
    }
}
