<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class BroadProjectTreeChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'broad-project-tree-20261031'], [
            'title' => '大まかな案件を階層で整理',
            'released_at' => '2026-10-31',
            'summary' => '情報出版の大まかな案件をグループ別に探し、進行管理・部署リーダーがWebで整理できます。',
            'body' => '<p>大まかな案件に最大3段の親子グループを追加しました。親の名前・取引先・年度を含む検索、グループの開閉、子分類の追加、編集・休止ができます。情報出版の同部署の進行管理・部署リーダー・SuperAdminが分類を管理します。承認済み分類の先行登録は既存の年度付き案件と作業の参照を保ち、再登録してもWeb編集を戻しません。一般Userの新しい仕事ハブ・簡易入力の公開、個人お気に入り、既存ProjectJobとの一括紐付けは今回の対象外です。</p>',
            'design_files' => ['z_instructions/USER_WORK_HUB_PLAN1.md'],
            'claude_notes' => 'Codex。親自己参照と会社部署別import_keyを追加。対象migration/専用CLI/独立Seederのみ限定実行。実装、配備、利用者画面確認は別管理。',
        ]);
    }
}
