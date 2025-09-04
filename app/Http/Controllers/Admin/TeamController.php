<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        // チーム一覧をcompany, departmentリレーション付きで取得
        $teams = Team::with(['company', 'department'])->get();
        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function edit($id)
    {
        $team = Team::with(['company', 'department'])->findOrFail($id);
        $companies = \App\Models\Company::active()->get(['id', 'name']);
        $departments = \App\Models\Department::active()->get(['id', 'name', 'company_id']);
        $props = [
            'team' => $team,
            'companies' => $companies,
            'departments' => $departments,
        ];

        // If this is a unit team, include the Unit model and users/leaders for the edit form
        if ($team->team_type === 'unit') {
            $unit = \App\Models\Unit::where('company_id', $team->company_id)
                ->where('department_id', $team->department_id)
                ->where('name', $team->name)
                ->first();

            $companyId = $team->company_id;
            $users = \App\Models\User::select(['id', 'name', 'user_role', 'department_id', 'company_id'])
                ->where('company_id', $companyId)
                ->get();

            $leaders = \App\Models\User::select(['id', 'name', 'user_role'])
                ->whereIn('user_role', ['superadmin', 'admin'])
                ->get();

            $props['unit'] = $unit;
            $props['users'] = $users;
            $props['leaders'] = $leaders;
        }

        return Inertia::render('Admin/Teams/Edit', $props);
    }

    public function update(Request $request, $id)
    {
        $team = Team::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);
        $team->update($validated);
        return redirect()->route('admin.teams.index')->with('success', 'チーム情報を更新しました');
    }

    // Show a single team (resource route expects this)
    public function show($id)
    {
        $team = Team::with(['company', 'department'])->findOrFail($id);
        return Inertia::render('Admin/Teams/Show', [
            'team' => $team,
        ]);
    }

    // Destroy a team
    public function destroy($id)
    {
        $team = Team::findOrFail($id);
        $team->delete();
        // If request is AJAX/XHR, return 204 No Content for client-side handling
        if (request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['message' => 'deleted'], 204);
        }

        return redirect()->route('admin.teams.index')->with('success', 'チームを削除しました');
    }
}
