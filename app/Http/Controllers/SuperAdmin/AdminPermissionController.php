<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPermissionController extends Controller
{
    /** Admin ユーザー一覧と権限を表示 */
    public function index()
    {
        $admins = User::where('user_role', 'admin')
            ->with('adminPermission')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $perm = $user->adminPermission;
                return [
                    'id'                     => $user->id,
                    'name'                   => $user->name,
                    'email'                  => $user->email,
                    'company_management'     => $perm?->company_management ?? false,
                    'user_management'        => $perm?->user_management ?? false,
                    'team_management'        => $perm?->team_management ?? false,
                    'diary_management'       => $perm?->diary_management ?? false,
                    'client_management'      => $perm?->client_management ?? false,
                    'workload_analysis'      => $perm?->workload_analysis ?? false,
                    'worktype_setting'       => $perm?->worktype_setting ?? false,
                    'work_record_management' => $perm?->work_record_management ?? false,
                ];
            });

        return Inertia::render('SuperAdmin/AdminPermissions/Index', [
            'admins' => $admins,
        ]);
    }

    /** 特定 Admin の権限編集フォーム */
    public function edit(User $adminuser)
    {
        $perm = AdminPermission::firstOrNew(['user_id' => $adminuser->id]);

        return Inertia::render('SuperAdmin/AdminPermissions/Edit', [
            'admin' => [
                'id'    => $adminuser->id,
                'name'  => $adminuser->name,
                'email' => $adminuser->email,
            ],
            'permissions' => [
                'company_management'     => $perm->company_management ?? false,
                'user_management'        => $perm->user_management ?? false,
                'team_management'        => $perm->team_management ?? false,
                'diary_management'       => $perm->diary_management ?? false,
                'client_management'      => $perm->client_management ?? false,
                'workload_analysis'      => $perm->workload_analysis ?? false,
                'worktype_setting'       => $perm->worktype_setting ?? false,
                'work_record_management' => $perm->work_record_management ?? false,
            ],
        ]);
    }

    /** 権限を保存 */
    public function update(Request $request, User $adminuser)
    {
        $data = $request->validate([
            'company_management'     => 'boolean',
            'user_management'        => 'boolean',
            'team_management'        => 'boolean',
            'diary_management'       => 'boolean',
            'client_management'      => 'boolean',
            'workload_analysis'      => 'boolean',
            'worktype_setting'       => 'boolean',
            'work_record_management' => 'boolean',
        ]);

        AdminPermission::updateOrCreate(
            ['user_id' => $adminuser->id],
            $data
        );

        return redirect()->route('superadmin.admin_permissions.index')
            ->with('success', '権限設定を保存しました');
    }
}
