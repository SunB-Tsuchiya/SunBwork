<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class UserWorkHubPreviewChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'user-work-hub-preview-20261030'], [
            'title' => '仕事・案件のタブと大まかな案件を限定試用',
            'released_at' => '2026-10-30',
            'summary' => '情報出版SuperAdminのユーザーメニューで、仕事と案件を一つの入口から確認できます。',
            'body' => '<p>「仕事・案件」の中にマイジョブ・依頼されたジョブ・大まかな案件・案件確認・校正状況をまとめ、初期表示をマイジョブにします。依頼タブには未登録かつ未完了の本人宛て依頼件数を表示します。大まかな案件は同部署の一覧を検索し、名前・任意の取引先と年度で追加できます。同じ候補がある場合は案内します。情報出版SuperAdmin限定の試用で、一般ユーザーへの公開と簡易入力のBOX統合は後続工程です。</p>',
            'design_files' => ['z_instructions/USER_WORK_HUB_PLAN1.md'],
            'claude_notes' => 'Codex。既存URLを保つ共通ナビと専用のUser一覧/追加API。既存INFO試用guardをサーバ側で再確認。DB移行なし。配備・画面確認・一般公開は別状態。',
        ]);
    }
}
