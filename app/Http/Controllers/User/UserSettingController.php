<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserMonthlySchedule;
use App\Models\UserSetting;
use App\Models\Worktype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserSettingController extends Controller
{
    public function index()
    {
        $user    = Auth::user();
        $setting = $user->userSetting()->with('worktype')->first();

        return Inertia::render('User/Settings/Index', [
            'setting' => $setting,
        ]);
    }

    public function edit()
    {
        $user      = Auth::user();
        $setting   = $user->userSetting()->first();
        $worktypes = Worktype::where('company_id', $user->company_id)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return Inertia::render('User/Settings/Edit', [
            'setting'   => $setting,
            'worktypes' => $worktypes,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'worktype_id'   => 'nullable|integer|exists:worktypes,id',
            'calendar_view' => 'required|in:timeGridWeek,dayGridMonth',
        ]);

        $user    = Auth::user();
        $current = $user->userSetting;

        // 基本勤務形態が変更された場合は日ごとの設定をリセット
        if ($current && $current->worktype_id !== ($data['worktype_id'] ?? null)) {
            UserMonthlySchedule::where('user_id', $user->id)->delete();
        }

        UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        return redirect()->route('user.settings.index');
    }
}
