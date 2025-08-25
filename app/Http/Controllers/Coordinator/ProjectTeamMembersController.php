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
        $members = User::orderBy('created_at', 'desc')->with(['department', 'assignment'])->get();
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
        $members = User::orderBy('created_at', 'desc')->with(['department', 'assignment'])->get();
        $departments = Department::all();
        $assignments = Assignment::all();
        $user = Auth::user();

        // Allow optional project_job_id to be passed via querystring from previous step
        $projectJobId = request()->query('project_job_id');

        // If project job id provided, load existing team member user_ids for pre-selection
        $selectedMemberIds = [];
        if ($projectJobId) {
            $selectedMemberIds = ProjectTeamMember::where('project_job_id', $projectJobId)->pluck('user_id')->map(fn($v) => (int)$v)->all();
        }

        return Inertia::render('Coordinator/ProjectTeamMembers/Create', [
            'members' => $members,
            'departments' => $departments,
            'assignments' => $assignments,
            'user' => $user,
            'project_job_id' => $projectJobId,
            'selected_member_ids' => $selectedMemberIds,
        ]);
    }
}
