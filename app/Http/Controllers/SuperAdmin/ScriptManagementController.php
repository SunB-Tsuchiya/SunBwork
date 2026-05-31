<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Script;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScriptManagementController extends Controller
{
    public function index(): Response
    {
        $companies = Company::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        $departments = Department::orderBy('name')
            ->get(['id', 'company_id', 'name']);

        $users = User::whereNotIn('user_role', ['superadmin'])
            ->orderBy('name')
            ->get(['id', 'company_id', 'department_id', 'name', 'user_role']);

        $scripts = Script::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description']);

        // 全ユーザーの割り当て済みスクリプトID一覧 {user_id: [script_id, ...]}
        $assignments = Script::where('is_active', true)
            ->with('users:id')
            ->get()
            ->flatMap(fn($s) => $s->users->map(fn($u) => ['user_id' => $u->id, 'script_id' => $s->id]))
            ->groupBy('user_id')
            ->map(fn($rows) => $rows->pluck('script_id')->values());

        return Inertia::render('SuperAdmin/Scripts/Index', [
            'companies'   => $companies,
            'departments' => $departments,
            'users'       => $users,
            'scripts'     => $scripts,
            'assignments' => $assignments,
        ]);
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'script_ids' => 'present|array',
            'script_ids.*' => 'integer|exists:scripts,id',
        ]);

        $userIds   = $validated['user_ids'];
        $scriptIds = $validated['script_ids'] ?? [];

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            $user->scripts()->sync($scriptIds);
        }

        return back()->with('success', count($userIds) . '名のスクリプト割り当てを更新しました。');
    }
}
