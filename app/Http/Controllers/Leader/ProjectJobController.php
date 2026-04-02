<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectJobController extends Controller
{
    /**
     * 部署に関係する案件をすべて表示（読み取り専用）
     * 代表Coordinator または サブCoordinator が同じ部署 → 対象
     */
    public function index(Request $request)
    {
        $user         = $request->user();
        $departmentId = $user->department_id;
        $q            = $request->input('q', '');
        $period       = $request->input('period', '');

        $query = ProjectJob::with(['client', 'user'])
            ->where(function ($sub) use ($departmentId) {
                $sub->whereHas('user', fn ($u) => $u->where('department_id', $departmentId))
                    ->orWhereHas('coordinators', fn ($c) => $c->where('users.department_id', $departmentId));
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

        return Inertia::render('Leader/ProjectJobs/Index', [
            'jobs'         => $jobs,
            'monthOptions' => $monthOptions,
            'q'            => $q,
            'period'       => $period,
        ]);
    }

    /**
     * 案件詳細（読み取り専用）
     * 部署外の案件へのアクセスは 403
     */
    public function show(Request $request, ProjectJob $projectJob)
    {
        $user         = $request->user();
        $departmentId = $user->department_id;

        $inDept = $projectJob->user?->department_id === $departmentId
            || $projectJob->coordinators()->where('users.department_id', $departmentId)->exists();

        if (! $inDept) {
            abort(403, 'この案件は管理対象外です。');
        }

        $projectJob->load(['client', 'user', 'coordinators', 'teamMembers.user']);

        return Inertia::render('Leader/ProjectJobs/Show', [
            'job' => $projectJob,
        ]);
    }
}
