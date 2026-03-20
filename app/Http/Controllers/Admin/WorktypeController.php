<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Worktype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorktypeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isSuperAdmin = ($user->user_role ?? '') === 'superadmin';

        if ($isSuperAdmin) {
            // 全会社を取得し、会社ごとにグループ化
            $companies = Company::orderBy('id')->get();
            $groups = $companies->map(function ($company) {
                return [
                    'company_id'   => $company->id,
                    'company_name' => $company->name,
                    'worktypes'    => Worktype::where('company_id', $company->id)
                        ->orderBy('sort_order')->get(),
                ];
            });

            return Inertia::render('Admin/Worktype/Index', [
                'groups'       => $groups,
                'is_superadmin' => true,
                'company_name' => null,
            ]);
        }

        // 通常 Admin: 自社のみ
        $companyId = $user->company_id;
        $company   = Company::find($companyId);
        $worktypes = Worktype::where('company_id', $companyId)
            ->orderBy('sort_order')->get();

        return Inertia::render('Admin/Worktype/Index', [
            'groups'        => null,
            'is_superadmin' => false,
            'company_name'  => $company?->name,
            'worktypes'     => $worktypes,
        ]);
    }

    public function edit(Request $request)
    {
        $user         = Auth::user();
        $isSuperAdmin = ($user->user_role ?? '') === 'superadmin';
        $companyId    = $isSuperAdmin
            ? (int) $request->query('company_id', $user->company_id)
            : $user->company_id;

        $company   = Company::find($companyId);
        $worktypes = Worktype::where('company_id', $companyId)
            ->orderBy('sort_order')->get();

        return Inertia::render('Admin/Worktype/Edit', [
            'worktypes'    => $worktypes,
            'company_id'   => $companyId,
            'company_name' => $company?->name,
        ]);
    }

    public function update(Request $request)
    {
        $rows = $request->validate([
            'rows'              => 'required|array',
            'rows.*.id'         => 'nullable|integer|exists:worktypes,id',
            'rows.*.name'       => 'nullable|string|max:50',
            'rows.*.start_time' => 'required|date_format:H:i',
            'rows.*.end_time'   => 'required|date_format:H:i',
            'rows.*.sort_order' => 'nullable|integer',
            'rows.*._deleted'   => 'nullable|boolean',
        ])['rows'];

        $user         = Auth::user();
        $isSuperAdmin = ($user->user_role ?? '') === 'superadmin';
        $companyId    = $isSuperAdmin
            ? (int) $request->input('company_id', $user->company_id)
            : $user->company_id;

        foreach ($rows as $row) {
            $deleted = !empty($row['_deleted']);

            if (!empty($row['id'])) {
                if ($deleted) {
                    Worktype::where('id', $row['id'])->where('company_id', $companyId)->delete();
                } else {
                    Worktype::where('id', $row['id'])->where('company_id', $companyId)->update([
                        'name'       => $row['name'] ?? '',
                        'start_time' => $row['start_time'] . ':00',
                        'end_time'   => $row['end_time']   . ':00',
                        'sort_order' => $row['sort_order'] ?? 0,
                    ]);
                }
            } elseif (!$deleted) {
                Worktype::create([
                    'company_id' => $companyId,
                    'name'       => $row['name'] ?? '',
                    'start_time' => $row['start_time'] . ':00',
                    'end_time'   => $row['end_time']   . ':00',
                    'sort_order' => $row['sort_order'] ?? 0,
                ]);
            }
        }

        return redirect()->route('admin.worktypes.index');
    }
}
