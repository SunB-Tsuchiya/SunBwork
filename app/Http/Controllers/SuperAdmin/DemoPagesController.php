<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\DemoPageUpdated;
use App\Models\DemoPage;
use App\Models\DemoPageEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class DemoPagesController extends Controller
{
    public function index()
    {
        $pages = DemoPage::withCount('emails')
            ->with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'slug'         => $p->slug,
                'is_active'    => $p->is_active,
                'expires_at'   => $p->expires_at?->format('Y-m-d H:i'),
                'emails_count' => $p->emails_count,
                'is_expired'   => $p->expires_at && $p->expires_at->isPast(),
                'creator_name' => $p->creator?->name,
            ]);

        return Inertia::render('SuperAdmin/DemoPages/Index', compact('pages'));
    }

    public function show(DemoPage $demoPage)
    {
        $demoPage->load('emails');

        return Inertia::render('SuperAdmin/DemoPages/Show', [
            'demoPage' => [
                'id'          => $demoPage->id,
                'name'        => $demoPage->name,
                'slug'        => $demoPage->slug,
                'description' => $demoPage->description,
                'is_active'   => $demoPage->is_active,
                'expires_at'  => $demoPage->expires_at?->format('Y-m-d\TH:i'),
                'emails'      => $demoPage->emails->map(fn($e) => [
                    'id'    => $e->id,
                    'email' => $e->email,
                    'label' => $e->label,
                ]),
            ],
        ]);
    }

    public function update(Request $request, DemoPage $demoPage)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active'   => 'required|boolean',
            'expires_at'  => 'nullable|date',
        ]);

        $statusChanged = $demoPage->is_active !== $validated['is_active'];
        $expiryChanged = $demoPage->expires_at?->format('Y-m-d H:i') !== (isset($validated['expires_at'])
            ? \Carbon\Carbon::parse($validated['expires_at'])->format('Y-m-d H:i')
            : null);

        $demoPage->update($validated);

        if ($statusChanged) {
            $this->notifySuperAdmins($demoPage, 'status_changed', $request->user()->name,
                $validated['is_active'] ? '有効 に変更' : '無効 に変更');
        } elseif ($expiryChanged) {
            $this->notifySuperAdmins($demoPage, 'expiry_changed', $request->user()->name,
                $validated['expires_at'] ? '期限: ' . $validated['expires_at'] : '無期限 に変更');
        }

        $notified = $statusChanged || $expiryChanged;

        return back()->with('success', $notified
            ? '設定を保存しました。SuperAdmin にメールを送信しました。'
            : '設定を保存しました。');
    }

    public function updatePassword(Request $request, DemoPage $demoPage)
    {
        $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $demoPage->update(['password' => Hash::make($request->password)]);

        $this->notifySuperAdmins($demoPage, 'password_changed', $request->user()->name);

        return back()->with('success', 'パスワードを変更しました。SuperAdmin にメールを送信しました。');
    }

    public function storeEmail(Request $request, DemoPage $demoPage)
    {
        $request->validate([
            'email' => 'required|email|max:200',
            'label' => 'nullable|string|max:100',
        ]);

        // 重複チェック
        if ($demoPage->emails()->where('email', $request->email)->exists()) {
            return back()->withErrors(['email' => 'このメールアドレスはすでに登録されています。']);
        }

        $demoPage->emails()->create([
            'email' => $request->email,
            'label' => $request->label,
        ]);

        $this->notifySuperAdmins($demoPage, 'email_added', $request->user()->name,
            "追加: {$request->email}" . ($request->label ? "（{$request->label}）" : ''));

        return back()->with('success', "メールアドレスを追加しました。SuperAdmin にメールを送信しました。");
    }

    public function destroyEmail(Request $request, DemoPage $demoPage, DemoPageEmail $email)
    {
        if ($email->demo_page_id !== $demoPage->id) {
            abort(404);
        }

        $removedEmail = $email->email;
        $email->delete();

        $this->notifySuperAdmins($demoPage, 'email_removed', $request->user()->name,
            "削除: {$removedEmail}");

        return back()->with('success', "メールアドレスを削除しました。SuperAdmin にメールを送信しました。");
    }

    private function notifySuperAdmins(DemoPage $demoPage, string $action, string $operatorName, string $detail = ''): void
    {
        $superAdmins = User::where('user_role', 'superadmin')->whereNotNull('email')->get();
        foreach ($superAdmins as $admin) {
            Mail::to($admin->email)->send(new DemoPageUpdated($demoPage, $action, $operatorName, $detail));
        }
    }
}
