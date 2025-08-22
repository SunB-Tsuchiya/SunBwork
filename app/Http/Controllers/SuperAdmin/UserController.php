<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        $assignments = \App\Models\Assignment::all();
        $departments = Department::all();
        $user = Auth::user();

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
            'assignments' => $assignments,
            'departments' => $departments,
            'user' => $user,
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
        if ($request->input('user_role') === 'admin' && (! $current || ! ($current->is_superadmin ?? false))) {
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
        return Inertia::render('SuperAdmin/Users/Edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'assignment' => 'required|string|max:255',
            'user_role' => 'required|in:admin,leader,coordinator, user',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'assignment' => $request->assignment,
            'user_role' => $request->user_role,
        ]);

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
