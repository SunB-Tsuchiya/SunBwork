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
        $request->validate([
            'name'         => 'required|string|max:255',
            'company_type' => 'required|in:sunbrain,general',
            'departments'              => 'array',
            'departments.*.name'       => 'required|string|max:255',
            'departments.*.module'     => 'nullable|in:publishing,prepress,ondemand',
            'departments.*.assignments'       => 'array',
            'departments.*.assignments.*.name' => 'required|string|max:255',
        ]);

        $base = Str::slug($request->name);
        $code = $base;
        $i = 1;
        while (Company::where('code', $code)->exists()) {
            $code = $base . '-' . $i++;
        }

        $company = Company::create([
            'name'         => $request->name,
            'code'         => $code,
            'company_type' => $request->company_type,
        ]);

        foreach ($request->input('departments', []) as $depData) {
            $department = $company->departments()->create([
                'name'   => $depData['name'],
                'code'   => $this->generateCode($depData['code'] ?? '', 'DEPT'),
                'module' => $depData['module'] ?? null,
            ]);
            foreach ($depData['assignments'] ?? [] as $assignmentData) {
                $department->assignments()->create([
                    'name' => $assignmentData['name'],
                    'code' => $this->generateCode($assignmentData['code'] ?? '', 'asgn'),
                ]);
            }
        }

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
            'company_type'             => 'required|in:sunbrain,general',
            'representative_id'        => 'nullable|exists:users,id',
            'representative_leader_id' => 'nullable|exists:users,id',
            'departments'              => 'array',
            'departments.*.name'       => 'required|string|max:255',
            'departments.*.module'     => 'nullable|in:publishing,prepress,ondemand',
            'departments.*.assignments'       => 'array',
            'departments.*.assignments.*.name' => 'required|string|max:255',
        ]);

        $company->update([
            'name'                     => $request->name,
            'company_type'             => $request->company_type,
            'representative_id'        => $request->input('representative_id'),
            'representative_leader_id' => $request->input('representative_leader_id'),
        ]);

        foreach ($request->departments as $depData) {
            $department = isset($depData['id']) ? $company->departments()->find($depData['id']) : null;
            if ($department) {
                $department->update([
                    'name'   => $depData['name'],
                    'module' => $depData['module'] ?? null,
                ]);
            } else {
                $department = $company->departments()->create([
                    'name'   => $depData['name'],
                    'code'   => $this->generateCode($depData['code'] ?? '', 'DEPT'),
                    'module' => $depData['module'] ?? null,
                ]);
            }

            foreach ($depData['assignments'] as $assignmentsData) {
                $assignments = isset($assignmentsData['id']) ? $department->assignments()->find($assignmentsData['id']) : null;
                if ($assignments) {
                    $assignments->update(['name' => $assignmentsData['name']]);
                } else {
                    $department->assignments()->create([
                        'name' => $assignmentsData['name'],
                        'code' => $this->generateCode($assignmentsData['code'] ?? '', 'asgn'),
                    ]);
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

    /**
     * コード文字列を生成する。入力があればそれを使い、なければ prefix + uniqid() で自動生成。
     */
    private function generateCode(string $input, string $prefix): string
    {
        $input = trim($input);
        if ($input !== '') {
            return $input;
        }
        return $prefix . '_' . substr(uniqid(), -6);
    }
}
