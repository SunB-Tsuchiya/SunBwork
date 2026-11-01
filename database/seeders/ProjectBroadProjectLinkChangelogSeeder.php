<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class ProjectBroadProjectLinkChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'project-broad-link-20261102'], [
            'title' => 'プロジェクト案件を大まかな案件へ紐付け',
            'released_at' => '2026-11-02',
            'summary' => '情報出版の進行管理が、案件の新規作成・編集で大グループまたは中グループを任意選択できます。',
            'body' => '<p>プロジェクト案件のタイトル付近に、大まかな案件の任意選択を追加しました。部署共通の順で探し、グループ名や年度で検索できます。既存案件には案件名からの未確定候補を表示し、進行管理が選んで更新した場合だけ紐付きます。詳細には分類の経路を別に表示します。既存の案件名・依頼・マイジョブ・添付は変更しません。情報出版の同所属で案件を管理できるCoordinator・Leader・SuperAdminが対象です。一般Userの新入力公開や既存案件の自動一括分類は含みません。</p>',
            'design_files' => ['z_instructions/USER_WORK_HUB_PLAN1.md'],
            'claude_notes' => 'Codex。nullable FKを追加するだけで旧行はNULL。通常詳細create/updateのみ明示保存。未送信保持、休止済み既存保持、別会社/部署/コンテキスト拒否。候補は一般系列語だけで判断し自動選択しない。配備・利用者画面確認は別管理。',
        ]);
    }
}
