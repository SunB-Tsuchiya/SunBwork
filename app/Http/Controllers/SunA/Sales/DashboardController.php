<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * 営業のダッシュボード（経理・総務のクラークのダッシュボードと同じ作り。イルカボード）。
 * ルートは suna_sales_report:view で保護（許可された人・サンエー印刷のみ）。
 */
class DashboardController extends Controller
{
    use ResolvesContextCompany;

    public function index()
    {
        $user = Auth::user();
        // SuperAdmin は切替中の会社、それ以外は自社の部署一覧（クラークのダッシュボードと同じ）
        $companyId = $user->isSuperAdmin() ? $this->contextCompanyId() : $user->company_id;
        $departments = $companyId
            ? Department::where('company_id', $companyId)->orderBy('sort_order')->get(['id', 'name'])->toArray()
            : [];

        return Inertia::render('SunA/Sales/Dashboard', [
            'user' => $user,
            'departments' => $departments,
            'userDepartmentId' => $user->department_id,
        ]);
    }
}
