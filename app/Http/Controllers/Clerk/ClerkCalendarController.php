<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClerkCalendarController extends Controller
{
    use ResolvesContextCompany;

    public function index()
    {
        $companyId = $this->contextCompanyId() ?? Auth::user()->company_id;
        abort_unless($companyId, 403, '会社を選択してください。');

        return Inertia::render('Clerk/Calendar/Index');
    }
}
