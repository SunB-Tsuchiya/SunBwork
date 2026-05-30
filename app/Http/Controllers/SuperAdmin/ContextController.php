<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ContextController extends Controller
{
    /**
     * SuperAdmin のコンテキスト（操作対象会社）を切り替える。
     * company_id = null → グローバル管理モード
     * company_id = ID  → 指定会社の Admin として操作
     */
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $companyId = $request->input('company_id');

        if ($companyId !== null) {
            $company = Company::findOrFail((int) $companyId);
            session(['superadmin_context' => ['company_id' => $company->id]]);
        } else {
            session(['superadmin_context' => ['company_id' => null]]);
        }

        return back();
    }
}
