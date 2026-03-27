<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Exception $e) {
            \Log::error('Password reset mail failed: ' . $e->getMessage());

            return back()->withErrors(['email' => 'メール送信に失敗しました。管理者にお問い合わせください。']);
        }

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
