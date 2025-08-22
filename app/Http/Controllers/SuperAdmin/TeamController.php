<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Team;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::with(['company', 'department'])->get();
        return Inertia::render('SuperAdmin/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function edit($id)
    {
        $team = Team::with(['company', 'department'])->findOrFail($id);
        $companies = \App\Models\Company::active()->get(['id', 'name']);
        $departments = \App\Models\Department::active()->get(['id', 'name', 'company_id']);
        return Inertia::render('SuperAdmin/Teams/Edit', [
            'team' => $team,
            'companies' => $companies,
            'departments' => $departments,
        ]);
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
        return redirect()->route('superadmin.teams.index')->with('success', 'チーム情報を更新しました');
    }
}
