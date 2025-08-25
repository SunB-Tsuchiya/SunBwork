<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiSetting;
use Inertia\Inertia;

class AiSettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = AiSetting::latest()->paginate(20);
        return Inertia::render('SuperAdmin/AISettings/AiSettingsIndex', ['settings' => $settings]);
    }

    public function edit(Request $request, $id)
    {
        $setting = AiSetting::findOrFail($id);
        $models = [
            'gpt-4',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-0613'
        ];
        $presets = config('ai_presets.presets', []);
        $instructionsPresets = config('ai_presets.instructions_presets', []);
        $systemPrompts = config('ai_presets.system_prompts', []);
        $hardMax = config('reverb.ai_max_tokens_hardcap', 2000);
        return Inertia::render('SuperAdmin/AISettings/AiSettingsEdit', [
            'setting' => $setting,
            'model_options_list' => $models,
            'hard_max' => $hardMax,
            'model_presets' => $presets,
            'instructions_presets' => $instructionsPresets,
            'system_prompts' => $systemPrompts,
        ]);
    }

    public function update(Request $request, $id)
    {
        $setting = AiSetting::findOrFail($id);
        $data = $request->validate([
            'model' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:1|max:32768',
            'model_options' => 'nullable|array',
            'model_options.temperature' => 'nullable|numeric|min:0|max:1',
            'default_instructions' => 'nullable|string',
            'system_prompt' => 'nullable|string',
        ]);
        $setting->update($data);
        return redirect()->route('superadmin.ai.index');
    }

    public function create(Request $request)
    {
        $models = [
            'gpt-4',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-0613'
        ];
        $presets = config('ai_presets.presets', []);
        $instructionsPresets = config('ai_presets.instructions_presets', []);
        $systemPrompts = config('ai_presets.system_prompts', []);
        $hardMax = config('reverb.ai_max_tokens_hardcap', 2000);
        return Inertia::render('SuperAdmin/AISettings/AiSettingsEdit', [
            'setting' => new AiSetting(),
            'model_options_list' => $models,
            'hard_max' => $hardMax,
            'model_presets' => $presets,
            'instructions_presets' => $instructionsPresets,
            'system_prompts' => $systemPrompts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'model' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:1|max:32768',
            'model_options' => 'nullable|array',
            'model_options.temperature' => 'nullable|numeric|min:0|max:1',
            'default_instructions' => 'nullable|string',
            'system_prompt' => 'nullable|string',
        ]);
        if (!empty($data['model_options']) && is_array($data['model_options'])) {
            $data['model_options'] = array_filter($data['model_options'], function ($v, $k) {
                return $k !== null && $k !== '';
            }, ARRAY_FILTER_USE_BOTH);
        }
        AiSetting::create($data);
        return redirect()->route('superadmin.ai.index');
    }
}
