<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Services\UnifiedMyJobBox;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnifiedMyJobBoxController extends Controller
{
    public function store(Request $request, UnifiedMyJobBox $box, DepartmentWorkController $entries)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        abort_unless($box->available($request->user(), $context === null ? null : (int) $context), 403);

        // Reuse the calendar's validation and owner-fixed save without copying work.
        return $entries->store($request);
    }

    public function index(Request $request, UnifiedMyJobBox $box)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        abort_unless($box->available($request->user(), $context === null ? null : (int) $context), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'period' => ['nullable', 'regex:/^(all|[0-9]{4}-(0[1-9]|1[0-2]))$/'],
            'created_entry' => ['nullable', 'integer', 'min:1'],
            'hide_completed' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        return Inertia::render('MyJobBox/UnifiedPreview', $box->listing($request->user(), $context === null ? null : (int) $context, $filters));
    }
}
