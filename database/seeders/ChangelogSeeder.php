<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class ChangelogSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            // ─────────────────────────────────────────────────────────────
            // 0f. CLIENT-UPDATE-1 — 2026-05-26
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'client-update-1',
                'title'        => 'クライアント管理：全ロール統一・任意統合・重複検知精度向上・登録エラー修正',
                'released_at'  => '2026-05-26',
                'summary'      => 'Leader/Coordinator のクライアント管理画面を SuperAdmin と同じ構成に統一しました。重複チェックページに「任意のクライアントを選んで統合」機能を追加。「その他」と「そのほか」など漢字・ひらがなの表記ゆれも重複として検知するよう改善。Leader がクライアントIDを保存できなかった問題も解消しました。',
                'design_files' => [],
                'claude_notes' => 'ClientController: store()/update() の client_code unique バリデーションを非admin時は where(company_id) スコープに変更（Leader が他社と同コードを使えなかった問題の解消）。normalizeClientName() の mb_convert_kana フラグを h→H に修正（半角カタカナ→全角に統一してから c で全角カタカナ→ひらがなの順に変更）し、str_replace("他","ほか") を追加。Edit.vue/Create.vue からロール別分岐を削除して全ロール共通の全部署選択UIに統一。DuplicateCheck.vue に任意選択マージ機能（検索→選択→残す指定→統合）を追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>Leader・Coordinator がクライアント管理を開くと SuperAdmin と異なるレイアウト（制限付きビュー）が表示されており、一部のボタンや編集項目が使えない状態だった</li>
    <li>「その他」と「そのほか」のように漢字とひらがなで書き方が違うだけの同一クライアントが、重複として検知されず二重登録されることがあった。また半角カタカナと全角カタカナの違いも検知できていなかった</li>
    <li>Leader がクライアントIDを入力・保存しても反映されない（エラーも出ずに無視される）という報告があった</li>
    <li>重複チェックは自動検出のみで、手動で「このクライアントとこのクライアントを統合したい」という操作ができなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>全ロール画面統一：</strong>Leader・Coordinator・Clerk のクライアント管理画面が SuperAdmin と同じ構成になった。テーブル表示・編集ボタン・削除ボタン・部署の複数選択がすべて使えるようになった</li>
    <li><strong>重複検知の精度向上：</strong>「その他」と「そのほか」（漢字・ひらがなの違い）を同一として検知するようになった。半角カタカナと全角カタカナの違いも吸収して比較するよう改善した</li>
    <li><strong>クライアントID保存エラーの解消：</strong>Leader が他社で使われているクライアントIDと同じ値を入力するとエラーになっていた（他社の情報は見えないため原因不明に見えていた）。自社内のみでの重複チェックに変更し、正常に保存できるようになった</li>
    <li><strong>任意統合機能の追加：</strong>重複チェックページに「任意のクライアントを選んで統合」セクションを追加。名前やIDで検索してクライアントを複数選び、残すクライアントをラジオボタンで指定して統合できる。自動検出リストに出てこない組み合わせでも使用可能</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>任意統合は「重複チェック」ページ内の折りたたみセクションに配置されています（初期表示は開いた状態）</li>
    <li>統合すると、削除されるクライアントに紐づいていた案件がすべて残すクライアントへ移動します。この操作は取り消せないため、統合前に確認ダイアログが表示されます</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0e. DEDUP-1 — 2026-05-24
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'dedup-1',
                'title'        => 'クライアント管理：重複チェック機能追加・部署フィルターボタン追加',
                'released_at'  => '2026-05-24',
                'summary'      => 'クライアント一覧に「重複チェック」ボタンを追加。伝票番号重複・コード欠損同名・名前類似（カタカナ/全角半角差異）の3ルールで疑わしいペアを一覧表示し、残すクライアントを選んで一括統合できます。admin/leader/coordinator の全ロール対応。クライアント管理とユーザー管理の部署フィルターボタン並び順も「情報出版→製版→オンデマンド」に固定しました。',
                'design_files' => ['DEDUP_PLAN1.md', 'DEDUP_MANAGER1.md'],
                'claude_notes' => 'ClientController に duplicateCheckPage()・batchMerge() を追加。normalizeClientName() を拡張（mb_convert_kana h/c で半角・全角カタカナをひらがなに統一）。routes/web.php の admin/leader/coordinator 3グループに clients/duplicate-check と clients/batch-merge を追加（Resource より前に定義）。Clients/DuplicateCheck.vue 新規作成（使い方ガイド折りたたみ付き）。Clients/Index.vue・Admin/Users/Index.vue に部署フィルターボタンと固定ソート順を追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>クライアントが名前表記のゆれ（カタカナ/ひらがな・全角/半角・空白有無など）や伝票番号の入力漏れにより、実質同一のクライアントが重複登録されることがあった</li>
    <li>重複に気づかず案件が別々のクライアントに紐づくと、集計・検索・統合の際に支障が出ていた</li>
    <li>クライアント管理の部署フィルターボタンの並び順が五十音順になっており、実際の業務部署の並びと異なっていた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>クライアント一覧に「重複チェック」ボタンを追加。ページに遷移してDBを全件スキャンし、疑わしいペアを一覧表示する</li>
    <li>重複の検出ルールは3種類：①伝票番号の完全一致（コード重複）、②片方にコードなし・名前完全一致（コード欠損）、③名前を正規化後に一致（名前類似 — 空白・カタカナ/ひらがな・全角半角差異を吸収）</li>
    <li>各ペアにはクライアント名・伝票番号・案件数・登録日を並べて表示。案件数が多い方をデフォルトで「残す」に自動選択</li>
    <li>チェックボックスで複数ペアを選択し、「選択した〇件を統合」ボタンで一括統合できる。統合は既存の安全なDB処理（トランザクション）を使用</li>
    <li>使い方ガイドを折りたたみ式で掲載（初期表示は開いた状態）</li>
    <li>クライアント管理・ユーザー管理の部署フィルターボタンの並び順を「情報出版→製版→オンデマンド」の固定順に統一</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0d. MISC-FIX-1 — 2026-05-24
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'misc-fix-1',
                'title'        => '役職称号の全ロール対応・クライアント管理403修正・在席ボード名前幅拡張',
                'released_at'  => '2026-05-24',
                'summary'      => '役職称号（係長・主任）を admin/leader に限らず全ロールのユーザーに設定できるようにしました。クライアント管理で新規作成直後に「編集」が403になる問題を leader/coordinator/clerk を含む全ロールで修正。在席ボードのメンバー名が5文字で切れていたのを8文字程度まで表示できるよう拡張しました。',
                'design_files' => [],
                'claude_notes' => 'PositionTitlesSeeder に主任(sort_order=9)を追加。Admin/UserController・Leader/UserManagementController の positionTitles 渡しを全件統合し role フィルター廃止。CSV インポートの leader 限定制限も削除。ClientPolicy::view()/update() を superadmin/admin は無条件許可、leader/coordinator/clerk は company_id 一致または null の場合は通すよう変更（delete は update に委譲なので自動対応）。IrukaBoard.vue の名前 span を w-16 → w-28 に変更。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>役職称号（係長など）が admin/leader ロールのユーザーにしか設定できず、coordinator や user など他のロールには表示されなかった</li>
    <li>クライアント管理でユーザーが新規作成したクライアントを即座に「編集」しようとすると403エラーが発生していた（company_id が null の場合にポリシーが弾いていた）。leader・coordinator でも同様の問題が起きうる状態だった</li>
    <li>在席ボードのメンバー名が約5文字で省略されており、名前が長いメンバーのフルネームが確認できなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>修正内容</h3>
  <ul>
    <li>役職称号に「主任」を新規追加（係長の次）。全ロールのユーザー作成・編集フォームで選択できるようになった</li>
    <li>クライアント管理の認可ポリシーを修正。superadmin/admin は全クライアントを管理可能、leader/coordinator/clerk は同一会社のクライアントを管理可能（company_id 未設定の場合も許可）</li>
    <li>在席ボードのメンバー名表示幅を拡張し、7〜8文字程度まで省略なく表示されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0c. PJOB1 — 2026-05-23
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'pjob-manager-1',
                'title'        => '案件管理強化：CSV一括登録・担当営業・セッション維持・重複チェック',
                'released_at'  => '2026-05-23',
                'summary'      => 'coordinator の案件新規作成フォームに担当営業・製版入稿日・下版日を追加。案件一覧から CSV 一括登録（No列自動判定・Shift-JIS対応・クライアント/営業担当インラインマッチング）が可能になりました。製版ボードにもサンプル CSV ダウンロードボタンを追加。長時間放置後の操作不能問題を解消し、受注番号の重複登録防止チェックも追加しています。',
                'design_files' => ['PJOB_PLAN1.md', 'PJOB_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/PJOB_PLAN1.md および PJOB_MANAGER1.md を参照。DB: project_jobs に sales_rep/sales_rep_id/plate_submission_date/plate_down_date 追加（migration 済み）。coordinator CSV ルート・コントローラーを新設（prepress とは完全分離）。No列の有無を header[0] で自動判定（detectNoColumnOffset）。Keep-Alive Ping は AppLayout.vue に 10 分間隔。coordinator store/update に jobcode 重複チェック追加（prepress は実装済み）。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>coordinator の案件登録フォームに担当営業や製版日程を入力する手段がなく、大量の案件を CSV から一括登録する機能もありませんでした。長時間ページを開いたままにするとセッションが切れて操作できなくなる問題もありました。</p>
</section>

<section class="cl-fix">
  <h3>追加・改善内容</h3>
  <ul>
    <li>coordinator 案件新規作成フォームに「担当営業」（dropdown + フリーテキスト）・「製版入稿日」・「下版日」を追加した</li>
    <li>coordinator 案件一覧から CSV 一括登録ができるようになった（会社既存の受注 CSV をそのままアップロード可能）</li>
    <li>CSV のクライアント名・営業担当名はファジーマッチングで自動解決。未マッチ時はその場で新規登録ができる</li>
    <li>CSV の「No（行番号）」列はあってもなくても自動判定して正しく読み込む</li>
    <li>Shift-JIS（Excel 保存形式）の CSV にも対応している</li>
    <li>製版ボード（prepress）の CSV 確認画面にサンプル CSV ダウンロードボタンを追加した</li>
    <li>長時間ページを開いたままにしてもセッションが切れなくなった（10 分ごとに自動的に接続を維持）</li>
    <li>coordinator で案件を登録・編集する際、受注番号（伝票番号）が既存案件と重複していると警告が表示されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0a. CLIENTCODE — 2026-05-21
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'client-code',
                'title'        => 'クライアントIDの追加と重複チェックの改善',
                'released_at'  => '2026-05-21',
                'summary'      => 'クライアントに業務用の識別コード（クライアントID）を設定できるようになりました。また、クライアント名の重複チェックのロジックも見直し、より正確に判定されるようになっています。',
                'design_files' => ['CLIENTCODE_PLAN1.md', 'CLIENTCODE_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/CLIENTCODE_PLAN1.md および CLIENTCODE_MANAGER1.md を参照。client_code カラム追加（マイグレーション済み）、重複チェックは client_code の有無で3パターン分岐。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>クライアントの管理をシステム内部のID番号だけで行っていたため、業務で使い慣れた独自のコードで検索・管理する手段がありませんでした。また、同じ名前のクライアントが重複登録されてしまうことがありました。</p>
</section>

<section class="cl-fix">
  <h3>追加・改善内容</h3>
  <ul>
    <li>クライアントに業務用の「クライアントID」を設定できるようになった（登録・編集・一覧すべての画面で対応）</li>
    <li>クライアントID が設定されている場合は、IDが一致しないと同名でも別クライアントとして扱えるようになった</li>
    <li>クライアント名の重複チェックがより正確になった（ID有無・名前の組み合わせで3パターン判定）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0b. IRUKA — 2026-05-15
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'iruka-board',
                'title'        => 'イルカボード（在籍管理）の新設',
                'released_at'  => '2026-05-15',
                'summary'      => 'メンバー全員の在籍状況をリアルタイムで確認・変更できる「イルカボード」を追加しました。18種類のステータスから選べ、ヘッダーに常時表示されます。退社ステータス設定時の自動日報作成にも対応しています。',
                'design_files' => ['IRUKA_PLAN1.md', 'IRUKA_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/IRUKA_PLAN1.md および IRUKA_MANAGER1.md を参照。Phase 1〜9 完了。user_presence_statuses テーブル。30秒ポーリング。ヘッダーの IrukaStatusBadge・IrukaStatusModal が中核コンポーネント。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>社内にいるメンバーが今どういう状況なのか（在席・外出・テレワーク等）をすぐに確認する手段がありませんでした。電話やチャットで確認する必要があり、手間がかかっていました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>全ページのヘッダーに自分のステータスが常時表示されるようになった。クリックするとステータスを変更できる</li>
    <li>「在席」「外出」「テレワーク」「有給休暇」「離席」など18種類のステータスから選択可能</li>
    <li>コメント（一言メモ）を添えてステータスを設定できる</li>
    <li>ダッシュボードに「イルカボード」として全メンバーの状況が一覧表示されるようになった（30秒ごとに自動更新）</li>
    <li>部署ごとのフィルター表示に対応（情報出版・製版・オンデマンド・全部署）</li>
    <li>他のメンバーの名前をクリックして、そのメンバーのステータスを変更することもできる</li>
    <li>「退社」ステータスを設定した際に、その日の日報がまだ作成されていない場合は自動で日報が作成されるようになった</li>
    <li>Admin・Leaderがイルカボードのメンバー表示順・表示/非表示を管理できるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0c. SCRIPTS — 2026-05-16
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'scripts',
                'title'        => 'スクリプトツールの追加',
                'released_at'  => '2026-05-16',
                'summary'      => '業務効率化のための「スクリプトツール」セクションをヘッダーに追加しました。Admin・Leader（権限設定次第）がアクセスでき、各種業務ツールをここから呼び出せるようになっています。',
                'design_files' => ['SCRIPT_PLAN1.md', 'SCRIPT_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/SCRIPT_PLAN1.md および SCRIPT_MANAGER1.md を参照。scripts テーブル（slug, title, description, component_key）。コンポーネントは resources/js/Components/Scripts/ に配置。SCRIPTS_SECTION_GUIDELINES.md（z_instructions/ に現存）も参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>業務で繰り返し行う作業（データ整理・一括処理など）をシステム上で実行できる仕組みがなく、手作業や外部ツールに頼る必要がありました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>ヘッダーに「スクリプト」ボタンを追加。クリックするとスクリプトツールの一覧ページへアクセスできる</li>
    <li>各スクリプトのページでは、ボタン操作や簡単なフォーム入力で業務処理を実行できる</li>
    <li>アクセス権限はAdmin・SuperAdmin、および権限設定されたLeaderのみ（一般ユーザーには非表示）</li>
    <li>今後、新しい業務ツールはここに追加されていく予定</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0d. WORKFLOW + PROCESS — 2026-05-14
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'workflow-sheet',
                'title'        => '工程シートの新設',
                'released_at'  => '2026-05-14',
                'summary'      => '案件に「工程シート」を追加できるようになりました。進行管理表と同様に担当者を登録・完了管理でき、カレンダーのイベントから作業時間を自動集計します。また、案件詳細に「項目リスト」タブが追加され、作業項目の登録・確認が一元管理できます。',
                'design_files' => ['PROCESS_PLAN1.md', 'PROCESS_MANAGER1.md', 'WORKFLOW_V2_PLAN1.md', 'WORKFLOW_V2_PLAN2.md', 'WORKFLOW_V2_PLAN3.md', 'WORKFLOW_V2_MANAGER1.md', 'WORKFLOW_V2_MANAGER2.md', 'WORKFLOW_V2_MANAGER3.md'],
                'claude_notes' => '詳細は z_instructions/archived/PROCESS_PLAN1.md / WORKFLOW_V2_PLAN*.md / WORKFLOW_V2_MANAGER*.md を参照。workflow_sheets / workflow_rows / workflow_cells / workflow_templates テーブル。工程シートは Coordinator/WorkflowSheets/Show.vue。進行表との違い: 工程シートは時間軸ベースで担当者1名/セル。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>案件の作業工程を管理する専用の仕組みがなく、進行管理表と予定管理が分離していました。担当者を設定しても実際の作業時間がシステムに反映されず、工数集計を手作業で行う必要がありました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>案件に「工程シート」を追加できるようになった。テンプレートから工程の雛形を作成し、担当者を登録・完了管理できる</li>
    <li>工程セルに担当者を登録すると、カレンダーのイベントから作業時間が自動で集計されて表示される</li>
    <li>担当者の完了操作・未完了への差し戻しが工程シート上から直接できる</li>
    <li>案件詳細に「項目リスト」タブを追加。作業項目（組版・校正・修正など）を一覧登録・管理できるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0e. GHOST — 2026-05-13
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'ghost-user',
                'title'        => 'テストユーザー（ゴーストユーザー）機能の追加',
                'released_at'  => '2026-05-13',
                'summary'      => 'Coordinatorが仮のテストユーザーを作成し、ジョブ割り当ての流れを実際のユーザー操作で確認できるようになりました。テストユーザーは14日間で自動削除され、正規のユーザーリストには影響しません。',
                'design_files' => ['GHOST_PLAN1.md', 'GHOST_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/GHOST_PLAN1.md および GHOST_MANAGER1.md を参照。users テーブルに is_ghost / ghost_owner_id / ghost_expires_at カラム追加済み。GhostUserController でセッション切り替え。GlobalScope でゴーストユーザーをリストから除外。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>Coordinatorがジョブの割り当てから完了までの流れを実際の画面で確認しようとすると、テスト用に別のユーザーアカウントが必要でした。テスト用アカウントを作ると正規のユーザーリストが汚れてしまうという問題がありました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>Coordinatorのダッシュボードから「テストユーザー」を1つ作成できるようになった</li>
    <li>テストユーザーのボタンをクリックするだけで、そのユーザーとしてサイトを操作できる（別ログイン不要）</li>
    <li>テストユーザーはジョブ受信・マイジョブBOX・進行表からの自己割当・完了操作が確認できる</li>
    <li>テストユーザーは自分の作成したCoordinatorにのみ見える（ユーザー一覧・進行表・メンバー選択には表示されない）</li>
    <li>作成から14日後に自動で削除される</li>
    <li>ジョブ割り当て画面では「[テスト]」ラベル付きで末尾に表示される</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 1. REPAIR5 — 2026-05-23
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-5',
                'title'        => '案件・ジョブ・日報の不具合修正と機能改善',
                'released_at'  => '2026-05-23',
                'summary'      => 'ユーザーから寄せられた不具合・使い勝手の問題16項目を修正しました。日報のタイムライン操作、案件一覧の表示カスタマイズ、ジョブ重複防止など多方面を改善しています。',
                'design_files' => ['REPAIR_PLAN5.md', 'REPAIR_MANAGER5.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN5.md および REPAIR_MANAGER5.md を参照。R5-01〜R5-16 の16タスク。R5-16は後から追加（日報タイムライン編集・カレンダー連動）。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>日常業務で気になっていた「小さな不具合」や「もう少し便利にしてほしい」という要望を16項目にまとめ、一括して修正・改善しました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>通知ページの時間表記が「15時：40」のように表示されていたのを「15:40」に修正</li>
    <li>案件一覧のお気に入り星をクリックしても反応しない問題を修正（進行表では動いていたが案件一覧では未実装だった）</li>
    <li>案件スケジュールパネルでCSVボタンが2つ重複して表示されていた問題を解消</li>
    <li>進行管理表ジョブのタイトル表記が全角ハイフン「ー」とアンダーバー「_」で不統一だったのをアンダーバーに統一</li>
    <li>ジョブ編集時に開始時刻が常に現在時刻に上書きされてしまう問題を修正（編集時は元の時刻を保持するように）</li>
    <li>案件一覧に伝票番号のカラムを追加。表示する列（登録日・伝票番号・クライアント名・ステータス）を各自カスタマイズできるようになった</li>
    <li>スケジュールCSVや案件CSVのインポートがShift-JIS + CRLF（Excelで保存した形式）でも正しく読み込めるようになった</li>
    <li>進行管理表からジョブを割り当てても担当者名が進行表に表示されない問題を修正</li>
    <li>進行管理表から「完了」「未完了に戻す」を操作しても反映されないことがあった問題を修正。操作後にモーダルが自動で閉じるようになった</li>
    <li>伝票画像を登録・削除してもモーダルを閉じるまで画面に反映されない問題を修正（登録・削除直後に画面が更新されるようになった）</li>
    <li>マイジョブBOXで「予定をセット」を何度も押すと同じジョブが際限なく増える問題を修正。既に登録済みのジョブには「登録済」バッジを表示するようになった</li>
    <li>日報の作成・編集ページに、当日のスケジュール（タイムライン）を表示するようになった</li>
    <li>日報ページのタイムライン上で、カレンダーの予定をドラッグやリサイズして直接時間変更できるようになった</li>
    <li>日報の入力欄（Quillエディター）で箇条書きや番号付きリストが正しく表示・動作しない問題を修正</li>
    <li>日報入力欄の各ボタンにカーソルを合わせると機能名（「太字」「箇条書き」等）がポップアップ表示されるようになった</li>
    <li>案件一括作成ページに「テンプレートから作成」タブを追加。テンプレートを選ぶと項目が自動入力された状態でフォームが表示される</li>
    <li>案件一括作成ページで伝票番号を入力できるようになった（空欄でもOK）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 2. REPAIR4 — 2026-05-12
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-4',
                'title'        => 'スマートフォン・タブレット対応',
                'released_at'  => '2026-05-12',
                'summary'      => 'スマートフォンやタブレットでサイトを使用した際のレイアウト崩れ・操作しにくさを全面的に改善しました。ナビゲーション、フォーム、テーブルなどがモバイル画面に対応しています。',
                'design_files' => ['REPAIR_PLAN4.md', 'REPAIR_MANAGER4.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN4.md および REPAIR_MANAGER4.md を参照。R-01〜R-11 の11タスク（レスポンシブ対応）。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>スマートフォンやタブレットでアクセスした際に、ナビゲーションが使いにくかったり、フォームが画面からはみ出したり、テーブルが横に切れるなど、モバイルでの操作に支障がありました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>ナビゲーションタブがスマートフォン画面でも使いやすいドロップダウン形式になった</li>
    <li>案件作成フォームがスマートフォン画面に対応（縦1列レイアウトに）</li>
    <li>ジョブ割り振りフォームがスマートフォン画面に対応</li>
    <li>カレンダーイベント登録フォームがスマートフォン画面に対応</li>
    <li>スマートフォン表示時のカレンダーデフォルトビューを見やすい形式に変更</li>
    <li>ハンバーガーメニューとサブタブの整合性を修正</li>
    <li>ヘッダーのレイアウトをスマートフォンに対応（要素が折り返しても崩れないように）</li>
    <li>「戻る」ボタンのテキストが折り返さないように修正</li>
    <li>テーブルが横にはみ出す場合に横スクロールで表示できるように対応</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 3. REPAIR3 — 2026-05-09
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-3',
                'title'        => '工数・時間計算の精度改善',
                'released_at'  => '2026-05-09',
                'summary'      => 'カレンダーの予定が重複する場合の工数計算や、昼休憩時間の扱いに不正確な部分がありました。計算ロジックを全体で統一し、工数レポートの精度を向上させました。',
                'design_files' => ['REPAIR_PLAN3.md', 'REPAIR_MANAGER3.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN3.md および REPAIR_MANAGER3.md を参照。Q-01〜Q-07 の7タスク。CalculatesEventTime トレイト（app/Http/Controllers/Concerns/CalculatesEventTime.php）が中核。CLAUDE.md の UTC/JST 混在ルールも参照のこと。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>カレンダーの予定が重なっている場合の工数計算（重複除外）が正確でなく、予定を編集・削除した後も古い計算結果が残ることがありました。また、個人設定の昼休憩時間が一部のページで正しく適用されていないケースもありました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>カレンダーの予定を編集・削除した際に、重複計算が正しく再計算されるようになった（以前は古い値が残ることがあった）</li>
    <li>個人設定で設定した昼休憩時間が全ページで正しく適用されるようになった（共通ロジックに統一）</li>
    <li>校正ジョブのイベント（UTC形式で保存）と通常イベント（JST形式で保存）が混在する場合に9時間ずれる問題を修正</li>
    <li>「重複除算」の用語表記を「中断」に統一し、ページ間で表示が一致するようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 4. REPAIR2 — 2026-04-26
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-2',
                'title'        => '案件・ジョブ・UIの改善（第2版）',
                'released_at'  => '2026-04-26',
                'summary'      => '第1版リリース後に見つかった不具合と使い勝手の問題を引き続き改善しました。カレンダー削除エラー、ジョブのステータス表示不統一、案件タブ構成の見直しなど12項目を対応しました。',
                'design_files' => ['REPAIR_PLAN2.md', 'REPAIR_MANAGER2.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN2.md および REPAIR_MANAGER2.md を参照。N-01〜N-12 の12タスク。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>第1版のリリース後も引き続き「カレンダーが壊れる」「戻るボタンが効かない」「ジョブの表示がページによって違う」など、日常操作で気になる問題が報告されていました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>カレンダーの予定削除時に500エラーが出ることがあった問題を修正</li>
    <li>ジョブを削除した後のリダイレクト先が「ジョブ一覧」になっていたのを、前の画面に戻るように変更</li>
    <li>ジョブのステータス表示がページによって異なっていた問題を統一</li>
    <li>「戻る」ボタンが機能しないページを追加修正</li>
    <li>ジョブ履歴の初期表示を展開済みに変更（開くと一覧がすぐ見える）</li>
    <li>ジョブ割り振り時の開始時刻初期値を現在時刻（5分刻み）に設定するようになった</li>
    <li>案件詳細のスケジュールをタブとして独立させた（以前は概要タブに混在していた）</li>
    <li>案件カレンダーのCSV出力ファイル名に案件名が含まれるようになった</li>
    <li>進行管理表の行をクリックで展開・折りたたみできるようになった</li>
    <li>ジョブタイトルの命名規則をアンダーバー区切りに統一</li>
    <li>案件詳細への戻りリンクで進行管理表タブが開いた状態に戻れるようになった</li>
    <li>案件ジョブ一覧の表示モード（リスト/グループ）がページをまたいでも記憶されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 5. PROOF-JOBS — 2026-04-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'proof-jobs',
                'title'        => '校正管理者のジョブ管理を一本化',
                'released_at'  => '2026-04-30',
                'summary'      => '校正管理者（Proof Co.）が使う「割り振り管理」と「校正履歴」の画面を統合し、1つの「ジョブ管理」ページで進行中・完了を切り替えて管理できるようになりました。',
                'design_files' => ['PROOF_JOBS_PLAN.md', 'PROOF_JOBS_MANAGER.md'],
                'claude_notes' => '詳細は z_instructions/archived/PROOF_JOBS_PLAN.md および PROOF_JOBS_MANAGER.md を参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>校正管理者は「割り振り管理」と「案件校正履歴」を別々のページで確認する必要があり、進行中のジョブと完了済みのジョブを横断して把握するのが不便でした。また、一度「完了」にしたジョブを未完了に戻す手段がありませんでした。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>「割り振り管理」と「案件校正履歴」を「ジョブ管理」ページに統合。1画面で全ジョブを確認できるようになった</li>
    <li>進行中・完了済みをタブで切り替えて確認できるようになった</li>
    <li>完了済みのジョブを「未完了に戻す」操作ができるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 6. UI-STATE — 2026-04-29
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'ui-state',
                'title'        => '画面の状態が自動的に記憶されるようになった',
                'released_at'  => '2026-04-29',
                'summary'      => 'ページをリロードしたり他のページから戻ってきた際に、フィルターやタブの選択状態がリセットされてしまう問題を解消しました。最後に操作した状態が自動的に復元されます。',
                'design_files' => ['UI_STATE_PERSIST_PLAN.md', 'UI_STATE_PERSIST_MANAGER.md'],
                'claude_notes' => '詳細は z_instructions/archived/UI_STATE_PERSIST_PLAN.md および UI_STATE_PERSIST_MANAGER.md を参照。useUIState コンポーザブル（resources/js/Composables/useUIState.js）が中核。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>「完了を表示しない」フィルターをONにして作業していても、ページをリロードするとOFFに戻ってしまうなど、操作した設定がリセットされてしまうことがありました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>ジョブ一覧の「完了を表示しない」フィルターがページをまたいでも保持されるようになった</li>
    <li>ジョブ一覧の表示モード（リスト形式 / グループ形式）が保持されるようになった</li>
    <li>案件一覧のソート順が保持されるようになった</li>
    <li>各種タブの選択状態（どのタブを開いていたか）が保持されるようになった</li>
    <li>案件一覧の表示列設定（伝票番号・クライアント名等の表示/非表示）が保持されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 7. PREPRESS — 2026-04-28
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'prepress',
                'title'        => '製版部署専用エリアの新設',
                'released_at'  => '2026-04-28',
                'summary'      => '製版担当者が伝票をカード形式で視覚的に管理できる専用エリアを新設しました。ドラッグ＆ドロップで状態を移動したり、伝票画像からOCRで情報を自動抽出する機能も含まれています。',
                'design_files' => ['PREPRESS_PLAN.md', 'PREPRESS_MANAGER.md', 'PREPRESS_PLAN2.md', 'PREPRESS_MANAGER2.md', 'PREPRESS_BOARD_V2_DESIGN.md'],
                'claude_notes' => '詳細は z_instructions/archived/PREPRESS_PLAN.md / PREPRESS_MANAGER.md / PREPRESS_PLAN2.md / PREPRESS_MANAGER2.md を参照。OCR機能は CONSOLIDATED_10_ocr_local_tesseract.md（z_instructions/ に現存）も参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>製版担当者が伝票情報を管理する専用の場所がなく、他部署のシステムと混在していました。また、伝票情報の入力は手作業で、画像から情報を読み取る仕組みがありませんでした。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>製版担当者専用の「Prepressボード」エリアを新設</li>
    <li>伝票ボード：伝票を「準備中」「作業中」「完了」のカード形式で管理できる。ドラッグ＆ドロップで状態を移動可能</li>
    <li>伝票一覧：登録した伝票を一覧表示・絞り込みできる</li>
    <li>OCR伝票読み取り：伝票画像をアップロードすると、受注番号・クライアント名・品目名を自動抽出。クライアントのデータベース照合と新規登録も同画面で完結できる</li>
    <li>営業担当の設定・管理機能を追加</li>
    <li>CSVによる伝票一括登録（クライアントIDや部署情報を含む形式に対応）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 8. PROGRESS-V2 — 2026-04-27
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'progress-v2',
                'title'        => '進行管理表の全面刷新',
                'released_at'  => '2026-04-27',
                'summary'      => '進行管理表を全面的に作り直しました。担当者管理・締め切り管理・ジョブ完了を1つのセルで完結させられるようになり、色分けアラート・完了率表示・共有URL・印刷など多数の機能が追加されています。',
                'design_files' => ['PROGRESS_SHEET_V2_DESIGN.md', 'PROGRESS_SHEET_V2_MANAGER1.md', 'PROGRESS_SHEET_V2_MANAGER2.md', 'PROGRESS_SHEET_V2_MANAGER3.md'],
                'claude_notes' => '詳細は z_instructions/archived/PROGRESS_SHEET_V2_DESIGN.md および PROGRESS_SHEET_V2_MANAGER1〜3.md を参照。進行表関連は CONSOLIDATED_05_calendar_and_jobbox.md も参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>進行管理表で担当者の設定、締め切り管理、ジョブの登録がそれぞれ別々の操作で行う必要があり、操作が煩雑でした。期日が過ぎていても一目でわかる仕組みがなく、全体の進捗状況を把握するのも困難でした。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>担当者の設定・締め切り・ジョブ完了の管理を1つのセルで完結できるようになった</li>
    <li>締め切りアラートの色分け：期日超過は赤、3日以内は黄、完了は緑で一目でわかる</li>
    <li>各行・シート全体の完了率をパーセントで表示するバッジを追加</li>
    <li>セルにメモを残せるようになった（ポップアップで確認可能）</li>
    <li>閲覧専用の共有URLを発行して社外の人にも安全に共有できるようになった</li>
    <li>複数案件の進捗を横断的に一覧確認できる「進行レポート」機能を追加（Coordinator向け）</li>
    <li>スケジュールの項目を選ぶだけで進行表の行と日付が自動生成されるようになった</li>
    <li>進行表を印刷できるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 9. REPAIR1 — 2026-04-24
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-1',
                'title'        => 'バグ修正・機能改善（第1版）',
                'released_at'  => '2026-04-24',
                'summary'      => '日常業務で発見されたバグ7件を修正し、ジョブステータスの4段階整理・台割行の強化・週間プランナー追加など10項目の機能改善、さらにスケジュール連動・案件複製などの大規模機能2件を実装しました。',
                'design_files' => ['REPAIR_PLAN.md', 'REPAIR_MANAGER.md', 'LAYOUT_REPAIR_PLAN.md', 'LAYOUT_REPAIR_MANAGER.md', 'G01_ITEM_DESIGN.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN.md および REPAIR_MANAGER.md を参照。B-01〜B-07（バグ修正）、L-01〜L-02（レイアウト）、F-01〜F-10（機能改善）、G-01〜G-02（大規模機能）の計21タスク。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>カレンダーの予定が削除できない、日付がずれるなど日常的な操作で「おかしいな」と感じていた不具合が複数ありました。同時に、進行管理表・スケジュール・案件複製など改善要望が多かった機能の大幅強化も実施しました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>カレンダーの予定が削除できない問題を修正</li>
    <li>カレンダーの日付が1日ずれる問題を修正</li>
    <li>スケジュール編集後に同じ予定が2つ表示される問題を修正</li>
    <li>進行管理表の「ジョブ詳細を開く」ボタンが反応しない問題を修正</li>
    <li>「未完了にする」操作がジョブ一覧に反映されない問題を修正</li>
    <li>ジョブ一覧「完了を表示しない」フィルターが機能しない問題を修正</li>
    <li>案件内割り当て一覧→ジョブ一覧が空になる問題を修正</li>
    <li>ジョブのステータスを「未読・確認済み・セット済み・完了」の4段階に整理し、全ページで統一</li>
    <li>台割行への追加・編集・削除・複製・並び替えを強化。Enterキーで保存できるようになった</li>
    <li>スケジュールをカレンダー以外からも直接入力できるインライン編集モードを追加（追加・編集・削除・並び替え・CSV出力取込）</li>
    <li>案件詳細への「戻る」リンクで、進行管理表タブが開いた状態に戻れるようになった</li>
    <li>カレンダーに週間プランナービューを追加。定例会議の自動登録・多段スレッド掲示板・ロールカラー表示に対応</li>
    <li>スケジュールと進行管理表が連動するようになった（項目を紐づけると双方に反映）</li>
    <li>案件複製機能を強化：スケジュール・進行管理表の構造（担当者情報は除く）も一緒にコピーされるようになった</li>
    <li>全ページで「戻る」ボタン・操作ボタンの配置・色を統一</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 10. BULK-CREATE — 2026-04-20
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'bulk-create',
                'title'        => '案件の一括登録・複製・テンプレート機能の追加',
                'released_at'  => '2026-04-20',
                'summary'      => 'Coordinatorが短いスパンで多数の案件を効率よく登録できるよう、ワンクリック複製・CSV一括登録・テンプレート管理の3機能を追加しました。',
                'design_files' => ['BULK_PROJECT_CREATE_DESIGN.md', 'BULK_PROJECT_CREATE_PROPOSAL.md'],
                'claude_notes' => '詳細は z_instructions/archived/BULK_PROJECT_CREATE_DESIGN.md および BULK_PROJECT_CREATE_PROPOSAL.md を参照。実装ファイルは BulkProjectJobController.php・ProjectJobTemplateController.php・BulkCreate.vue。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>Coordinatorが短いスパンで数十件の似た案件を1件ずつ手入力しなければならず、非常に手間がかかっていました。チームメンバーは変わらずクライアントだけ変わるケースが多いため、効率化が求められていました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>ワンクリック複製：既存の案件を1クリックで複製できるようになった。スケジュール・進行管理表の構造（担当者情報は除く）も一緒にコピーされる</li>
    <li>CSV一括登録：CSVファイルから複数の案件を一括登録できるようになった。登録前にプレビューでエラーを確認してから確定できる</li>
    <li>テンプレート管理：よく使う設定（リーダー・チームメンバー・サイズ・クライアント等）をテンプレートとして保存し、次回から自動入力で使い回せるようになった</li>
    <li>クライアントプリセット：案件作成画面でクライアントを選ぶと、そのクライアントの直近案件の設定が自動で引き継がれるようになった</li>
    <li>Shift-JIS + CRLF形式のCSVファイル（Excelで保存した形式）を正しく読み込めるように対応</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0g. PROOF-UNIFY-1 — 2026-05-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'proof-unify-1',
                'title'        => '校正ジョブUI統合：「依頼されたジョブ」フローへの統一・完了同期修正',
                'released_at'  => '2026-05-30',
                'summary'      => '校正ジョブ専用タブを廃止し、通常の「依頼されたジョブ → マイジョブ」フローに統一しました。校正割当と通常割当の完了が連動しなかった問題を修正。スケジュールは校正担当者のカレンダーに直接反映されるようになりました。',
                'design_files' => [],
                'claude_notes' => 'ProofRequestController: complete()/uncomplete() の sender!=user 条件を削除（自己proof時の完了不具合を解消）。MyProjectJobController: completeAssignment() に maybeCompleteProofRequest() を追加（pja100直接 / supersedes_assignment_id 経由の両パターン対応）。EventController: supersedes_assignment_id パスへのproof完了フック追加。SavesProofWorkSlots: pja101作成を廃止しpja100に直接Eventを作成。ProofRequestController::assignStore(): JobAssignmentMessage 作成で「依頼されたジョブ」タブへの表示を実現。UserNavigationTabs.vue: 校正ジョブタブを削除。MyJobBox/Show.vue: proof型割当に校正依頼情報カード（依頼者・校正管理者・締切・ステータス）を追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>校正ジョブが「校正ジョブ」タブと「マイジョブBOX」に二重表示され、どちらかを完了にしても連動しなかった</li>
    <li>校正管理者がセットしたスケジュールがマイジョブBOXに反映されなかった</li>
    <li>「校正ジョブ」タブが「依頼されたジョブ」と役割が重複しており、利用者が混乱していた</li>
    <li>校正担当者=自分（自己proof）の場合に完了ボタンが機能しなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>「校正ジョブ」タブ廃止：</strong>ナビゲーションから「校正ジョブ」タブを削除。既存URLはすべて「依頼されたジョブ」へリダイレクト</li>
    <li><strong>依頼されたジョブへ統合：</strong>校正割当時に JobAssignmentMessage が自動生成されるため、通常の「依頼されたジョブ」タブに自然に表示されるようになった</li>
    <li><strong>完了連動の修正：</strong>マイジョブBOXから完了にすると、対応する ProofRequest も完了になり完了通知が送信される。逆に校正管理者が完了にした場合も同様に連動する</li>
    <li><strong>スケジュール直接反映：</strong>校正管理者がセットした作業スロットが校正担当者のカレンダーに直接反映される（中間割当 pja101 を廃止）</li>
    <li><strong>校正依頼情報カードの表示：</strong>マイジョブBOX詳細ページで校正ジョブを開くと、依頼者・校正管理者・締切・ステータス（校正待ち/校正中/校正完了）が表示されるようになった</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>PCを持たない校正担当者向けの「校正管理者が代わりに完了する」フローは引き続き利用可能です</li>
    <li>校正担当者がマイジョブにした場合（「マイジョブにする」ボタン経由）も、元の校正割当と完了が連動します</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0h. COTYPE-1 — 2026-05-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'cotype-1',
                'title'        => '会社タイプ別機能分離：サン・ブレーン専用機能の部署制御・SuperAdmin コンテキスト管理',
                'released_at'  => '2026-05-30',
                'summary'      => '会社ごとに使える機能を切り分ける仕組み（COTYPE）を導入しました。サン・ブレーンでは情報出版・製版など部署ごとに専用機能を有効化できます。SuperAdmin がヘッダーから会社を切り替えて各社の管理ができるコンテキスト切り替え機能も追加しました。校正依頼ボタンや「校正管理へ依頼」オプションは情報出版部署のユーザーにのみ表示されるよう制御されています。',
                'design_files' => ['z_instructions/COTYPE_PLAN1.md', 'z_instructions/COTYPE_MANAGER1.md'],
                'claude_notes' => '【DB】companies.company_type(sunbrain|general), departments.module(publishing|prepress|ondemand), users.home_company_id を追加（migration 3本）。【ミドルウェア】CheckCompanyType: company_type を検証してルートを保護。ProofCoordinator・Prepress ルートに company_type:sunbrain を追加。【フロントエンド】CompanyModules レジストリ（sunbrain.js/general.js/index.js）でナビゲーションボタンの会社別制御を実現。CompanyModuleNavButtons.vue が extraRoles を動的描画（group=beforeUser/afterUser で位置制御）。SuperAdminContextSwitcher.vue でヘッダーから会社コンテキスト切り替え。【featureFlags】HandleInertiaRequests に auth.featureFlags.proofRequest/prepressBoard を追加。MyJobBox/Show・User/ProjectJobs/Show・Coordinator/ProjectJobs/Show・ProgressSheets/Show・WorkflowSheets/Show の校正依頼UIをフラグでガード。ProgressCell.vue の「校正管理へ依頼」オプションも usePage() 経由でガード。【SuperAdmin UX】DashboardController に ResolvesContextCompany を追加しイルカボードをコンテキスト会社でフィルタ。UserPresenceController も同様に対応。SuperAdmin/Users/Index.vue に会社タブ（filter_company クエリパラメータ）追加。SuperAdminNavigationTabs に「ユーザー管理」リンク追加。CompanyController に generateCode() ヘルパー追加（部署・担当の code 自動生成）。会社登録・編集フォームに code 入力欄とコード説明 Tips ボタンを追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>サン・ブレーンと将来のグループ各社（サンエー印刷など）が同じシステムを使うにあたり、サン・ブレーン固有の機能（校正管理・製版ボード・校正依頼ジョブフロー）が他社ユーザーにも表示されてしまっていた</li>
    <li>SuperAdmin がどの会社の管理をしているかが画面上で分からず、ユーザー一覧や在籍ボードに全社のデータが混在していた</li>
    <li>情報出版部署以外のユーザーが「校正依頼」ボタンを押してエラーになるケースが想定された</li>
    <li>会社・部署・担当の新規登録時に code カラムが必須のためエラーが発生していた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>会社タイプ制御の導入：</strong>companies テーブルに company_type（sunbrain / general）を追加。サン・ブレーン専用ルート（校正管理・製版）には company_type:sunbrain ミドルウェアを付与し、他社ユーザーは 403 でアクセス不可になった</li>
    <li><strong>部署モジュールによる機能制御：</strong>departments テーブルに module（publishing / prepress / ondemand）を追加。ナビゲーションボタン「Proof Admin」「Prepress」の表示を部署モジュールとロールで制御。情報出版 Leader のみ「Proof Admin」が表示され、Coordinator・User には表示されない</li>
    <li><strong>校正依頼 UI のガード：</strong>auth.featureFlags.proofRequest フラグを導入し、情報出版部署（または Admin/SuperAdmin）以外では校正依頼ボタン・セクション・「校正管理へ依頼」オプションが非表示になった。進行表・管理シートのモーダルも同様にガード済み</li>
    <li><strong>SuperAdmin コンテキスト切り替え：</strong>ヘッダーに会社切り替えドロップダウンを追加。選択した会社に応じてユーザー一覧・在籍ボード・ナビゲーションメニューが切り替わる</li>
    <li><strong>SuperAdmin ユーザー一覧の改善：</strong>ユーザー一覧画面に会社タブ（全て・各社）を追加。タブ切り替えで会社ごとのユーザーを確認可能。SuperAdmin タブメニューに「ユーザー管理」リンクも追加</li>
    <li><strong>会社登録・編集フォームの改善：</strong>部署・担当の code フィールドを任意入力に（未入力時は自動生成）。「コードとは？」ボタンで説明を確認できる Tips を追加</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>新しいグループ会社を追加するには SuperAdmin → 会社追加 から company_type を選択してください。sunbrain 専用機能が不要な場合は「一般」を選びます</li>
    <li>部署に専用機能を割り当てるには会社編集画面の「機能」セレクトを使います（sunbrain タイプの会社のみ表示）</li>
    <li>進行表・管理シートの proof_v2 セル（校正担当列）はデータを保持したまま、情報出版部署以外では「校正管理へ依頼」オプションが非表示になります</li>
    <li>さくら本番への適用には migration 3本（company_type・module・home_company_id）をコードデプロイ前に実行する必要があります</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0i. ANNEX-1 — 2026-05-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'annex-1',
                'title'        => 'お知らせ機能強化：会社横断送信・添付ファイル・編集削除・SuperAdmin ユーザー管理改善',
                'released_at'  => '2026-05-30',
                'summary'      => 'お知らせ（通知）機能を大幅に強化しました。サンエー印刷の Clerk は全会社またはグループ各社を選んで通知を送信できるようになりました。通知に PDF・画像などの添付ファイルを付けられるようになり、受信側では本文の下に画像・PDF の内容が直接表示されます。送信済み通知のタイトル・本文・添付ファイルを編集・削除できるようにもなりました。SuperAdmin のユーザー管理画面にも詳細・編集ページと会社タブを追加しています。',
                'design_files' => ['z_instructions/ANNEX_PLAN1.md', 'z_instructions/ANNEX_MANAGER1.md'],
                'claude_notes' => '【DB】announcements.target_company_id (nullable FK) を追加（migration 1本）。【モデル】Announcement に target_company_id fillable + attachments() morphToMany 追加。【ルート】clerk.announcements.edit / update / destroy を追加。【featureFlags】HandleInertiaRequests に crossCompanyAnnouncement フラグ追加（general タイプ会社の Clerk/Admin）。【コントローラー】Clerk/AnnouncementController: store() に会社スコープ対応（未選択=全会社 / 指定会社=その会社のみ / 一般ユーザー=自社のみ）+ AttachmentService による添付保存。edit() / update()（タイトル・本文・添付のみ、受信者変更なし）/ destroy()（添付クリーンアップ含む）追加。AnnouncementController: 受信者側 show() に attachments eager load 追加。attachments mapping: mime_type カラム名修正（$a->mime → $a->mime_type）。【フロントエンド】Create.vue: 会社チェックボックス（複数選択対応 / ソート可能）+ ドロップゾーン添付 + PDF.js サムネイル（scale 1.5/2.5）+ ライトボックス + router.post forceFormData で送信。Show.vue (Clerk): 添付インライン表示 + PDF.js で描画 + 50%幅 + ライトボックス + 編集・削除ボタンを #headerExtras へ。Edit.vue: 新規作成（タイトル・本文・既存添付削除 + 新規追加）。Announcements/Show.vue (受信側): 同様に添付インライン表示 + PDF.js 描画。【SuperAdmin ユーザー管理】SuperAdmin/Users/Show.vue・Edit.vue 新規作成（Admin 版をベースに superadmin ルートへ適用）。SuperAdmin/UserController::edit() / update() を Admin 相当に強化（companies + positionTitles 渡し / 完全バリデーション）。Index.vue に SuperAdminNavigationTabs(active=all_users) 追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>お知らせ機能は自社内のみの送信で、グループ会社（サンエー印刷）から他社への通知ができなかった</li>
    <li>お知らせに添付ファイルを添付する機能がなく、PDF や画像を共有するにはメッセージ機能を使う必要があった</li>
    <li>送信済みのお知らせを後から修正・削除できなかった</li>
    <li>SuperAdmin のユーザー管理画面に詳細・編集ページがなく、「詳細」「編集」ボタンを押してもエラーになっていた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>会社横断送信：</strong>サンエー印刷（グループ親会社）の Clerk は通知作成時に会社チェックボックスで送信先を選択できる。未選択で全会社、特定会社を選ぶとその会社のメンバーのみに送信</li>
    <li><strong>添付ファイル：</strong>お知らせ作成時にドロップ兼クリック選択で複数ファイルを添付できる。画像は即時プレビュー、PDF は PDF.js でサムネイル表示。受信側では本文の下に添付内容が直接描画される</li>
    <li><strong>PDF インライン表示：</strong>添付 PDF は PDF.js で第1ページを画像化して表示。50% 幅で表示され、クリックすると全画面ライトボックスで拡大確認できる</li>
    <li><strong>編集・削除：</strong>送信済みお知らせのタイトル・本文・添付ファイルを編集できる（受信者は変更不可）。削除時は添付ファイルも含めて完全削除される</li>
    <li><strong>SuperAdmin ユーザー管理強化：</strong>詳細ページ（Show.vue）・編集ページ（Edit.vue）を新規作成。Admin の編集画面と同等の項目（会社・部署・担当・権限・雇用形態・役職称号・パスワード変更）を設定可能</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>会社横断送信は general タイプ会社の Clerk / Admin / SuperAdmin のみ利用可能。サン・ブレーンの Clerk は引き続き自社内のみに送信</li>
    <li>個別選択（individual）で宛先を指定した場合、選択した会社チェックボックスに関係なく指定ユーザーのみに送信されます</li>
    <li>編集は送信者本人のみ可能。編集しても既読状況はリセットされません</li>
    <li>PDF サムネイルはページ読み込み後に非同期で描画されます（数秒かかる場合があります）</li>
  </ul>
</section>
HTML,
            ],
        ];

        foreach ($entries as $entry) {
            Changelog::updateOrCreate(
                ['version' => $entry['version']],
                $entry
            );
        }
    }
}
