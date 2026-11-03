<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Services\WorkJobReader;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkJobDetailController extends Controller
{
    public function show(Request $request, int $entry, WorkJobReader $jobs)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        $data = $jobs->detail($request->user(), $entry, $context === null ? null : (int) $context);
        $back = $request->validate([
            'from' => ['nullable', 'in:box,calendar'], 'period' => ['nullable', 'regex:/^(all|[0-9]{4}-(0[1-9]|1[0-2]))$/'],
            'q' => ['nullable', 'string', 'max:200'], 'page' => ['nullable', 'integer', 'min:1'], 'hide_completed' => ['nullable', 'boolean'],
        ]);
        return Inertia::render('MyJobBox/WorkDetail', $data + ['back' => $back]);
    }

    public function destroy(Request $request, int $entry, WorkJobReader $jobs, DepartmentWorkController $entries)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        $job = $jobs->resolve($request->user(), 'entry', $entry, $context === null ? null : (int) $context);
        return $entries->destroy($request, $job);
    }

    public function update(Request $request, int $entry, WorkJobReader $jobs, DepartmentWorkController $entries)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        $job = $jobs->resolve($request->user(), 'entry', $entry, $context === null ? null : (int) $context);
        return $entries->update($request, $job);
    }
}
