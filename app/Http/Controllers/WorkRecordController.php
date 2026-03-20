<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Team;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkRecordController extends Controller
{
    public function index(Request $request)
    {
        $user         = Auth::user();
        $role         = $user->user_role ?? 'leader';
        $isSuperAdmin = $role === 'superadmin';
        $isAdmin      = $role === 'admin';

        // フィルタ: 日数ベース (デフォルト30日)
        $days    = max(1, (int) $request->input('days', 30));
        $perPage = max(1, (int) $request->input('perPage', 50));
        $page    = max(1, (int) $request->input('page', 1));

        $end   = now()->toDateString();
        $start = now()->subDays($days - 1)->toDateString();

        // 閲覧可能ユーザーID取得
        $userIds = $this->buildPermittedUserIds($user, $isSuperAdmin, $isAdmin);

        // クエリ
        $query = WorkRecord::with(['user.department', 'worktype'])
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date', 'desc')
            ->orderBy('user_id');

        $total   = $query->count();
        $records = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // 会社・部署でグループ化
        $groups = $this->buildGroups($records, $isSuperAdmin);

        $lastPage = (int) ceil(max(1, $total) / $perPage);
        $meta = [
            'current_page' => $page,
            'last_page'    => $lastPage,
            'per_page'     => $perPage,
            'total'        => $total,
        ];

        $routePrefix = match ($role) {
            'superadmin' => 'superadmin',
            'admin'      => 'admin',
            default      => 'leader',
        };

        return Inertia::render('WorkRecord/Index', [
            'groups'        => $groups,
            'meta'          => $meta,
            'filters'       => ['days' => $days, 'perPage' => $perPage],
            'routePrefix'   => $routePrefix,
            'is_superadmin' => $isSuperAdmin,
        ]);
    }

    // -------------------------------------------------------

    private function buildPermittedUserIds($user, bool $isSuperAdmin, bool $isAdmin): array
    {
        if ($isSuperAdmin) {
            return User::pluck('id')->toArray();
        }

        if ($isAdmin) {
            return User::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        // Leader: チーム配下のユーザー
        $userIds = [];
        $teams   = Team::where('leader_id', $user->id)
            ->whereIn('team_type', ['department', 'unit'])
            ->get();

        foreach ($teams as $team) {
            if ($team->team_type === 'department' && $team->department_id) {
                $ids = User::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->pluck('id')->toArray();
                $userIds = array_merge($userIds, $ids);
            }

            if ($team->team_type === 'unit') {
                $unit = Unit::where('company_id', $team->company_id)
                    ->where('department_id', $team->department_id)
                    ->where('name', $team->name)
                    ->first();
                if ($unit) {
                    $ids = $unit->members()->pluck('users.id')->toArray();
                    $userIds = array_merge($userIds, $ids);
                }
            }
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    private function buildGroups($records, bool $isSuperAdmin): array
    {
        // company → department → records の入れ子構造
        $tree = [];

        foreach ($records as $rec) {
            $companyId   = $rec->user->company_id ?? 0;
            $companyName = $rec->user->company->name ?? '不明';
            $deptId      = $rec->user->department_id ?? 0;
            $deptName    = $rec->user->department->name ?? '未所属';

            $tree[$companyId]['company_name']             = $companyName;
            $tree[$companyId]['departments'][$deptId]['department_name'] = $deptName;
            $tree[$companyId]['departments'][$deptId]['records'][]       = [
                'id'                  => $rec->id,
                'user_id'             => $rec->user_id,
                'user_name'           => $rec->user->name ?? '',
                'date'                => $rec->date?->toDateString(),
                'worktype_name'       => $rec->worktype->name ?? '—',
                'start_time'          => substr($rec->start_time ?? '', 0, 5),
                'end_time'            => substr($rec->end_time ?? '', 0, 5),
                'scheduled_start'     => substr($rec->scheduled_start ?? '', 0, 5),
                'scheduled_end'       => substr($rec->scheduled_end ?? '', 0, 5),
                'overtime_minutes'    => $rec->overtime_minutes,
                'early_leave_minutes' => $rec->early_leave_minutes,
            ];
        }

        // 配列化
        $result = [];
        foreach ($tree as $companyData) {
            $depts = [];
            foreach ($companyData['departments'] as $dept) {
                $depts[] = $dept;
            }
            $result[] = [
                'company_name' => $companyData['company_name'],
                'departments'  => $depts,
            ];
        }

        return $result;
    }
}
