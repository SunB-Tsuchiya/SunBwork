<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkItemPresetsSeeder extends Seeder
{
    public function run(): void
    {
        // fetch lookup ids
        $sizeA4 = DB::table('sizes')->where('name', 'A4')->value('id');
        $sizeA3 = DB::table('sizes')->where('name', 'A3')->value('id');
        $sizeShiroku = DB::table('sizes')->where('name', '四六判')->value('id');

        $typeCreation = DB::table('work_item_types')->where('slug', 'creation')->value('id');
        $typeRevision = DB::table('work_item_types')->where('slug', 'revision')->value('id');
        $typeProof = DB::table('work_item_types')->where('slug', 'proofreading')->value('id');

        $presets = [
            [
                'title' => '初校作成 A4 10ページ',
                'description' => '初校を作成します。A4、10ページ。',
                'work_item_type_id' => $typeCreation,
                'size_id' => $sizeA4,
                'pages' => 10,
                'quantity' => 1,
                'estimated_minutes' => 120,
                'status' => 'preset',
            ],
            [
                'title' => '三校修正 A3 30ページ',
                'description' => '三校の修正対応。A3、30ページ。',
                'work_item_type_id' => $typeRevision,
                'size_id' => $sizeA3,
                'pages' => 30,
                'quantity' => 1,
                'estimated_minutes' => 360,
                'status' => 'preset',
            ],
            [
                'title' => '校正確認 四六判 200ページ',
                'description' => '校正・確認作業。四六判、200ページ。',
                'work_item_type_id' => $typeProof,
                'size_id' => $sizeShiroku,
                'pages' => 200,
                'quantity' => 1,
                'estimated_minutes' => 2400,
                'status' => 'preset',
            ],
        ];

        foreach ($presets as $p) {
            // If size/type not found, skip and log
            if (empty($p['work_item_type_id']) || empty($p['size_id'])) {
                Log::warning('Skipping preset, missing lookup: ' . json_encode($p));
                continue;
            }

            DB::table('work_item_presets')->updateOrInsert([
                'title' => $p['title']
            ], [
                'description' => $p['description'] ?? null,
                'work_item_type_id' => $p['work_item_type_id'] ?? null,
                'size_id' => $p['size_id'] ?? null,
                'pages' => $p['pages'] ?? null,
                'quantity' => $p['quantity'] ?? null,
                'estimated_minutes' => $p['estimated_minutes'] ?? null,
                'status' => $p['status'] ?? 'preset',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
