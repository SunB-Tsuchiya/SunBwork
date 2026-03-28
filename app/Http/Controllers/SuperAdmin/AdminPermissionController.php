<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminPermissionController extends Controller
{
    /** Admin ユーザー一覧と権限を表示 */
    public function index()
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser->isSuperAdmin();

        $query = User::where('user_role', 'admin')
            ->with('adminPermission')
            ->orderBy('name');

        // 代表者 Admin は自社の Admin のみ表示
        if (! $isSuperAdmin) {
            $query->where('company_id', $currentUser->company_id);
        }

        $admins = $query->get()->map(function ($user) {
            $perm = $user->adminPermission;
            return [
                'id'                     => $user->id,
                'name'                   => $user->name,
                'email'                  => $user->email,
                'company_management'     => $perm?->company_management ?? true,
                'user_management'        => $perm?->user_management ?? true,
                'team_management'        => $perm?->team_management ?? true,
                'diary_management'       => $perm?->diary_management ?? true,
                'client_management'      => $perm?->client_management ?? true,
                'workload_analysis'      => $perm?->workload_analysis ?? true,
                'worktype_setting'       => $perm?->worktype_setting ?? true,
                'work_record_management' => $perm?->work_record_management ?? true,
            ];
        });

        $indexRoute = $isSuperAdmin ? 'superadmin.admin_permissions.index' : 'admin.admin_permissions.index';
        $editRoute  = $isSuperAdmin ? 'superadmin.admin_permissions.edit'  : 'admin.admin_permissions.edit';

        return Inertia::render('SuperAdmin/AdminPermissions/Index', [
            'admins'     => $admins,
            'indexRoute' => $indexRoute,
            'editRoute'  => $editRoute,
        ]);
    }

    /** 特定 Admin の権限編集フォーム */
    public function edit(User $adminuser)
    {
        $currentUser  = Auth::user();
        $isSuperAdmin = $currentUser->isSuperAdmin();

        // 代表者 Admin は自社の Admin のみ編集可能
        if (! $isSuperAdmin && $adminuser->company_id !== $currentUser->company_id) {
            abort(403);
        }

        $perm = AdminPermission::firstOrNew(['user_id' => $adminuser->id]);

        $updateRoute = $isSuperAdmin ? 'superadmin.admin_permissions.update' : 'admin.admin_permissions.update';
        $indexRoute  = $isSuperAdmin ? 'superadmin.admin_permissions.index'  : 'admin.admin_permissions.index';

        return Inertia::render('SuperAdmin/AdminPermissions/Edit', [
            'admin' => [
                'id'    => $adminuser->id,
                'name'  => $adminuser->name,
                'email' => $adminuser->email,
            ],
            'permissions' => [
                'company_management'     => $perm->company_management ?? true,
                'user_management'        => $perm->user_management ?? true,
                'team_management'        => $perm->team_management ?? true,
                'diary_management'       => $perm->diary_management ?? true,
                'client_management'      => $perm->client_management ?? true,
                'workload_analysis'      => $perm->workload_analysis ?? true,
                'worktype_setting'       => $perm->worktype_setting ?? true,
                'work_record_management' => $perm->work_record_management ?? true,
            ],
            'updateRoute' => $updateRoute,
            'indexRoute'  => $indexRoute,
        ]);
    }

    /** 権限を保存 */
    public function update(Request $request, User $adminuser)
    {
        $currentUser  = Auth::user();
        $isSuperAdmin = $currentUser->isSuperAdmin();

        // 代表者 Admin は自社の Admin のみ更新可能
        if (! $isSuperAdmin && $adminuser->company_id !== $currentUser->company_id) {
            abort(403);
        }

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

        $redirectRoute = $isSuperAdmin
            ? 'superadmin.admin_permissions.index'
            : 'admin.admin_permissions.index';

        return redirect()->route($redirectRoute)
            ->with('success', '権限設定を保存しました');
    }
}
