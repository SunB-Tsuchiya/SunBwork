<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class BroadProjectOrganizationChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'broad-groups-20261031'], [
            'title' => '大まかな案件の編集・移動・並べ替えを整理',
            'released_at' => '2026-10-31',
            'summary' => '分類を大グループと中グループに揃え、部署共通の並びを管理できます。',
            'body' => '<p>大グループと中グループの追加を分け、名前などの編集から所属の変更を外しました。中グループは専用の「グループ移動」で別の大グループへ移せます。初期順は承認済みのExcelの分類順です。同部署の進行管理・部署リーダー・SuperAdminが、大グループ同士や各大グループ内の並びを上下操作して保存できます。一般ユーザーには共通の並べ替えを開放しません。既存の作業・案件参照を保持します。</p>',
            'design_files' => ['z_instructions/USER_WORK_HUB_PLAN1.md'],
            'claude_notes' => 'Codex。最大3段を大中2段へ変更。sort_order追加、明示mysql/会社部署の初期化CLIは設定済み兄弟順を保護。一般Userの新ハブ/新入力公開、個人お気に入り、ProjectJob紐付けは別工程。',
        ]);
    }
}
