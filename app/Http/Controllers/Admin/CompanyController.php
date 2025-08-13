<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    // 一覧
    public function index()
    {
        $companies = Company::with(['departments.assignments'])->get();
        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    // 新規作成フォーム
    public function create()
    {
        return Inertia::render('Admin/Companies/Create');
    }

    // 登録
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        Company::create($request->only('name'));
        return redirect()->route('admin.companies.index');
    }

    // 編集フォーム
    public function edit(Company $company)
    {
        $company->load('departments.assignments');
        return Inertia::render('Admin/Companies/Edit', [
            'company' => $company,
        ]);
    }

    // 更新
    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'departments' => 'array',
            'departments.*.name' => 'required|string|max:255',
            'departments.*.assignments' => 'array',
            'departments.*.assignments.*.name' => 'required|string|max:255',
        ]);

        // 会社名更新
        $company->update(['name' => $request->name]);

        // 部署の更新・追加
        foreach ($request->departments as $depData) {
            $department = isset($depData['id']) ? $company->departments()->find($depData['id']) : null;
            if ($department) {
                $department->update(['name' => $depData['name']]);
            } else {
                $department = $company->departments()->create(['name' => $depData['name']]);
            }

            // 担当（役割）の更新・追加
            foreach ($depData['assignments'] as $assignmentsData) {
                $assignments = isset($assignmentsData['id']) ? $department->assignments()->find($assignmentsData['id']) : null;
                if ($assignments) {
                    $assignments->update(['name' => $assignmentsData['name']]);
                } else {
                    $department->assignments()->create(['name' => $assignmentsData['name']]);
                }
            }
        }

        return redirect()->route('admin.companies.index');
    }

    // 削除
    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('admin.companies.index');
    }
}
