<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class QuickWorkProofChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'quick-proof-20261110'], [
            'title' => '新式マイジョブからの校正依頼を限定試用',
            'released_at' => '2026-11-10',
            'summary' => '試用中の新式マイジョブ詳細から校正窓口へ依頼できます。',
            'body' => '<p>元の作業との紐づきを保ち、件名・案件を引き継いで校正依頼を送ります。同じ会社・部署の校正窓口へ通知し、二重送信を防ぎます。校正依頼を送っても元のマイジョブは完了になりません。情報出版SuperAdminの限定試用で、一般公開は行いません。</p>',
            'design_files' => ['z_instructions/QUICK_JOB_PROOF_PLAN1.md'],
            'claude_notes' => 'Codex。source_personal_work_entry_idを追加。元行lockと依頼/通知transaction、同会社部署の窓口、旧割当/セルの混在拒否、元参照の限定表示と削除保護。進行表・銀本の専用導線は旧式を維持。',
        ]);
    }
}
