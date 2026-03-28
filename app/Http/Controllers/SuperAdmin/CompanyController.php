<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with(['departments.assignments', 'representative'])->get();
        return Inertia::render('SuperAdmin/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function show(Company $company)
    {
        $company->load('departments.assignments', 'representative');
        return Inertia::render('SuperAdmin/Companies/Show', [
            'company' => $company,
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Companies/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Generate a slug-like unique code from the name (used as unique identifier)
        $base = Str::slug($validated['name']);
        $code = $base;
        $i = 1;
        while (Company::where('code', $code)->exists()) {
            $code = $base . '-' . $i++;
        }

        Company::create([
            'name' => $validated['name'],
            'code' => $code,
        ]);

        return redirect()->route('superadmin.companies.index');
    }

    public function edit(Company $company)
    {
        $company->load('departments.assignments');
        $adminUsers = User::where('company_id', $company->id)
            ->where('user_role', 'admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $leaderUsers = User::where('company_id', $company->id)
            ->where('user_role', 'leader')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        return Inertia::render('SuperAdmin/Companies/Edit', [
            'company'     => $company,
            'adminUsers'  => $adminUsers,
            'leaderUsers' => $leaderUsers,
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name'                     => 'required|string|max:255',
            'representative_id'        => 'nullable|exists:users,id',
            'representative_leader_id' => 'nullable|exists:users,id',
            'departments'              => 'array',
            'departments.*.name'       => 'required|string|max:255',
            'departments.*.assignments'       => 'array',
            'departments.*.assignments.*.name' => 'required|string|max:255',
        ]);

        $company->update([
            'name'                     => $request->name,
            'representative_id'        => $request->input('representative_id'),
            'representative_leader_id' => $request->input('representative_leader_id'),
        ]);

        foreach ($request->departments as $depData) {
            $department = isset($depData['id']) ? $company->departments()->find($depData['id']) : null;
            if ($department) {
                $department->update(['name' => $depData['name']]);
            } else {
                $department = $company->departments()->create(['name' => $depData['name']]);
            }

            foreach ($depData['assignments'] as $assignmentsData) {
                $assignments = isset($assignmentsData['id']) ? $department->assignments()->find($assignmentsData['id']) : null;
                if ($assignments) {
                    $assignments->update(['name' => $assignmentsData['name']]);
                } else {
                    $department->assignments()->create(['name' => $assignmentsData['name']]);
                }
            }
        }

        return redirect()->route('superadmin.companies.index');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('superadmin.companies.index');
    }
}
