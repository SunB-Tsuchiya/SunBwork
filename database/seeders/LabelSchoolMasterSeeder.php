<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LabelSchoolMasterSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('data/label_school_master_import.json');
        if (! file_exists($jsonPath)) {
            $this->command->error('Import JSON not found: ' . $jsonPath);
            return;
        }

        $entries = json_decode(file_get_contents($jsonPath), true);
        if (! $entries) {
            $this->command->error('Failed to parse JSON.');
            return;
        }

        // 既存データを全消去してから再インポート
        DB::table('label_school_masters')->truncate();

        $now = now();
        $rows = array_map(fn($e) => [
            'code'             => $e['code'] ?? '',
            'display_name'     => $e['display_name'] ?? $e['print_name'],
            'print_name'       => $e['print_name'],
            'area'             => $e['area'] ?? '',
            'area_sort_order'  => $e['area_sort_order'] ?? 0,
            'route'            => $e['route'] ?? null,
            'stop_order'       => null,
            'default_qty'      => 0,
            'is_active'        => true,
            'notes'            => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ], $entries);

        // チャンクで挿入
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('label_school_masters')->insert($chunk);
        }

        $this->command->info('label_school_masters: ' . count($rows) . ' 件インポート完了');
    }
}
