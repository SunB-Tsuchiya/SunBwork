<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class ReceivedJobPrefillChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'myjob-request-fill-20261109'], [
            'title' => '試用マイジョブの依頼登録で詳細の引継ぎを統一',
            'released_at' => '2026-11-09',
            'summary' => '依頼詳細とカレンダーからの依頼選択で、案件の既定値や作業詳細を同じように引き継ぎます。',
            'body' => '<p>情報出版Super Admin限定。依頼のクライアント、案件固定サイズ、作業種別・工程・概要、数量・単位・作業ファイル情報を共通処理で入力欄へ渡します。カレンダーで選んだ作業日時を保持し、保存時には元依頼の更新や二重登録を確認します。進行表・管理表・校正の関連を保持します。一般公開や過去データの一括補正、ファイル本体の保存は行いません。</p>',
            'design_files' => ['z_instructions/MYJOB_REQUEST_HANDOFF_PLAN1.md'],
            'claude_notes' => 'Codex。試用認可付きGETと共通prefill、選択の遅延応答保護、revision再照合と元依頼ロックを伴う保存。旧一般経路・入口別statusを保持。',
        ]);
    }
}
