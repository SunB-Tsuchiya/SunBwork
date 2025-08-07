<?php

use Illuminate\Support\Facades\Route;
use App\Models\Company;

// デバッグ用：登録ページのデータ構造を確認
Route::get('/debug/register-data', function () {
    $companies = Company::with([
        'departments' => function ($query) {
            $query->where('active', 1)
                ->with(['roles' => function ($roleQuery) {
                    $roleQuery->where('active', 1)->orderBy('sort_order');
                }])
                ->orderBy('sort_order');
        }
    ])->where('active', 1)->get();

    return response()->json([
        'companies' => $companies,
        'debug_info' => [
            'companies_count' => $companies->count(),
            'first_company_name' => $companies->first() ? $companies->first()->name : null,
            'first_company_departments_count' => $companies->first() ? $companies->first()->departments->count() : 0,
            'first_department_name' => $companies->first() && $companies->first()->departments->first()
                ? $companies->first()->departments->first()->name : null,
            'first_department_roles_count' => $companies->first() && $companies->first()->departments->first()
                ? $companies->first()->departments->first()->roles->count() : 0,
            'sample_department_structure' => $companies->first() && $companies->first()->departments->first()
                ? array_keys($companies->first()->departments->first()->toArray()) : [],
        ]
    ]);
});
