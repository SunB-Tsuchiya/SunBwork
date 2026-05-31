<?php

namespace App\Http\Controllers;

use App\Models\LeaderPermission;
use App\Models\Script;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ScriptController extends Controller
{
    private function checkAccess(): void
    {
        $user = Auth::user();
        $role = $user->user_role;

        if (in_array($role, ['superadmin', 'admin'])) {
            return;
        }

        if ($role === 'leader') {
            $ok = LeaderPermission::where('user_id', $user->id)->value('script_access');
            if ($ok) {
                return;
            }
        }

        abort(403, 'スクリプトへのアクセス権限がありません。');
    }

    public function index()
    {
        $this->checkAccess();

        $user = Auth::user();
        $role = $user->user_role;

        if (in_array($role, ['superadmin', 'admin'])) {
            // SuperAdmin・Admin は全アクティブスクリプトを表示
            $scripts = Script::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'component_key', 'sort_order']);
        } else {
            // その他は個人割り当て済みスクリプトのみ
            $scripts = Script::where('is_active', true)
                ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'component_key', 'sort_order']);
        }

        return Inertia::render('Scripts/Index', [
            'scripts' => $scripts,
        ]);
    }

    public function show(Script $script)
    {
        $this->checkAccess();

        if (! $script->is_active) {
            abort(404);
        }

        return Inertia::render('Scripts/Show', [
            'script' => $script->only(['id', 'name', 'slug', 'description', 'component_key']),
        ]);
    }
}
