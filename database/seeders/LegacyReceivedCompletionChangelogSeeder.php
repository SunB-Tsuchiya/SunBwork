<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class LegacyReceivedCompletionChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'legacy-job-notify-20261110'], [
            'title' => '依頼から登録した従来マイジョブの完了通知を修正',
            'released_at' => '2026-11-10',
            'summary' => '従来のカレンダー・マイジョブ詳細からの完了で、元の依頼主への通知が抜ける問題を修正しました。',
            'body' => '<p>依頼からマイジョブに登録した仕事を担当者本人が完了した際、元依頼を基準に依頼主・案件リーダーへ通知します。依頼主がリーダーを兼ねる場合も1件にまとめ、再操作では重複させません。自己完了を自分へ通知しない動作と校正等の専用処理は維持します。過去の未送信通知の一括再送や、新しい入力画面の一般公開は行いません。</p>',
            'design_files' => ['z_instructions/LEGACY_RECEIVED_COMPLETION_PLAN1.md'],
            'claude_notes' => 'Codex。既存owner policyの旧形式完了を共通状態/通知transactionへ接続。default DB確定後に銀本outbox同期・担当者Toast。元依頼とJobAssignmentMessageの完了を同期。新式guardは変更なし。',
        ]);
    }
}
