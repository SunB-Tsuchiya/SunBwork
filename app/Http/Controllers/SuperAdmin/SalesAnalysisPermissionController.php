<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SalesAnalysisPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SalesAnalysisPermissionController extends Controller
{
    /** 個人許可の候補は Admin / Clerk のみ。Leader は対象外 */
    private const ELIGIBLE_ROLES = ['admin', 'clerk'];

    public function index()
    {
        $candidates = User::whereIn('user_role', self::ELIGIBLE_ROLES)
            ->with('salesAnalysisPermission')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $perm = $user->salesAnalysisPermission;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_role' => $user->user_role,
                    'enabled' => $perm->enabled ?? false,
                ];
            });

        return Inertia::render('SuperAdmin/SalesAnalysisPermissions/Index', [
            'candidates' => $candidates,
        ]);
    }

    public function update(Request $request, User $user)
    {
        if (! in_array($user->user_role, self::ELIGIBLE_ROLES, true)) {
            abort(422, '対象ユーザーが不正です。');
        }

        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $currentUser = Auth::user();

        SalesAnalysisPermission::updateOrCreate(
            ['user_id' => $user->id],
            [
                'enabled' => $data['enabled'],
                'granted_by' => $currentUser->id,
                'granted_at' => $data['enabled'] ? now() : null,
            ]
        );

        return back()->with('success', '許可設定を保存しました');
    }
}
