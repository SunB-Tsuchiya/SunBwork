<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\CoordinatorSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CoordinatorSettingController extends Controller
{
    public function index(Request $request)
    {
        $setting = CoordinatorSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['jobbox_group_mode' => 'date']
        );

        return Inertia::render('Coordinator/Settings/Index', [
            'setting' => $setting,
        ]);
    }

    public function show(Request $request)
    {
        $setting = CoordinatorSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['jobbox_group_mode' => 'date']
        );

        return response()->json(['jobbox_group_mode' => $setting->jobbox_group_mode]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'jobbox_group_mode' => 'sometimes|in:date,client,project',
        ]);

        $setting = CoordinatorSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        if ($request->inertia()) {
            return redirect()->route('coordinator.settings.index')
                ->with('success', '設定を保存しました。');
        }

        return response()->json(['jobbox_group_mode' => $setting->jobbox_group_mode]);
    }
}
