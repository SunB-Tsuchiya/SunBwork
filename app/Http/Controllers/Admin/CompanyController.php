<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    // 一覧
    public function index()
    {
        $user = Auth::user();
        // superadmin は全会社を閲覧可能、それ以外は所属会社のみ
        if ($user && $user->user_role === 'superadmin') {
            $companies = Company::with(['departments.assignments'])->get();
        } else {
            if ($user && $user->company_id) {
                $companies = Company::with(['departments.assignments'])
                    ->where('id', $user->company_id)
                    ->get();
            } else {
                $companies = collect([]);
            }
        }

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
        $user = Auth::user();
    if (!($user && $user->user_role === 'superadmin') && $user->company_id !== $company->id) {
            abort(403);
        }

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

        // 権限チェック: superadmin でない場合は所属会社以外の更新を禁止
    $user = Auth::user();
    if (!($user && $user->user_role === 'superadmin') && $user->company_id !== $company->id) {
            abort(403);
        }

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
    $user = Auth::user();
    if (!($user && $user->user_role === 'superadmin') && $user->company_id !== $company->id) {
            abort(403);
        }

        $company->delete();
        return redirect()->route('admin.companies.index');
    }
}
