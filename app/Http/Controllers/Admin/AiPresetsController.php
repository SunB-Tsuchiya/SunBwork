<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AiPreset;
use Inertia\Inertia;

class AiPresetsController extends Controller
{
    public function index(Request $request)
    {
        $presets = AiPreset::orderBy('type')->orderBy('name')->paginate(30);
        return Inertia::render('Admin/AiPresetsIndex', ['presets' => $presets]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:model,instruction,system',
            'data' => 'nullable|array',
            'description' => 'nullable|string',
            'icon' => 'nullable|string'
        ]);
        AiPreset::create($data);
        return redirect()->back();
    }

    public function update(Request $request, AiPreset $ai_preset)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:model,instruction,system',
            'data' => 'nullable|array',
            'description' => 'nullable|string',
            'icon' => 'nullable|string'
        ]);
        $ai_preset->update($data);
        return redirect()->back();
    }

    public function destroy(AiPreset $ai_preset)
    {
        $ai_preset->delete();
        return redirect()->back();
    }
}
