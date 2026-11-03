<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class UnifiedMyJobBoxChangelogSeeder extends Seeder
{
    public function run(): void
    {
        Changelog::updateOrCreate(['version' => 'unified-box-preview-20261102'], [
            'title' => 'Super Admin限定の統合マイジョブBOX試用',
            'released_at' => '2026-11-02',
            'summary' => '簡易入力と従来のジョブを本人用の読取一覧で確認する限定試用です。',
            'body' => '<p>情報出版のSuper Admin本人だけの試用一覧です。未予定の従来ジョブを残し、複数日の予定を1行にまとめ、簡易入力の「その他」も含めます。検索・月選択・既存詳細やカレンダーへの移動に対応します。一般公開と入力の差し替えは別工程です。</p>',
            'design_files' => ['z_instructions/MYJOB_UNIFIED_BOX_PLAN1.md'],
            'claude_notes' => 'Codex。実所属INFO/非一般会社/本人固定/別会社コンテキスト拒否。新テーブル・一括移行・予定保存・完了更新・日報評価変更なし。CalculatesEventTimeでproof UTC/JST混在を解決。',
        ]);
        Changelog::updateOrCreate(['version' => 'unified-box-input-20261102'], [
            'title' => '統合BOXの試用画面から簡易入力でマイジョブ作成',
            'released_at' => '2026-11-02',
            'summary' => '情報出版のSuper Admin限定で、統合BOXから簡易入力を開いて作業を登録できます。',
            'body' => '<p>統合BOXの「マイジョブ作成」から日付と時刻を入力します。登録した同じ記録をBOXとカレンダーに表示し、従来の詳細入力・依頼からの登録・添付は既存BOXから使えます。一般ユーザーへの公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_UNIFIED_BOX_PLAN1.md'],
            'claude_notes' => 'Codex。専用POSTの厳しい試用guard後に既存DepartmentWorkController::storeへ委譲。1簡易記録のみ保存しassignment/events複製なし。旧カレンダーの入口と保存経路、日報・評価計算は維持。保存IDで本人行のページへ移動。',
        ]);
        Changelog::updateOrCreate(['version' => 'unified-picker-20261103'], [
            'title' => '試用マイジョブの分類選択と単位候補を改善',
            'released_at' => '2026-11-03',
            'summary' => '大まかな案件を全件の階層から選択し、単位を選びやすくしました。作業表示は青に統一します。',
            'body' => '<p>試用マイジョブの大まかな案件は大見出しの＋で開閉し、検索時は一致する中分類と親を表示します。ページ・ファイル・件などの単位候補を常時選択でき、自由入力も残します。簡易入力由来の作業は従来のマイジョブと同じ青で表示します。一般公開やデータ移行は行いません。</p>',
            'design_files' => ['z_instructions/MYJOB_UNIFIED_BOX_PLAN1.md'],
            'claude_notes' => 'Codex。簡易案件候補の30件制限を除去し、部署/会社/activePath認可は維持。共通treeRowsで親子開閉。PHP/JSの簡易作業色を旧マイジョブ青へ合わせる。数量/保存/評価式は変更しない。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-detail-core-20261103'], [
            'title' => '試用マイジョブに詳細ページと時間内訳を追加',
            'released_at' => '2026-11-03',
            'summary' => '情報出版Super Admin限定で、BOXとカレンダーから作業の詳細を開き、同じ記録を編集できます。',
            'body' => '<p>簡易入力で作った仕事にも詳細ページを追加しました。予定枠、昼休憩との重なり、作業分析の試用V3と同じ条件による記録時間を分けて表示します。既存記録のコピーや一般公開は行いません。続き・完了操作は次の段階で接続します。</p>',
            'design_files' => ['z_instructions/MYJOB_CORE_DESIGN1.md'],
            'claude_notes' => 'Codex。nullable完了/相互継続FK基盤、本人INFO SA/context guard、共通ledgerの挙動保持抽出、詳細から旧簡易フォーム編集/保護付き削除。M03〜M06/M09〜M11の全対応を完了扱いにしない。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-chain-simple-20261103'], [
            'title' => '試用マイジョブの続き登録・完了・系列表示',
            'released_at' => '2026-11-03',
            'summary' => '簡易入力の仕事から続きの仕事を登録し、系列と予定・記録時間を確認できます。',
            'body' => '<p>情報出版Super Admin限定。続き作成時は分類や種類を初期値にし、数量・結果・時間は新たに入力します。完了は選んだ仕事と続き元だけに反映し、ほかの続きは未完のまま残します。二重送信で重複作成しない受付を追加しました。旧形式との相互継続と完了連動は次段階で、一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_CORE_DESIGN1.md'],
            'claude_notes' => 'Codex。段階2aのentry系列のみ。明示FK/本人SA context guard、ancestor root locks、同一transactionの再送受付、子あり削除保護。旧新相互継続/混在完了/M11未完。',
        ]);
    }
}
