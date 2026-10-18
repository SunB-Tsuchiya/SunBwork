<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Admin;

use App\Http\Controllers\Controller;
use App\Models\SunA\GeneralAffairs\Nencho\MyNumberAccessLog;
use App\Models\SunA\GeneralAffairs\Nencho\StaffPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * サンエー印刷 管理者: 社員情報権限管理（サン・ブレーンの Leader権限管理と同じ作り）と、マイナンバー閲覧記録
 */
class StaffPermissionController extends Controller
{
    public function index()
    {
        $perms = StaffPermission::all()->keyBy('user_id');

        return Inertia::render('SunA/GeneralAffairs/Admin/StaffPermissions/Index', [
            'staff' => $this->staffQuery()->with('company:id,name')->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->user_role,
                'company' => $u->company?->name,
                'permissions' => $perms->get($u->id)?->only(array_keys(StaffPermission::ABILITIES)) ?? array_fill_keys(array_keys(StaffPermission::ABILITIES), false),
            ]),
            'abilities' => StaffPermission::ABILITIES,
        ]);
    }

    public function edit(User $staff)
    {
        $this->checkStaff($staff);
        $perm = StaffPermission::where('user_id', $staff->id)->first();

        return Inertia::render('SunA/GeneralAffairs/Admin/StaffPermissions/Edit', [
            'staff' => ['id' => $staff->id, 'name' => $staff->name, 'email' => $staff->email, 'role' => $staff->user_role],
            'permissions' => $perm?->only(array_keys(StaffPermission::ABILITIES)) ?? array_fill_keys(array_keys(StaffPermission::ABILITIES), false),
            'abilities' => StaffPermission::ABILITIES,
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->checkStaff($staff);
        $rules = array_fill_keys(array_keys(StaffPermission::ABILITIES), ['required', 'boolean']);
        $data = $request->validate($rules);
        StaffPermission::updateOrCreate(['user_id' => $staff->id], $data + ['updated_by' => Auth::id()]);

        return to_route('admin.general_affairs.staff_permissions.index')->with('success', "{$staff->name}さんの権限を保存しました。");
    }

    /** マイナンバー閲覧記録 */
    public function logs()
    {
        return Inertia::render('SunA/GeneralAffairs/Admin/StaffPermissions/MyNumberLogs', [
            'logs' => MyNumberAccessLog::orderByDesc('accessed_at')->orderByDesc('id')->paginate(50)->through(fn ($l) => [
                'id' => $l->id,
                'accessed_at' => $l->accessed_at?->timezone('Asia/Tokyo')->format('Y-m-d H:i:s'),
                'viewer_name' => $l->viewer_name,
                'employee_name' => $l->employee_name,
                'action' => $l->action,
                'ip_address' => $l->ip_address,
            ]),
            'actions' => MyNumberAccessLog::ACTIONS,
        ]);
    }

    /** 権限を設定する対象: サンエー印刷（general）の総務・経理(clerk)と管理者、および SuperAdmin */
    private function staffQuery()
    {
        return User::query()->where(function ($q) {
            $q->where('user_role', 'superadmin')
                ->orWhere(function ($q2) {
                    $q2->whereIn('user_role', ['clerk', 'admin'])
                        ->whereHas('company', fn ($c) => $c->where('company_type', 'general'));
                });
        });
    }

    private function checkStaff(User $staff): void
    {
        abort_unless($this->staffQuery()->whereKey($staff->id)->exists(), 404);
    }
}
