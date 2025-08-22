<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with(['departments.assignments'])->get();
        return Inertia::render('SuperAdmin/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Companies/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        Company::create($request->only('name'));
        return redirect()->route('superadmin.companies.index');
    }

    public function edit(Company $company)
    {
        $company->load('departments.assignments');
        return Inertia::render('SuperAdmin/Companies/Edit', [
            'company' => $company,
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'departments' => 'array',
            'departments.*.name' => 'required|string|max:255',
            'departments.*.assignments' => 'array',
            'departments.*.assignments.*.name' => 'required|string|max:255',
        ]);

        $company->update(['name' => $request->name]);

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
