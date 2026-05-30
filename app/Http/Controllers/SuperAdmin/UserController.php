<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\Company;
use App\Models\Department;
use App\Models\PositionTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    use \App\Http\Controllers\Concerns\ResolvesContextCompany;

    public function index(\Illuminate\Http\Request $request)
    {
        // タブで明示的に指定された会社 > コンテキスト会社 の順で優先
        $filterCompanyId = $request->query('filter_company')
            ? (int) $request->query('filter_company')
            : $this->contextCompanyId();

        $query = User::orderBy('created_at', 'desc');
        if ($filterCompanyId) {
            $query->where('company_id', $filterCompanyId);
        }
        $users = $query->get();

        // タブ会社に絞った部署一覧
        $departments = $filterCompanyId
            ? Department::where('company_id', $filterCompanyId)->get()
            : Department::all();

        $assignments = \App\Models\Assignment::all();
        $user = Auth::user();

        // SuperAdmin 向け: 会社タブ用の一覧
        $companies = Company::where('code', '!=', 'SUPERADMIN')
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'company_type']);

        return Inertia::render('SuperAdmin/Users/Index', [
            'users'             => $users,
            'assignments'       => $assignments,
            'departments'       => $departments,
            'user'              => $user,
            'companies'         => $companies,
            'filterCompanyId'   => $filterCompanyId,
        ]);
    }

    public function create()
    {
        $companies = Company::with(['departments.assignments' => function($q){
            $q->where('active', true);
        }])->where('active', true)->get();

        return Inertia::render('SuperAdmin/Users/Create', [
            'companies' => $companies,
        ]);
    }

    public function store(Request $request)
    {
    $current = Auth::user();
    if ($request->input('user_role') === 'admin' && (! $current || $current->user_role !== 'superadmin')) {
            return redirect()->route('superadmin.users.index')
                ->with('error', '管理者の作成は許可されていません。');
        }

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|lowercase|email|max:255|unique:users|email',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'assignment_id' => 'required|exists:assignments,id',
                'user_role' => [
                    'required',
                    function($attribute, $value, $fail) {
                        $allowed = ['admin', 'leader', 'coordinator', 'user'];
                        if (!in_array($value, $allowed)) {
                            $fail("{$attribute} の値 '{$value}' は許可されていません（許可値: " . implode(',', $allowed) . ")");
                        }
                    }
                ],
            ]);

            $companyTeam = Team::where('company_id', $request->company_id)
                ->where('team_type', 'company')
                ->first();
            $departmentTeam = Team::where('department_id', $request->department_id)
                ->first();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
                'assignment_id' => $request->assignment_id,
                'current_team_id' => $request->company_id,
                'user_role' => $request->user_role,
                'email_verified_at' => now(),
            ]);

            $role = ($request->user_role === 'admin') ? 'admin' : 'viewer';

            if ($companyTeam) {
                $user->teams()->attach($companyTeam->id, ['role' => $role]);
            }
            if ($departmentTeam) {
                $user->teams()->attach($departmentTeam->id, ['role' => $role]);
            }

            return redirect()->route('superadmin.users.index')
                ->with('success', 'ユーザーが正常に作成されました。');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('登録バリデーションエラー:', $e->errors());
            throw $e;
        }
    }

    public function show(User $user)
    {
        return Inertia::render('SuperAdmin/Users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user)
    {
        $companies = Company::with(['departments' => function ($q) {
            $q->with(['assignments' => function ($q2) {
                $q2->where('active', true);
            }]);
        }])->where('active', true)->get();

        $positionTitles = PositionTitle::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Users/Edit', [
            'user'           => $user,
            'companies'      => $companies,
            'positionTitles' => $positionTitles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'email'             => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'user_role'         => 'required|string|in:admin,leader,coordinator,proof_coordinator,clerk,user',
            'company_id'        => 'required|exists:companies,id',
            'department_id'     => 'required|exists:departments,id',
            'assignment_id'     => 'required|exists:assignments,id',
            'employment_type'   => 'nullable|string|in:regular,contract,dispatch,outsource',
            'position_title_id' => 'nullable|exists:position_titles,id',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules);

        $updateData = [
            'name'              => $request->name,
            'email'             => $request->email,
            'user_role'         => $request->user_role,
            'company_id'        => $request->company_id,
            'department_id'     => $request->department_id,
            'assignment_id'     => $request->assignment_id,
            'employment_type'   => $request->input('employment_type', 'regular'),
            'position_title_id' => $request->input('position_title_id') ?: null,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // 会社・部署チームの同期
        $companyTeam    = Team::where('company_id', $request->company_id)->where('team_type', 'company')->first();
        $departmentTeam = Team::where('department_id', $request->department_id)->first();
        $role = ($request->user_role === 'admin') ? 'admin' : 'viewer';

        if ($companyTeam) {
            $user->teams()->syncWithoutDetaching([$companyTeam->id => ['role' => $role]]);
        }
        if ($departmentTeam) {
            $user->teams()->syncWithoutDetaching([$departmentTeam->id => ['role' => $role]]);
        }

        return redirect()->route('superadmin.users.index')
            ->with('success', 'ユーザー情報が更新されました。');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('superadmin.users.index')
                ->with('error', '自分自身のアカウントは削除できません。');
        }

        $user->delete();

        return redirect()->route('superadmin.users.index')
            ->with('success', 'ユーザーが削除されました。');
    }
}
