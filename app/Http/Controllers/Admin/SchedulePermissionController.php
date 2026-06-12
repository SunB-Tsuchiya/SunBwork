<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchedulePermissionSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SchedulePermissionController extends Controller
{
    public function edit()
    {
        $user    = Auth::user();
        $setting = SchedulePermissionSetting::firstOrCreate(
            ['company_id' => $user->company_id],
            ['can_add_to_others_min_role' => 'coordinator']
        );

        return Inertia::render('Admin/SchedulePermissions/Edit', ['setting' => $setting]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'can_add_to_others_min_role' => 'required|in:coordinator,leader,admin,superadmin',
        ]);

        $user = Auth::user();
        SchedulePermissionSetting::updateOrCreate(
            ['company_id' => $user->company_id],
            ['can_add_to_others_min_role' => $request->can_add_to_others_min_role]
        );

        return redirect()->route('admin.schedule-settings.edit')
            ->with('success', '権限設定を更新しました');
    }
}
