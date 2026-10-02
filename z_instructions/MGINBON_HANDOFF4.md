# MGinbon 引継ぎ4

更新日: 2026-10-01

## 新しいCodexへの開始指示

1. `/home/w229/SunBwork/AGENTS.md` を最初から読む。
2. 本ファイルを最初から読む。
3. `z_instructions/CONSOLIDATED_09_domain_rules.md` の次の3節を読む。
   - `銀本進行：ProjectJob・担当者連携`
   - `銀本進行：文字校正・ページ数`
   - `銀本進行：FileMaker型コンパクトレイアウト規則`
4. `git status --short` と `git diff --check` を実行し、利用者変更を保護する。
5. 次の作業を始める前に、本ファイルの「現在地」「未完了」「注意」を利用者へ短く報告する。

## 現在地

FileMakerの銀本タブメニューをLaravelへ整理して移行中。

次の機能はローカル実装・自動検証・利用者確認まで完了している。

- 銀本年度とProjectJobの接続
- 案件メンバー／外注先と工程担当候補の連携
- 銀本からMyJob登録、予定、進行中、完了、工程日付への同期
- 年度準備、LIST、入稿チェック、出稿表、既存発注帳票、集計、CSV
- 文字校正一覧と16セルのページ数入力
- 組版外注一覧
- `組版外注_初校 御中` の初校組発注書とA4印刷／PDF

Sakura本番には未デプロイ。今回の作業はまだコミットされていない。

## 直近で完了した内容

### 組版外注

- `view=composition_outsource` を追加。
- 学校単位で入稿日、初校組、再校組、三校組、四校組を4教科表示。
- 担当は既存stage task、発注・納品相当日はwork packageの `assigned_at` / `completed_at` を使用。
- 新規DB列や保存APIは追加していない。
- FileMakerに合わせた固定幅レイアウト。画面幅いっぱいへセルをストレッチしない。

### 組版外注_初校 御中

- `view=composition_initial_order` を追加。
- 左側を主表とし、コード、分類、学校名、媒体、4教科の初校組担当、登録日、完了日を表示。
- 右側補助表は、入稿日と文字完了日を教科セル1個分、作図担当と作図納品日だけを4教科表示。
- 選択レコードをA4縦で印刷・PDF保存可能。
- 印刷時にも左側の識別情報を残す。
- 利用者が最終レイアウトを「文句なし」と確認済み。

## 主な変更ファイル

- `app/Http/Controllers/Coordinator/MGinbonLedgerController.php`
- `resources/js/Pages/Coordinator/MGinbon/LedgerIndex.vue`
- `resources/js/Components/MGinbon/CompositionOutsourceLayout.vue`（新規）
- `resources/js/Components/MGinbon/CompositionInitialOrderLayout.vue`（新規）
- `resources/js/Components/MGinbon/CompositionInitialOrderRecord.vue`（新規）
- `database/seeders/ChangelogSeeder.php`
- `z_instructions/CONSOLIDATED_09_domain_rules.md`
- `z_instructions/MGINBON_HANDOFF3.md`
- `public/build/**`（最新ビルド済み、管理対象）

完了資料:

- `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE_PLAN1.md`
- `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE_MANAGER1.md`
- `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE1_PROMPT.md`

## 検証済み

- `docker compose exec laravel bash -lc "php artisan test --filter=MGinbon"`
  - 26 tests / 91 assertions 成功。
- `npm run build` 成功。
  - 既知のBrowserslistとVite glob非推奨警告のみ。
- `git diff --check` 成功。
- `docker compose exec laravel bash -lc "php artisan db:seed --class=ChangelogSeeder --force"` 成功。
- 利用者による文字校正、組版外注、初校発注書、固定幅レイアウトの確認成功。

## DB変更

直近の組版外注2画面ではDB migrationを追加していない。

未デプロイの銀本migration:

- `2026_09_29_000009_add_page_count_to_mginbon_item_subjects.php`
  - 初期案の互換列。現行文字校正は使用しない。
- `2026_09_29_000010_create_mginbon_page_counts.php`
  - 文字校正ページ数の正本。

ローカル適用済み、本番未適用。

## 未完了・次の候補

### 1. ローカル差分の整理・コミット

今回の変更は未コミット。利用者から依頼された場合のみ、差分を再確認してコミットする。

- `public/build/**` は管理対象なので最新ビルドを含める。
- `storage/**` の変更はローカル実行由来であり、コミットしない。
- unrelatedな利用者変更を戻さない。

### 2. Sakura本番デプロイ

利用者が依頼した場合に行う。開始前に `z_instructions/DEPLOY_SAKURA.md` を読む。

- SSH、migration、seed、deployは正確なコマンドを先に提示し、利用者の明示確認を得る。
- 本番migrationとSeederは `--force` 必須。
- 本番売上データを閲覧しない。
- 本番反映対象にはmigration 000009/000010、最新 `public/build/**`、ChangelogSeederを含める。

### 3. 文字校正ページ数のFileMaker取込

現在はLaravel画面の手入力が正本。FileMaker取込JSONにページ数がないため未対応。

着手条件:

- FileMakerの完全修飾フィールド名を確認する。
- ページ数を含む実際のエクスポート内容を確認する。
- 本番データは見ず、提供されたファイルまたは合成fixtureを使う。

## 重要な設計ルール

- `project_job_assignments` がJobBox／MyJobBoxの正本。
- 銀本工程候補は `MGinbonStageActorOptions` へ統一。
- 組版外注画面は独自データを保存せず、既存工程・work package・milestoneを再構成する。
- FileMaker型専用ビューは内容量に応じた固定幅を使い、外枠やセルをブラウザ幅いっぱいへ伸ばさない。
- 単一値は4教科幅へ結合・拡張せず、教科別の意味がある値だけ4列表示する。
- 日付文字列はJSTの値を直接 `MM/DD` 表示し、`Date` や `toISOString()` でUTC変換しない。
- `～for mac` 専用画面は作らず、Web印刷／PDFで共通化する。

## 作業ツリーの注意

- 最新ビルドにより `public/build/assets` は多数のハッシュ付きファイルが削除・追加され、`manifest.json` も更新されている。これは想定内。
- `storage/**` には以前からローカル実行時変更がある。触らず、コミット対象から除外する。
- 標準patchが作る `.orig` はすべて `/tmp/mginbon-patch-backup.*` へ退避済み。リポジトリ内に残さない。
- WSLのbubblewrap mount問題により、Codexの通常 `apply_patch` が既存ファイル読込で失敗することがあった。再発時は状況を利用者へ説明し、対象を限定した安全な編集方法を使う。

## 再開時に詳細が必要なら読む資料

- `z_instructions/archived/MGINBON_PROJECT_ACTOR_PLAN1.md`
- `z_instructions/archived/MGINBON_PROJECT_ACTOR_MANAGER1.md`
- `z_instructions/archived/MGINBON_TEXT_PROOF_PLAN1.md`
- `z_instructions/archived/MGINBON_TEXT_PROOF_MANAGER1.md`
- `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE_PLAN1.md`
- `z_instructions/archived/MGINBON_COMPOSITION_OUTSOURCE_MANAGER1.md`

## 新しいCodexへ渡す依頼文

`z_instructions/MGINBON_HANDOFF4.md` を最初から読み、記載された開始指示と確認手順を実行して銀本進行の作業を続けてください。既存の未コミット変更と `storage/**` のローカル変更を保護し、SakuraへのSSH・migration・seed・deployは正確なコマンドを提示して私の明示確認を得るまで実行しないでください。
