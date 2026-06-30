<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\EventItemType;
use App\Models\MeetingDefinition;
use App\Models\MeetingRoom;
use App\Models\ScheduleCalendarOverlay;
use App\Models\User;
use App\Models\UserMonthlyBreak;
use App\Models\UserMonthlySchedule;
use App\Models\Worktype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $overlays = ScheduleCalendarOverlay::where('user_id', $user->id)
            ->with(['targetUser:id,name', 'targetCompany:id,name', 'targetDepartment:id,name'])
            ->orderBy('sort_order')
            ->get();

        $rooms = MeetingRoom::where('company_id', $user->company_id)
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'available_from', 'available_to']);

        $eventItemTypes = EventItemType::orderBy('id')->get(['id', 'name', 'slug']);

        $meetingDefinitions = MeetingDefinition::where('company_id', $user->company_id)
            ->with('members:id,name')
            ->get(['id', 'title', 'description', 'recurrence', 'day_of_week', 'week_of_month', 'custom_dates', 'start_time', 'end_time']);

        // 参加者ピッカー用: 全会社・全部署（company_id 付き）
        $companies   = Company::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('sort_order')->get(['id', 'name', 'company_id']);

        // 日程行（worktype）表示用
        $worktypes       = [];
        $dailyWorktypes  = [];
        $defaultWorktype = null;

        try {
            $wq = Worktype::orderBy('sort_order');
            if ($user->company_id) $wq->where('company_id', $user->company_id);
            $worktypes = $wq->get(['id', 'name', 'start_time', 'end_time'])->toArray();
        } catch (\Throwable $e) {
            Log::error('ScheduleController worktypes: ' . $e->getMessage());
        }

        try {
            $setting = $user->userSetting()->with('worktype')->first();
            if ($setting?->worktype) {
                $defaultWorktype = [
                    'id'   => $setting->worktype->id,
                    'name' => $setting->worktype->name,
                ];
            }
        } catch (\Throwable $e) { /* non-fatal */ }

        try {
            $fromYm = now()->subMonths(3)->format('Y-m');
            $toYm   = now()->addMonths(3)->format('Y-m');
            UserMonthlySchedule::where('user_id', $user->id)
                ->whereBetween('year_month', [$fromYm, $toYm])
                ->get(['year_month', 'schedule'])
                ->each(function ($ms) use (&$dailyWorktypes) {
                    foreach (($ms->schedule ?? []) as $dd => $worktypeId) {
                        if ($worktypeId) {
                            $dailyWorktypes[] = ['date' => $ms->year_month . '-' . $dd, 'worktype_id' => (int) $worktypeId];
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::error('ScheduleController dailyWorktypes: ' . $e->getMessage());
        }

        return Inertia::render('Schedule/Index', [
            'initialDate'        => now()->toDateString(),
            'overlays'           => $overlays,
            'rooms'              => $rooms,
            'eventItemTypes'     => $eventItemTypes,
            'meetingDefinitions' => $meetingDefinitions,
            'companies'          => $companies,
            'departments'        => $departments,
            'worktypes'          => $worktypes,
            'dailyWorktypes'     => $dailyWorktypes,
            'defaultWorktype'    => $defaultWorktype,
        ]);
    }

    public function rooms(Request $request)
    {
        $user = Auth::user();
        $rooms = MeetingRoom::where('company_id', $user->company_id)
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'capacity', 'available_from', 'available_to']);

        return response()->json($rooms);
    }

    public function clients(Request $request)
    {
        $user = Auth::user();

        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobsFromTeam = $ptms->map(fn($ptm) => $ptm->projectJob)->filter();

            $jobsAsLeader = \App\Models\ProjectJob::with('client')
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->get();

            $jobsAsSubLeader = \App\Models\ProjectJob::with('client')
                ->whereHas('coordinators', fn($q) => $q->where('users.id', $user->id))
                ->where('completed', false)
                ->get();

            $jobs = $jobsFromTeam->merge($jobsAsLeader)->merge($jobsAsSubLeader)->unique('id');

            $clients = $jobs->map(fn($job) => $job->client)
                ->filter()
                ->unique('id')
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name ?? ''])
                ->values();
        } catch (\Throwable $e) {
            Log::warning('ScheduleController::clients() failed', ['error' => $e->getMessage()]);
            $clients = collect();
        }

        return response()->json($clients);
    }

    public function users(Request $request)
    {
        $user      = Auth::user();
        $selfId    = $user->id;
        $q         = trim($request->get('q', ''));
        $companyId = $request->get('company_id');
        $deptId    = $request->get('department_id');

        // 自社またはグループ会社のユーザーのみ返す
        $allowedCompanyIds = $this->allowedCompanyIds((int) $user->company_id);

        // 指定 company_id が許可リスト外なら空を返す
        if ($companyId && !in_array((int) $companyId, $allowedCompanyIds)) {
            return response()->json([]);
        }

        $users = User::where('id', '!=', $selfId)
            ->when(
                $companyId,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereIn('company_id', $allowedCompanyIds)
            )
            ->when($deptId,   fn ($query) => $query->where('department_id', $deptId))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->ordered()
            ->get(['id', 'name', 'user_role', 'department_id', 'company_id', 'sort_order']);

        return response()->json($users);
    }

    /** 自社と同グループの全 company_id を返す */
    private function allowedCompanyIds(int $companyId): array
    {
        $groupIds = DB::table('company_group_members')
            ->where('company_id', $companyId)
            ->pluck('company_group_id');

        if ($groupIds->isEmpty()) {
            return [$companyId];
        }

        return DB::table('company_group_members')
            ->whereIn('company_group_id', $groupIds)
            ->pluck('company_id')
            ->push($companyId)
            ->unique()
            ->values()
            ->all();
    }
}
