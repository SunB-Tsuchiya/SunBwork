<?php

namespace App\Http\Controllers\NSystem;

use App\Http\Controllers\Controller;
use App\Models\DemoPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class GuestAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $slug = $request->get('for', 'n-demo');

        if (session("nsystem_demo_auth.{$slug}") === true) {
            return redirect($this->defaultRedirect($slug));
        }

        // 非ログインユーザーのみ: Inertia SPA セッションとの競合を防ぐため
        // セッション ID を再生成する（セッションデータ・CSRF トークンは引き継ぐ）
        if (! \Illuminate\Support\Facades\Auth::check()) {
            $intended = session('intended');
            $request->session()->regenerate();
            if ($intended) {
                session(['intended' => $intended]);
            }
        }

        return view('n_system.guest.login', compact('slug'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $slug = $request->input('for', 'n-demo');

        // ブルートフォース対策（1分間に5回まで）
        $key = 'nsystem-guest-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "試行回数が多すぎます。{$seconds}秒後に再試行してください。"]);
        }

        $page = DemoPage::where('slug', $slug)->first();

        if (! $page) {
            return back()->withErrors(['email' => 'このページは存在しません。']);
        }
        if (! $page->is_active) {
            return back()->withErrors(['email' => 'このページは現在公開されていません。']);
        }
        if ($page->expires_at && $page->expires_at->isPast()) {
            return back()->withErrors(['email' => '公開期限が終了しています。']);
        }

        // メールアドレス確認
        if (! $page->emails()->where('email', $request->email)->exists()) {
            RateLimiter::hit($key, 60);
            return back()->withErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        // パスワード確認
        if (! Hash::check($request->password, $page->password)) {
            RateLimiter::hit($key, 60);
            return back()->withErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        session(["nsystem_demo_auth.{$slug}" => true]);

        $intended = session('intended', $this->defaultRedirect($slug));
        session()->forget('intended');

        return redirect($intended);
    }

    public function logout(Request $request)
    {
        $slug = $request->input('slug', 'n-demo');
        session()->forget("nsystem_demo_auth.{$slug}");
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('n-guest.login', ['for' => $slug]);
    }

    private function defaultRedirect(string $slug): string
    {
        try {
            return route("n-demo.index");
        } catch (\Exception) {
            return '/';
        }
    }
}
