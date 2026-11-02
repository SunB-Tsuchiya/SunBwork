<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class BroadProjectFavoritesChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'broad-favorites-20261102'], [
            'title' => '大まかな案件に個人のお気に入りを追加',
            'released_at' => '2026-11-02',
            'summary' => '大・中グループを星登録し、本人の入力候補からすぐ呼び出せます。',
            'body' => '<p>大まかな案件の一覧で、星を付けて自分だけのお気に入りに登録できます。「お気に入りだけ」で絞り込み、簡易入力と進行管理の案件分類選択でも呼び出せます。大と中は個別登録で、部署共通のExcel順や他の人のお気に入りは変わりません。休止した分類は新しい入力の候補から外れ、以前の星は一覧の「休止中も表示」で確認・解除できます。現在の情報出版の試用・分類管理の範囲で提供します。</p>',
            'design_files' => ['z_instructions/USER_WORK_HUB_PLAN1.md'],
            'claude_notes' => 'Codex。userとbroad_projectの一意FKテーブル、本人固定の冪等PUT。会社部署/既存試用guardを再検証、一般User公開なし。通常30件より後ろの星も取得。分類sort/ProjectJob/作業データは変更しない。',
        ]);
    }
}
