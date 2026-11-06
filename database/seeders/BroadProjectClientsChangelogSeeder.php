<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class BroadProjectClientsChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'broad-clients-20261106'], [
            'title' => '大まかな案件に複数クライアントを登録',
            'released_at' => '2026-11-06',
            'summary' => '同じ系列に複数のクライアントを登録し、案件名とクライアント名から検索できます。',
            'body' => '<p>大まかな案件のまとまりを保ったまま、関連クライアントを複数登録・解除できます。多い場合は一部と残りの社数を表示し、開くと全件を確認できます。中グループの検索結果は親も表示します。具体案件のクライアントや担当は変更せず、簡易入力での取引先選択は必須にしません。従来の情報出版の試用・分類管理の範囲で提供します。</p>',
            'design_files' => ['z_instructions/BROAD_PROJECT_CLIENTS_PLAN1.md'],
            'claude_notes' => 'Codex。追加pivotと旧client_id互換、会社関連の検証、直接値と親集約の分離。初期関連は確認済みID表の専用add-only import。一般公開なし。',
        ]);
    }
}
