<?php

namespace App\Http\Controllers\SunA\GeneralAffairs;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Models\SunA\GeneralAffairs\ApplicationType;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * サンエー印刷 総務: 申請一覧（有効な申請の種類を枠で表示）
 */
class ApplicationController extends Controller
{
    use ResolvesContextCompany;

    public function index()
    {
        $companyId = $this->contextCompanyId() ?? Auth::user()->company_id;
        abort_unless($companyId, 403, '会社を選択してください。');

        return Inertia::render('SunA/GeneralAffairs/Applications/Index', [
            'types' => ApplicationType::forCompany((int) $companyId)
                ->where('is_active', true)
                ->ordered()
                ->get(['id', 'name', 'description', 'feature_key']),
        ]);
    }
}
