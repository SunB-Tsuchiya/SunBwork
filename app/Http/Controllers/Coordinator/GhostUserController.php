<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GhostUserController extends Controller
{
    public function guide()
    {
        return Inertia::render('Coordinator/GhostUsers/Guide');
    }

    public function index()
    {
        $ghosts = User::withGhosts()
            ->where('ghost_owner_id', Auth::id())
            ->get(['id', 'name', 'ghost_expires_at', 'created_at']);

        return Inertia::render('Coordinator/GhostUsers/Index', [
            'ghosts' => $ghosts,
        ]);
    }

    public function store(Request $request)
    {
        $coordinator = Auth::user();

        $existing = User::withGhosts()
            ->where('ghost_owner_id', $coordinator->id)
            ->count();

        if ($existing >= 1) {
            return back()->withErrors(['ghost' => 'テストユーザーはすでに作成済みです。削除してから再作成してください。']);
        }

        User::create([
            'name'              => 'テスト_' . $coordinator->name,
            'email'             => 'ghost_' . Str::random(8) . '@ghost.local',
            'password'          => bcrypt(Str::random(32)),
            'user_role'         => 'user',
            'is_ghost'          => true,
            'ghost_owner_id'    => $coordinator->id,
            'ghost_expires_at'  => now()->addDays(14),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'テストユーザーを作成しました。');
    }

    public function destroy(int $ghostUserId)
    {
        $ghost = User::withGhosts()
            ->where('id', $ghostUserId)
            ->where('ghost_owner_id', Auth::id())
            ->firstOrFail();

        DB::transaction(function () use ($ghost) {
            DB::table('project_job_assignments')->where('user_id', $ghost->id)->delete();
            DB::table('events')->where('user_id', $ghost->id)->delete();
            $ghost->delete();
        });

        return back()->with('success', 'テストユーザーを削除しました。');
    }

    public function switch(int $ghostUserId)
    {
        $ghost = User::withGhosts()
            ->where('id', $ghostUserId)
            ->where('ghost_owner_id', Auth::id())
            ->firstOrFail();

        session()->put('ghost_return_user_id', Auth::id());

        // Update the web SessionGuard (writes ghost's ID into the session store)
        Auth::guard('web')->login($ghost);

        // auth:sanctum called Auth::shouldUse('sanctum') earlier in this request,
        // so $request->user() resolves through Sanctum's RequestGuard which cached
        // the coordinator. AuthenticateSession's tap() closure calls
        // storePasswordHashInSession() using $request->user() — without this
        // setUser(), the coordinator's hash gets written into password_hash_sanctum,
        // causing a mismatch-logout on the very next request.
        Auth::guard('sanctum')->setUser($ghost);

        // login() migrates the session (new session ID). On Sakura, XSRF-TOKEN cookie
        // is not issued, so soft navigation cannot refresh the CSRF meta tag.
        // Inertia::location() forces a full page reload so app.blade.php re-renders
        // with the new CSRF token — same pattern as AuthenticatedSessionController.
        return Inertia::location(route('user.myjobbox.index'));
    }

    public function exit()
    {
        $returnId = session()->pull('ghost_return_user_id');

        if (!$returnId) {
            abort(403, 'ゴーストセッションではありません。');
        }

        $coordinator = User::find($returnId);
        if (!$coordinator) {
            abort(403, '復帰先のユーザーが見つかりません。');
        }

        Auth::guard('web')->login($coordinator);
        // Same reason as switch(): $request->user() uses Sanctum's cached ghost,
        // so the tap() closure would write ghost's hash. Override it here.
        Auth::guard('sanctum')->setUser($coordinator);

        // Same as switch(): force full reload to refresh CSRF token in meta tag.
        return Inertia::location(route('coordinator.project_jobs.index'));
    }
}
