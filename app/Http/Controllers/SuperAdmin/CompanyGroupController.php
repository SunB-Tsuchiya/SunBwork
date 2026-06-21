<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CompanyGroupController extends Controller
{
    public function index()
    {
        $groups = CompanyGroup::with('companies:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('SuperAdmin/CompanyGroups/Index', ['groups' => $groups]);
    }

    public function create()
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);
        return Inertia::render('SuperAdmin/CompanyGroups/Create', ['companies' => $companies]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'group_key'   => 'required|string|max:50|unique:company_groups,group_key|alpha_dash',
            'description' => 'nullable|string',
            'active'      => 'boolean',
            'company_ids' => 'array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $group = CompanyGroup::create([
            'name'        => $validated['name'],
            'group_key'   => $validated['group_key'],
            'description' => $validated['description'] ?? null,
            'active'      => $validated['active'] ?? true,
            'created_by'  => Auth::id(),
        ]);

        if (!empty($validated['company_ids'])) {
            $group->companies()->attach($validated['company_ids']);
        }

        return redirect()->route('super-admin.company-groups.index')
            ->with('success', 'グループ会社を登録しました');
    }

    public function edit(CompanyGroup $companyGroup)
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);
        $companyGroup->load('companies:id,name');

        return Inertia::render('SuperAdmin/CompanyGroups/Edit', [
            'group'     => $companyGroup,
            'companies' => $companies,
        ]);
    }

    public function update(Request $request, CompanyGroup $companyGroup)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'group_key'   => 'sometimes|string|max:50|alpha_dash|unique:company_groups,group_key,' . $companyGroup->id,
            'description' => 'nullable|string',
            'active'      => 'boolean',
            'company_ids' => 'array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $companyGroup->update($validated);

        if (isset($validated['company_ids'])) {
            $companyGroup->companies()->sync($validated['company_ids']);
        }

        return redirect()->route('super-admin.company-groups.index')
            ->with('success', 'グループ会社を更新しました');
    }

    public function destroy(CompanyGroup $companyGroup)
    {
        $companyGroup->delete();

        return redirect()->route('super-admin.company-groups.index')
            ->with('success', 'グループ会社を削除しました');
    }
}
