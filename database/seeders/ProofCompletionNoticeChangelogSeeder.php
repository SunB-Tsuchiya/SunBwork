<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class ProofCompletionNoticeChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'proof-notify-20261110'], [
            'title' => '校正完了時の依頼者通知を修正',
            'released_at' => '2026-11-10',
            'summary' => 'カレンダーから校正ジョブを完了した場合も依頼者へ通知します。',
            'body' => '<p>校正の完了処理と依頼者への通知をまとめ、再操作による通知の重複を防ぎます。通知を保存できない場合は完了も確定しません。校正元の作業を自動で完了にする変更はありません。</p>',
            'design_files' => ['z_instructions/PROOF_COMPLETION_NOTICE_PLAN1.md'],
            'claude_notes' => 'Codex。校正依頼のロック/完了/通知を共通化。旧カレンダー・旧BOX・校正専用画面・混在系列で同じ処理。複数候補のlatest選択を禁止。過去通知の補正なし、新式一般公開なし。',
        ]);
    }
}
