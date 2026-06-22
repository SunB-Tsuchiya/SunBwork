<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressCell;
use App\Models\ProjectJob;
use App\Models\ProjectTeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgressReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // スコープ: SuperAdmin も通常の coordinator フィルターを適用
        if ($user->isSuperAdmin()) {
            $companyId = session('superadmin_context.company_id');
            if ($companyId === null) {
                // グローバルモード: 自分が関わる案件のみ（会社フィルターなし）
                $ownedIds  = ProjectJob::where('user_id', $user->id)->pluck('id')->all();
                $memberIds = ProjectTeamMember::where('user_id', $user->id)->pluck('project_job_id')->all();
                $allowedJobIds = array_unique(array_merge($ownedIds, $memberIds));
            } else {
                // コンテキスト会社 + 自分が owner or team member の案件のみ
                $ownedIds  = ProjectJob::where('company_id', $companyId)->where('user_id', $user->id)->pluck('id')->all();
                $memberIds = ProjectTeamMember::where('user_id', $user->id)->pluck('project_job_id')->all();
                $allowedJobIds = array_unique(array_merge($ownedIds, $memberIds));
            }
        } elseif ($user->isAdmin() || $user->isClerk()) {
            // Admin / Clerk: 自社の案件のみ
            $allowedJobIds = $user->company_id
                ? ProjectJob::where('company_id', $user->company_id)->pluck('id')->all()
                : [];
        } else {
            // Coordinator など: 自分が project_team_members に含まれる案件のみ
            $allowedJobIds = ProjectTeamMember::where('user_id', $user->id)
                ->pluck('project_job_id')
                ->unique()
                ->all();
        }

        $filterUserId      = $request->input('user_id');
        $filterJobId       = $request->input('project_job_id');
        $filterStatus      = $request->input('status', 'incomplete');
        $filterTo          = $request->input('deadline_to');
        $filterCompletedDate = $request->input('completed_date');

        $query = ProgressCell::where('cell_type', 'worker')
            ->whereNotNull('value_user_id')
            ->with([
                'row:id,label,sheet_id',
                'row.sheet:id,name,project_job_id,column_config',
                'row.sheet.projectJob:id,title,client_id',
                'row.sheet.projectJob.client:id,name',
                'schedule:id,name,end_date',
                'assignment:id,desired_end_date',
                'valueUser:id,name',
            ]);

        if ($allowedJobIds !== null) {
            $query->whereHas('row.sheet', fn ($q) =>
                $q->whereIn('project_job_id', $allowedJobIds)
            );
        }

        if ($filterUserId) {
            $query->where('value_user_id', (int) $filterUserId);
        }

        if ($filterJobId) {
            $query->whereHas('row.sheet', fn ($q) =>
                $q->where('project_job_id', (int) $filterJobId)
            );
        }

        if ($filterStatus === 'incomplete') {
            $query->whereNull('completed_at');
        } elseif ($filterStatus === 'complete') {
            $query->whereNotNull('completed_at');
        }

        $cells = $query->get()->map(function ($cell) {
            $deadline = $cell->cell_deadline?->format('Y-m-d')
                ?? $cell->schedule?->end_date?->format('Y-m-d')
                ?? $cell->assignment?->desired_end_date?->format('Y-m-d');

            return [
                'id'                => $cell->id,
                'project_job_id'    => $cell->row?->sheet?->project_job_id,
                'project_job_title' => $cell->row?->sheet?->projectJob?->title ?? '-',
                'client_name'       => $cell->row?->sheet?->projectJob?->client?->name ?? '-',
                'sheet_id'          => $cell->row?->sheet_id,
                'sheet_name'        => $cell->row?->sheet?->name ?? '-',
                'row_label'         => $cell->row?->label ?? '-',
                'col_label'         => $this->findColLabel(
                    $cell->row?->sheet?->column_config ?? [],
                    $cell->col_key
                ),
                'user_name'         => $cell->valueUser?->name ?? '-',
                'deadline'          => $deadline,
                'completed_at'      => $cell->completed_at?->format('Y-m-d H:i'),
            ];
        });

        if ($filterTo) {
            $cells = $cells->filter(fn ($c) => $c['deadline'] && $c['deadline'] <= $filterTo);
        }
        if ($filterCompletedDate) {
            $cells = $cells->filter(fn ($c) => $c['completed_at'] && substr($c['completed_at'], 0, 10) <= $filterCompletedDate);
        }

        $cells = $cells->sortBy(fn ($c) => $c['deadline'] ?? '9999-99-99')->values();

        // フィルター用: スコープ内の案件一覧
        $projectJobsQuery = ProjectJob::with('client')->orderBy('title');
        if ($allowedJobIds !== null) {
            $projectJobsQuery->whereIn('id', $allowedJobIds);
        }
        $projectJobs = $projectJobsQuery->get()->map(fn ($j) => [
            'id'          => $j->id,
            'title'       => $j->title,
            'client_name' => $j->client?->name ?? '-',
        ]);

        // フィルター用: スコープ内のセルに登場する担当者一覧
        $userIds = ProgressCell::where('cell_type', 'worker')
            ->whereNotNull('value_user_id')
            ->when($allowedJobIds !== null, fn ($q) =>
                $q->whereHas('row.sheet', fn ($q2) =>
                    $q2->whereIn('project_job_id', $allowedJobIds)
                )
            )
            ->pluck('value_user_id')
            ->unique()
            ->all();

        $users = User::whereIn('id', $userIds)->ordered()->get(['id', 'name']);

        return Inertia::render('Coordinator/ProgressReport/Index', [
            'cells'       => $cells,
            'projectJobs' => $projectJobs,
            'users'       => $users,
            'filters'     => [
                'user_id'        => $filterUserId ? (int) $filterUserId : null,
                'project_job_id' => $filterJobId  ? (int) $filterJobId  : null,
                'status'         => $filterStatus,
                'deadline_to'    => $filterTo,
                'completed_date' => $filterCompletedDate,
            ],
        ]);
    }

    private function findColLabel(array $nodes, string $key): string
    {
        foreach ($nodes as $node) {
            if (($node['key'] ?? '') === $key) {
                return $node['label'] ?? $key;
            }
            if (!empty($node['children'])) {
                $found = $this->findColLabel($node['children'], $key);
                if ($found !== $key) {
                    return $found;
                }
            }
        }
        return $key;
    }
}
