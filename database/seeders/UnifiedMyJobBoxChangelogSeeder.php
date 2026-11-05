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
        Changelog::updateOrCreate(['version' => 'myjob-legacy-continue-20261103'], [
            'title' => '旧マイジョブから簡易入力の続きと共通系列を試用',
            'released_at' => '2026-11-03',
            'summary' => '情報出版Super Admin限定で旧形式の詳細から簡易形式の続きへ登録できます。',
            'body' => '<p>元の仕事と予定を保持し、続き元を明示して簡易入力で新しい作業を登録します。両形式を含む系列で全予定と予定・記録時間を分けて確認できます。旧形式との混在完了と簡易から旧詳細形式への継続は次段階です。一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_CORE_DESIGN1.md'],
            'claude_notes' => 'Codex。段階2b-1: typed assignment sourceと再送受付の拡張、全予定/UTC-JST/結果補足・系列外配分、旧親削除前の子チェック。一般User新propなし。混在完了/逆方向/M11は未完。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-history-simple-20261104'], [
            'title' => '過去データから簡易マイジョブを流用',
            'released_at' => '2026-11-04',
            'summary' => '簡易入力の仕事も過去データ一覧で選び、続きや独立した仕事として登録できます。',
            'body' => '<p>情報出版Super Admin限定。従来のカレンダー時間ドラッグからの過去データ流用を保ち、簡易作成した仕事を追加します。選択したカレンダーの日付・時間枠を引き継ぎ、数量・結果は新たに入力します。「続き」と「新規として引用」を区別して保存します。一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_UNIFIED_BOX_PLAN1.md'],
            'claude_notes' => 'Codex。pastDataへの本人entryのtrial限定追加、typed key、モーダル/旧ページのMyJobHistoryInput。新規引用はreuse_entry_idの本人元検証後に通常entry作成、source FKなし。既存詳細の続きボタン維持。混在完了/簡易→旧詳細形式/M11未完。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-simple-sources-20261104'], [
            'title' => '簡易作成から過去引用と依頼登録を選択',
            'released_at' => '2026-11-04',
            'summary' => '簡易入力から過去ジョブを引用し、依頼は従来のマイジョブ登録へ引き継げます。',
            'body' => '<p>情報出版Super Admin限定。簡易作成画面の過去ジョブから、続きか独立引用を選び、入力済みの日付・時間を保持します。依頼ジョブから登録を選ぶと、その日時で従来の登録画面を開きます。移動時に仕事を作成せず、既存の依頼登録処理を使います。一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_UNIFIED_BOX_PLAN1.md'],
            'claude_notes' => 'Codex。QuickWorkEntryModal内の既存Picker、旧/簡易typed sourceと旧独立引用の本人確認。依頼はevents.create_job open=requestへ日時保持で遷移しsupersedes既存処理利用。混在完了/M11等未完。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-mixed-complete-20261105'], [
            'title' => '旧形式と簡易形式の続きジョブの完了連動を試用',
            'released_at' => '2026-11-05',
            'summary' => '情報出版Super Admin限定で、両形式を含む系列の仕事と続き元を完了にできます。',
            'body' => '<p>選んだ仕事と続き元を完了にし、ほかの続きの状態・予定・数量は保ちます。従来の予定表示・進行表・校正依頼と通知を連動し、再送による通知の重複を防ぎます。銀本への反映は失敗時に再試行し、詳細画面に反映待ちを示します。一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_CORE_DESIGN1.md'],
            'claude_notes' => 'Codex。root順lock/全系列認可、既定DB更新と厳格通知のtransaction、銀本は明示祖先単位の永続受付と既存scheduler再試行。旧のみ/一般User経路は維持。簡易→旧詳細続き/M11は別の未完工程。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-file-details-20261105'], [
            'title' => '試用マイジョブの詳細入力と作業ファイル情報の説明',
            'released_at' => '2026-11-05',
            'summary' => '同じ仕事に説明や作業ファイル情報を追加し、ファイル選択後の動きを旧新両形式で案内します。',
            'body' => '<p>新式は情報出版Super Admin限定。同じ仕事の詳細入力から説明・ファイル名・ページ数・サイズなどを登録でき、予定・実績数量・完了・続きの関係は保ちます。ファイル本体は保存・共有しません。旧式にも同じ説明を追加し、既存の数量自動反映との違いを明記しました。新式の一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_DETAIL_HANDOFF_DESIGN1.md'],
            'claude_notes' => 'Codex。entryの任意説明/file_info/details_version、本人試用専用PUT、型/件数検証と合計再算定、楽観ロック・同内容再送対応。既存詳細のFileInfoDisplay/useFileAnalyzer再利用。旧説明は利用者が明示した両形式への修正として既存フォームに適用。ファイル本体なし。',
        ]);
        Changelog::updateOrCreate(['version' => 'myjob-entry-legacy-continue-20261106'], [
            'title' => '簡易マイジョブから旧形式の続きを登録',
            'released_at' => '2026-11-06',
            'summary' => '情報出版Super Admin限定で、続きの入力中に日時を引き継いで旧形式へ進めます。',
            'body' => '<p>元の簡易ジョブを残し、新しい旧形式の作業を同じ系列へ登録します。元の数量・完了・ファイル一覧はコピーしません。二重送信で仕事や予定を増やさず、予定保存に失敗した場合は登録全体を取り消します。一般公開は行っていません。</p>',
            'design_files' => ['z_instructions/MYJOB_CORE_DESIGN1.md'],
            'claude_notes' => 'Codex。型付きsource_personal_work_entry_id、本人試用/実会社context、root順lockと旧保存を包むtransaction、受付assignment_id拡張。既存カレンダー流用/簡易同士/旧→簡易を維持。クライアント補完は工程3、一般公開は未承認。',
        ]);
    }
}
