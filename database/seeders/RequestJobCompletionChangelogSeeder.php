<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class RequestJobCompletionChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'myjob-received-completion-20261109'], [
            'title' => '試用マイジョブから元依頼への完了反映を改善',
            'released_at' => '2026-11-09',
            'summary' => '依頼から登録した仕事の完了を元依頼にも反映し、依頼主・案件リーダーへ重複せず通知します。',
            'body' => '<p>情報出版Super Admin限定。依頼から登録したマイジョブと続き元を完了にした際、明示的に紐づく本人宛の元依頼も完了にします。再操作で通知を重複させず、ほかの続きは未完了のまま残します。銀本への反映待ちは詳細に表示し、再試行まで削除を保護します。登録時通知の追加や過去データの一括変更、一般公開は行いません。</p>',
            'design_files' => ['z_instructions/REQUEST_JOB_COMPLETION_PLAN1.md'],
            'claude_notes' => 'Codex。通常supersedes元の本人/案件/所属/一意関係を検証しdefault DBで状態と通知を確定。校正専用処理は保持、進行表通知との重複を防ぐ。MGinbonは元依頼も永続outboxでcommit後同期。',
        ]);
    }
}
