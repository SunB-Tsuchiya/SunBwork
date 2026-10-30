<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class EvaluationV3DefaultChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'eval-v3-default-20261030'], [
            'title' => '評価の試用分析をV3の標準表示に統一',
            'released_at' => '2026-10-30',
            'summary' => '作業実績の内訳・詳しい分析・ガイドはV3を標準表示にし、従来の試用点は比較用として残します。',
            'body' => '<p>採点方式を指定せずに開いた場合はV3を表示します。従来の試用点は比較用に選択でき、分析画面間のリンクでも選択を保持します。点数式や保存済みの作業記録、試用対象の権限は変更しません。</p>',
            'design_files' => ['z_instructions/EVALUATION_SCORE_PLAN1.md'],
            'claude_notes' => 'Codex。情報出版SuperAdminの試用範囲を維持。未指定の画面/API既定をV3へ統一、明示V2の比較とリンク保持を継続。DB migrationなし。',
        ]);
    }
}
