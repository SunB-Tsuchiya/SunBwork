<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\ProjectJob;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProjectJobController extends Controller
{
    use ResolvesContextCompany;
    use \App\Http\Controllers\Concerns\CalculatesEventTime;

    /**
     * 部署に関係する案件をすべて表示（読み取り専用）
     * 部署リーダー/副リーダー → 部署メンバーが owner or coordinator の案件
     * チームリーダー → unitメンバーが owner の案件
     */
    public function index(Request $request)
    {
        $user   = $request->user();
        $q      = $request->input('q', '');
        $period = $request->input('period', '');

        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d              = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $d->format('Y-m'),
                'label' => $d->format('Y年n月'),
            ];
        }

        // SuperAdmin: コンテキスト会社でフィルター（グローバルモードは警告）
        if ($user->isSuperAdmin()) {
            $companyId = $this->contextCompanyId();
            if ($companyId === null) {
                return Inertia::render('Leader/ProjectJobs/Index', [
                    'noCompanySelected' => true,
                    'jobs'              => collect(),
                    'monthOptions'      => $monthOptions,
                    'q'                 => $q,
                    'period'            => $period,
                ]);
            }
            $query = ProjectJob::with(['client', 'user'])
                ->where('company_id', $companyId);
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
            return Inertia::render('Leader/ProjectJobs/Index', [
                'jobs'         => $query->orderBy('created_at', 'desc')->get(),
                'monthOptions' => $monthOptions,
                'q'            => $q,
                'period'       => $period,
            ]);
        }

        [$deptMemberIds, $unitMemberIds] = $this->getAccessibleMemberIds($user);

        // チームが1件も割り当てられていない Leader は 0 件を返す（空クロージャによる全件漏洩バグ対策）
        if (empty($deptMemberIds) && empty($unitMemberIds)) {
            return Inertia::render('Leader/ProjectJobs/Index', [
                'jobs'         => collect(),
                'monthOptions' => $monthOptions,
                'q'            => $q,
                'period'       => $period,
            ]);
        }

        $query = ProjectJob::with(['client', 'user'])
            ->where('company_id', $user->company_id)
            ->where(function ($sub) use ($deptMemberIds, $unitMemberIds) {
                if (!empty($deptMemberIds)) {
                    $sub->orWhere(function ($q) use ($deptMemberIds) {
                        $q->whereIn('user_id', $deptMemberIds)
                          ->orWhereHas('coordinators', fn ($c) => $c->whereIn('users.id', $deptMemberIds));
                    });
                }
                if (!empty($unitMemberIds)) {
                    $sub->orWhereIn('user_id', $unitMemberIds);
                }
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

        return Inertia::render('Leader/ProjectJobs/Index', [
            'jobs'         => $jobs,
            'monthOptions' => $monthOptions,
            'q'            => $q,
            'period'       => $period,
        ]);
    }

    /**
     * 案件詳細（読み取り専用）
     * アクセス権限外の案件は 403
     */
    public function show(Request $request, ProjectJob $projectJob)
    {
        $user = $request->user();

        // 他社案件へのアクセスを拒否
        if ($user->company_id && (int) $projectJob->company_id !== (int) $user->company_id) {
            abort(403, 'この案件は管理対象外です。');
        }

        [$deptMemberIds, $unitMemberIds] = $this->getAccessibleMemberIds($user);

        $ownerId  = $projectJob->user_id;
        $coordIds = $projectJob->coordinators()->pluck('users.id')->toArray();

        $canAccess = (!empty($deptMemberIds) && (
            in_array($ownerId, $deptMemberIds) || count(array_intersect($coordIds, $deptMemberIds)) > 0
        )) || (!empty($unitMemberIds) && in_array($ownerId, $unitMemberIds));

        if (! $canAccess) {
            abort(403, 'この案件は管理対象外です。');
        }

        $projectJob->load(['client', 'user', 'coordinators', 'teamMembers.user']);

        $assignmentEvents = [];
        try {
            $assignments = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->with(['user', 'statusModel', 'stage'])
                ->get();

            $userAssignmentIds = $assignments->map(fn ($a) => $a->user?->assignment_id)
                ->filter()->unique()->values()->all();

            $assignmentNameMap = [];
            $assignmentCodeMap = [];
            if (!empty($userAssignmentIds)) {
                $roleRecords       = \App\Models\Assignment::whereIn('id', $userAssignmentIds)->get(['id', 'name', 'code']);
                $assignmentNameMap = $roleRecords->pluck('name', 'id')->toArray();
                $assignmentCodeMap = $roleRecords->pluck('code', 'id')->toArray();
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'project_job_assignment_id')) {
                foreach ($assignments as $a) {
                    // events は proof=UTC / 通常=JST の混在保存のため job_type をロードして
                    // resolveJstCarbon() で解決する（Coordinator\ProjectJobController と同方式）
                    $events = \App\Models\Event::where('project_job_assignment_id', $a->id)
                        ->orderBy('starts_at')
                        ->with('projectJobAssignment:id,job_type')
                        ->get();

                    foreach ($events as $ev) {
                        $userAssignmentId   = $a->user?->assignment_id ?? null;
                        $userAssignmentCode = $userAssignmentId ? ($assignmentCodeMap[$userAssignmentId] ?? null) : null;
                        $userAssignmentName = $userAssignmentId ? ($assignmentNameMap[$userAssignmentId] ?? null) : null;

                        // Carbon::parse($ev->starts_at) は proof（UTC 保存）で 9 時間ずれる
                        $jstStart = $this->resolveJstCarbon($ev, 'starts_at');
                        $jstEnd   = $this->resolveJstCarbon($ev, 'ends_at');
                        $eventDate = $jstStart?->toDateString();

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
                            'start'           => $jstStart?->toIso8601String(),
                            'end'             => $jstEnd?->toIso8601String(),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leader: Failed to build assignmentEvents', [
                'error'          => $e->getMessage(),
                'project_job_id' => $projectJob->id,
            ]);
            $assignmentEvents = [];
        }

        return Inertia::render('Leader/ProjectJobs/Show', [
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

    /**
     * ログインLeaderがアクセスできる案件のメンバーIDを返す
     * @return array{0: int[], 1: int[]} [deptMemberIds, unitMemberIds]
     */
    private function getAccessibleMemberIds($user): array
    {
        // 部署チーム（leader or sub-leader）
        $deptTeamIds = Team::where('team_type', 'department')
            ->where(function ($q) use ($user) {
                $q->where('leader_id', $user->id)
                  ->orWhereHas('subLeaders', fn ($s) => $s->where('users.id', $user->id));
            })
            ->pluck('id')
            ->toArray();

        // unitチーム（leader）
        $unitTeamIds = Team::where('team_type', 'unit')
            ->where('leader_id', $user->id)
            ->pluck('id')
            ->toArray();

        $deptMemberIds = !empty($deptTeamIds)
            ? DB::table('team_user')->whereIn('team_id', $deptTeamIds)->pluck('user_id')->unique()->toArray()
            : [];

        $unitMemberIds = !empty($unitTeamIds)
            ? DB::table('team_user')->whereIn('team_id', $unitTeamIds)->pluck('user_id')->unique()->toArray()
            : [];

        return [$deptMemberIds, $unitMemberIds];
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
