# MGinbon 引継ぎ3

更新日: 2026-09-30

## 再開手順

1. `AGENTS.md` を読む。
2. 本ファイルを読む。
3. `z_instructions/CONSOLIDATED_09_domain_rules.md` の「銀本進行」2節を読む。
4. 詳細が必要な場合だけ、次の完了資料を読む。
   - `z_instructions/archived/MGINBON_PROJECT_ACTOR_PLAN1.md`
   - `z_instructions/archived/MGINBON_PROJECT_ACTOR_MANAGER1.md`
   - `z_instructions/archived/MGINBON_TEXT_PROOF_PLAN1.md`
   - `z_instructions/archived/MGINBON_TEXT_PROOF_MANAGER1.md`
   - `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE_PLAN1.md`
   - `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE_MANAGER1.md`

## 現在地

FileMakerの銀本タブメニューをLaravel側へ整理して移行中。ProjectJob・担当者・MyJob連動、文字校正、組版外注一覧、初校組発注書まで実装・利用者確認済み。Sakura本番には未デプロイ。

## 完了済み

### ProjectJob・担当者・MyJob連動

- 銀本年度を既存ProjectJobへ接続、または銀本専用ProjectJobを作成。
- 年度準備画面を追加し、媒体取込前と制作進行開始後を分離。
- 担当者系の値一覧を案件所属ユーザー／外注先へリンク。
- 値一覧の表示値と実ユーザー名を分離。担当リンク変更時は表示値も同名を初期値にし、後から任意変更可能。
- LIST、入稿チェック、媒体詳細、出稿表、Coordinator仮担当、User自己登録の候補判定を `MGinbonStageActorOptions` に統一。
- 銀本進行からMyJob登録、予定設定、進行中、完了、工程日付反映まで連動。
- 完了済み・開始済み工程の変更は通常の入力エラーとして扱い、Laravel例外画面を出さない。
- 値一覧と媒体詳細は銀本専用ヘッダーへ変更。媒体詳細は広幅・3列レイアウト。
- 出稿表の表示名を修正。

### 文字校正

- 制作進行のレイアウト選択に `文字校正` (`view=text_proof`) を追加。
- 既存の校正担当、発注日、納品日を4教科で一覧表示。
- `LedgerCellEditor` を共用し、文字校正から担当者・日付を編集可能。LISTと同じ正本を更新するため相互反映される。
- ページ数は `mginbon_page_counts` に production unit × 教科 × page type で保存。
- page typeは `problem`、`answer`、`trend`、`explanation`。
- 媒体の存在で自動判定せず、問題・解答・傾対・解説×4教科の16セルを常に入力可能。
- MyJobの `amounts` は1ジョブ1値で複数教科へ逆配分できないため、ページ数とは連動しない。
- 保存結果は共通 `ToastUnified` の `toast:show` イベントで通知。
- 利用者がレイアウト、ページ数保存・再読込、担当／日付編集、LIST反映、トーストを確認済み。

### 組版外注・初校発注書

- 制作進行のレイアウト選択に `組版外注` と `組版外注_初校 御中` を追加。
- 組版外注は初校組・再校組・三校組・四校組の担当と、作業パッケージの登録・完了日を4教科で一覧表示。
- 初校発注書はコード・分類・学校名・媒体と、4教科の初校組担当・登録日・完了日を主表として表示。
- 右側補助表は入稿日・文字完了日を単一セル、作図担当・作図納品日だけを4教科表示。
- 新規DB列は作らず、既存工程、作業パッケージ、milestoneを再構成。
- FileMaker型コンパクトレイアウト規則を統合仕様へ追加し、固定セル幅で画面全幅へのストレッチを禁止。
- 利用者が両画面とレイアウトを確認済み。

## FileMakerメニュー監査

直接または既存別メニューで対応済み:

- 入稿チェック
- 出稿表
- LIST
- 組版進行一覧と初校出・解答初校出の各表示（共通LIST／絞り込み）
- 作図＆文字発注フォーム
- C&C文字入力発注書
- 大連作図発注書
- 原本スキャン発注書
- 図版点数・集計
- LISTコピー（CSV）
- 文字校正
- 組版外注
- 組版外注_初校 御中

`～for mac` は専用画面を作らず、共通のWeb印刷・PDF保存で扱う方針。

## 次の作業

文字校正ページ数のFileMaker取込は未対応。現行取込JSONにページ数フィールドがないため、現在はLaravel画面での手入力が正本。将来対応する場合は完全修飾フィールド名とエクスポート内容を確認する。

## DB変更

- `2026_09_29_000009_add_page_count_to_mginbon_item_subjects.php`
  - 初期案の互換列。現行文字校正はこの列を使用しない。
- `2026_09_29_000010_create_mginbon_page_counts.php`
  - 現行ページ数の正本。

ローカルmigration適用済み。本番は未適用。

## 検証

- `docker compose exec laravel bash -lc "php artisan test --filter=MGinbon"`
  - 26 tests / 91 assertions 成功。
- `npm run build` 成功。
- `git diff --check` 成功。
- 利用者による一連の手動確認成功。

## 注意

- SakuraへのSSH・migration・seed・deployは、正確なコマンドを利用者へ提示し、明示確認を得てから行う。
- 本番migrationは `--force` 必須。
- `public/build` は管理対象。Vue変更後の最新ビルドを含める。
- `storage/**` のローカル実行時変更はコミットしない。
- 本番売上データを閲覧しない。
- ProjectJob/MyJobの正本・継続ジョブ規則は `AGENTS.md` と統合仕様を優先する。
