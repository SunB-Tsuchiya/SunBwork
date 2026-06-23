<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProjectTeamMember;
use App\Http\Requests\StoreProjectTeamMembersRequest;

class ProjectTeamMembersController extends Controller
{
    public function store(StoreProjectTeamMembersRequest $request)
    {

        $projectJobId = $request->input('project_job_id');
        $userIds = $request->input('user_ids', []);
        try {
            DB::beginTransaction();
            // 既存のメンバーを一旦削除（上書き方式）
            ProjectTeamMember::where('project_job_id', $projectJobId)->delete();
            // 新規登録
            foreach ($userIds as $userId) {
                ProjectTeamMember::create([
                    'project_job_id' => $projectJobId,
                    'user_id' => $userId,
                ]);
            }
            DB::commit();
            return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $projectJobId])
                ->with('success', 'チームメンバーを登録しました。');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('DBエラー: ' . $e->getMessage(), ['request' => $request->all()]);
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['db' => 'データベースエラーが発生しました。管理者にお問い合わせください。']);
        } catch (\Exception $e) {
            Log::error('例外: ' . $e->getMessage(), ['request' => $request->all()]);
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->withErrors(['exception' => '予期しないエラーが発生しました。管理者にお問い合わせください。']);
        }
    }
    public function index()
    {
        $members = User::ordered()->with(['department', 'assignment'])->get();
        $departments = Department::all();
        $assignments = Assignment::all();
        $user = Auth::user();

        return Inertia::render('Coordinator/ProjectTeamMembers/Index', [
            'members' => $members,
            'departments' => $departments,
            'assignments' => $assignments,
            'user' => $user,
        ]);
    }
    public function create()
    {
        $departments = Department::all();
        $assignments = Assignment::all();
        $user = Auth::user();

        // Allow optional project_job_id to be passed via querystring from previous step
        $projectJobId = request()->query('project_job_id');

        $leaderDepartmentId = null;
        $preCheckedIds = [];

        if ($projectJobId) {
            $projectJob = \App\Models\ProjectJob::with(['user', 'coordinators'])->find($projectJobId);
            if ($projectJob) {
                // リーダーの部署で絞り込む
                if ($projectJob->user) {
                    $leaderDepartmentId = $projectJob->user->department_id;
                }
                // リーダーとサブリーダーを初期チェック済みにする
                $preCheckedIds = array_values(array_filter(array_unique(array_merge(
                    [$projectJob->user_id],
                    $projectJob->coordinators->pluck('id')->toArray()
                ))));
            }
        }

        // リーダーの部署でメンバーを絞り込む（project_job_idがない場合は全員表示）
        $membersQuery = User::ordered()->with(['department', 'assignment']);
        if ($leaderDepartmentId) {
            $membersQuery->where('department_id', $leaderDepartmentId);
        }
        $members = $membersQuery->get()->map(fn ($u) => array_merge($u->toArray(), ['is_ghost' => false]));

        $ghosts = User::withGhosts()
            ->where('ghost_owner_id', Auth::id())
            ->get(['id', 'name'])
            ->map(fn ($g) => [
                'id'            => $g->id,
                'name'          => $g->name,
                'department_id' => null,
                'assignment_id' => null,
                'is_ghost'      => true,
            ]);
        $members = collect($members)->concat($ghosts)->values();

        return Inertia::render('Coordinator/ProjectTeamMembers/Create', [
            'members' => $members,
            'departments' => $departments,
            'assignments' => $assignments,
            'user' => $user,
            'project_job_id' => $projectJobId,
            'pre_checked_ids' => $preCheckedIds,
            'leader_department_id' => $leaderDepartmentId,
        ]);
    }
}
