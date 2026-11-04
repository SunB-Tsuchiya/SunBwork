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

        if ($request->filled('reuse_entry_id')) {
            $id = $request->validate(['reuse_entry_id' => ['required', 'integer', 'min:1']])['reuse_entry_id'];
            $source = app(\App\Services\WorkJobReader::class)->resolve($request->user(), 'entry', (int) $id, $context === null ? null : (int) $context);
            $user = $request->user();
            $data = $entries->validatedEntry($request, $user, false, null, $source->project_job_id ? (int) $source->project_job_id : null);
            $entry = \App\Models\PersonalWorkEntry::create($data + ['user_id' => $user->id, 'company_id' => $user->company_id, 'department_id' => $user->department_id]);
            return response()->json(['id' => $entry->id], 201);
        }

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
