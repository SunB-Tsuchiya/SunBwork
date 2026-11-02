<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\UnifiedMyJobBox;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnifiedMyJobBoxController extends Controller
{
    public function index(Request $request, UnifiedMyJobBox $box)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        abort_unless($box->available($request->user(), $context === null ? null : (int) $context), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'period' => ['nullable', 'regex:/^(all|[0-9]{4}-(0[1-9]|1[0-2]))$/'],
            'hide_completed' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        return Inertia::render('MyJobBox/UnifiedPreview', $box->listing($request->user(), $context === null ? null : (int) $context, $filters));
    }
}
