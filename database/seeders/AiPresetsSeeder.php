<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use App\Models\AiPreset;

class AiPresetsSeeder extends Seeder
{
    public function run()
    {
        $config = Config::get('ai_presets', []);
        // insert model presets
        foreach ($config['presets'] ?? [] as $p) {
            AiPreset::updateOrCreate([
                'name' => $p['name'],
                'type' => 'model'
            ], [
                'data' => [
                    'model' => $p['model'] ?? null,
                    'max_tokens' => $p['max_tokens'] ?? null,
                    'model_options' => $p['model_options'] ?? []
                ],
                'description' => $p['description'] ?? null,
                'icon' => $p['icon'] ?? null,
            ]);
        }
        // insert instructions presets
        foreach ($config['instructions_presets'] ?? [] as $p) {
            AiPreset::updateOrCreate([
                'name' => $p['name'],
                'type' => 'instruction'
            ], [
                'data' => [
                    'instructions' => $p['instructions'] ?? null,
                ],
                'description' => $p['description'] ?? null,
                'icon' => $p['icon'] ?? null,
            ]);
        }
        // insert system prompts
        foreach ($config['system_prompts'] ?? [] as $p) {
            AiPreset::updateOrCreate([
                'name' => $p['name'],
                'type' => 'system'
            ], [
                'data' => [
                    'prompt' => $p['prompt'] ?? null,
                ],
                'description' => $p['description'] ?? null,
                'icon' => $p['icon'] ?? null,
            ]);
        }
    }
}
