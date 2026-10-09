# CODEX_HANDOVER_DEPLOY1.md — 売上分析デプロイに向けた引継ぎ（Claude Code → Codex）

作成日: 2026-10-10 / 作成者: Claude Code

## 1. この文書の目的

ユーザーから「売上分析（Claude Code担当分）とCodexが作業中の個人勤務スケジュール機能を
まとめてさくら本番へデプロイしたいが、実際のデプロイ作業はCodex側（そちらの環境）で行う」
との指示を受けた。Claude Code（私）は実際の`git commit`/`push`/SSHデプロイは一切実行せず、
代わりに現状を正確に伝えるこの引継ぎ文書を作成した。

**重要**: ユーザーからは「個人勤務スケジュール機能は、デプロイしても画面上の入り口（ナビゲーション
リンク・メニュー項目）が見えないようにしてからデプロイしてほしい」との指示があった。つまり、
コード自体がさくら本番に乗ること自体は問題ないが、**一般ユーザーが実際にその機能へ到達できる
導線（メニュー・タブ・ボタン等）は、まだ表示しないでほしい**、という意図と理解している
（利用可能だが未告知の状態でデプロイし、準備が整ってから導線を公開する想定）。

## 2. 現状（作業ディレクトリの状態、2026-10-10時点）

`git status`で確認したところ、**origin/mainとは完全に同期済み**（ahead/behindともに0）で、
以下の変更はすべて**まだ一切コミットされていない**、作業ディレクトリ上の変更のみの状態。

- 売上分析（Phase 20〜22＋UI全幅化再設計、Claude Code担当分）
- 個人勤務スケジュール機能（PersonalWork関連、Codex担当分と思われる）

この2つが**同じ作業ディレクトリに未コミットのまま混在**している。Claude Codeはこの状況を
確認した時点で、Codexが編集中のファイルには一切触れず（`git add`も`git commit`もせず）、
この文書の作成のみを行った。

## 3. 売上分析側（Claude Code担当分）の状況

### 3.1 内容サマリー

- Phase 20: サン・ブレーンの受注経路分離（サンエー印刷経由/独自受注）— 既にコミット済み・
  デプロイ済み（この引継ぎの対象外）
- Phase 21: 部署別経費データ（外注加工費等）の取込・期別分析への表示
- Phase 22: 経費比較画面（科目の複数年推移・支払先依存度）新規追加
- 上記に対するユーザー実機フィードバック対応多数（経費内訳の並び順・アコーディオン化・
  小計表示・取込の再取込み許可・年度不一致の取込停止・画面の全幅化（`AppLayout`を使わない
  独立レイアウト`SalesAnalysisShell.vue`への移行）・2カラム化・構成比可視化
  （`ShareCompositionBar.vue`）・期番号表記「56期（2026年度）」の追加）

詳細な設計・判断ログは以下を参照（Codexも直接読める）:
- `z_instructions/SALES_ANALYSIS_PLAN1.md` / `SALES_ANALYSIS_MANAGER1.md`（Phase21・22の設計と経緯）
- `z_instructions/SALES_UI_PLAN1.md` / `SALES_UI_MANAGER1.md`（全幅化・2カラム化・構成比可視化の経緯）

### 3.2 動作確認状況

- `npm run build` 成功（ローカル用ビルドで確認済み。さくら用ビルド(`VITE_APP_BASE_PATH=/members`)は
  未実施）
- `php artisan test --filter=SalesAnalysis` 355件成功
- プロジェクト全体のテスト 546件成功（27件skipは既存の無関係な設定ルート未登録）
- 本番相当の実機確認はユーザー自身がブラウザで実施済み（複数回のフィードバック往復で
  バグ修正済み。現時点で未解決の既知バグ無し）

### 3.3 変更ファイル一覧（売上分析分のみ・明示的リスト）

Codexがステージングする際は、以下のパスだけを売上分析分として扱ってよい
（`git add -A`は使わず、このリストを参考に明示的に指定することを推奨）。

**Controller（新規）**
```
app/Http/Controllers/SalesAnalysis/ExpenseComparisonController.php
app/Http/Controllers/SalesAnalysis/ExpenseImportController.php
app/Http/Controllers/SalesAnalysis/ImportHubController.php
```

**Controller（既存ファイルへの追記）**
```
app/Http/Controllers/SalesAnalysis/FiscalYearAnalysisController.php
```

**Model（新規）**
```
app/Models/Sales/SalesExpenseImport.php
app/Models/Sales/SalesExpenseLineItem.php
```

**Service（新規）**
```
app/Services/SalesAnalysis/SalesExpenseImportService.php
app/Services/SalesAnalysis/SalesExpenseQueryService.php
app/Services/SalesAnalysis/SalesExpenseWorkbookReader.php
app/Services/SalesAnalysis/SalesExpenses.php
```

**Migration（新規、本番で`php artisan migrate --force`が必要）**
```
database/migrations/2026_10_08_100001_create_sales_expense_imports_table.php
database/migrations/2026_10_08_100002_create_sales_expense_line_items_table.php
database/migrations/2026_10_09_100001_add_order_and_subtotal_to_sales_expense_line_items_table.php
database/migrations/2026_10_09_100002_drop_file_hash_unique_from_sales_expense_imports_table.php
```

**Vue コンポーネント（新規）**
```
resources/js/Components/SalesAnalysis/SalesAnalysisShell.vue
resources/js/Components/SalesAnalysis/ShareCompositionBar.vue
resources/js/Pages/SalesAnalysis/ExpenseComparison.vue
resources/js/Pages/SalesAnalysis/ExpenseImport.vue
resources/js/Pages/SalesAnalysis/ImportHub.vue
```

**Vue コンポーネント（既存ファイルへの変更）**
```
resources/js/Components/SalesAnalysis/PeriodNavigator.vue
resources/js/Components/SalesAnalysis/RankingPanel.vue
resources/js/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue
resources/js/Composables/useSalesChart.js
resources/js/Pages/SalesAnalysis/AnnualAnalysis.vue
resources/js/Pages/SalesAnalysis/ClientAnalysis.vue
resources/js/Pages/SalesAnalysis/ClientGroups.vue
resources/js/Pages/SalesAnalysis/FiscalYearAnalysis.vue
resources/js/Pages/SalesAnalysis/Import.vue
resources/js/Pages/SalesAnalysis/ImportHistory.vue
resources/js/Pages/SalesAnalysis/MonthlyAnalysis.vue
resources/js/Pages/SalesAnalysis/ProductAnalysis.vue
resources/js/Pages/SalesAnalysis/RegistrationStatus.vue
resources/js/Pages/SalesAnalysis/SameMonthComparison.vue
resources/js/Pages/SalesAnalysis/SideBySideComparison.vue
```

**テスト（新規）**
```
tests/Concerns/BuildsExpenseWorkbook.php
tests/Feature/SalesAnalysis/ExpenseComparisonTest.php
tests/Feature/SalesAnalysis/ImportHubTest.php
tests/Feature/SalesAnalysis/SalesExpenseApiTest.php
tests/Feature/SalesAnalysis/SalesExpenseImportTest.php
tests/Feature/SalesAnalysis/SalesExpenseQueryServiceTest.php
tests/Unit/SalesAnalysis/SalesExpenseWorkbookReaderTest.php
```

**ドキュメント（新規・更新）**
```
z_instructions/SALES_ANALYSIS4_PROMPT.md
z_instructions/SALES_ANALYSIS_MANAGER1.md         （更新）
z_instructions/SALES_ANALYSIS_PLAN1.md            （更新）
z_instructions/SALES_UI1_PROMPT.md
z_instructions/SALES_UI_MANAGER1.md
z_instructions/SALES_UI_PLAN1.md
z_instructions/CONSOLIDATED_01_layout_and_ui.md   （更新。`fluid`/`SalesAnalysisShell`の使い方を追記）
z_instructions/★経費表_フォーマット.xlsx          （ユーザー提供の参考ファイル。Excelバイナリ）
```

### 3.4 共有ファイル（Codexも同時に変更している可能性が高いもの）

以下はCodexの個人勤務機能側も触っている可能性が高い**共有ファイル**。上書きせず、必ず`git diff`で
両方の変更が残るようマージしてからコミットすること。

- **`routes/web.php`** — 売上分析側では`$registerSalesAnalysisRoutes`クロージャ内に
  `expense-import`/`expense-comparison`/`import-hub`関連のルートを追加しただけ（既存ルートの
  削除・変更はしていない）。差分の該当箇所だけ確認すれば判別は容易なはず。
- **`database/seeders/ChangelogSeeder.php`** — 売上分析側では`$entries`配列の末尾付近に
  `sales-expense-import-1`と`sales-expense-compare-1`という2件のエントリを追記しただけ
  （既存エントリの変更・削除はしていない）。
- **`resources/js/ziggy.js`** — 自動生成ファイル。売上分析のルート追加を反映するため
  `php artisan ziggy:generate resources/js/ziggy.js`を実行済み（`routes/web.php`の変更を
  反映した状態）。Codex側のルート追加も混ざっている場合は、最終的に`routes/web.php`を
  マージしてから**再生成し直す**こと（手で編集しない）。
- **`resources/js/app.js`** — 差分の中身を要確認（売上分析側で明示的に変更した記憶はないが、
  ビルド時の自動整形等で差分が出ている可能性がある）。

### 3.5 本番デプロイ時の注意点（売上分析固有）

- 新規migrationが4件ある（3.3節参照）。`php artisan migrate --force`を忘れると
  期別分析・経費関連画面が本番で壊れる。
- 経費関連機能（経費取込・経費比較・期別分析の経費セクション・期番号表記）は
  `Company.code === 'SUNBRAIN'`（サン・ブレーン）の場合のみ有効になる設計。他社（サンエー印刷等）
  には影響しない。
- `ChangelogSeeder`を本番にも反映する場合は`php artisan db:seed --class=ChangelogSeeder --force`
  も実行すること（`DEPLOY_SAKURA.md`の「ChangelogSeederも反映する場合」参照）。
- Vue/JSファイルの変更を含むため、**さくらデプロイ時は`DEPLOY_SAKURA.md`のVITE_APP_BASE_PATH
  切り替え手順（①〜⑥）を省略しないこと**。これを飛ばすとさくら本番が全画面404になった実績がある。

## 4. デプロイ手順の参照先

実際のビルド・コミット・push・さくらSSHでのpull/migrate手順は
`z_instructions/DEPLOY_SAKURA.md`に詳細がある（AI Agent向けに書かれており、Codexもそのまま
参照できる）。特に以下は省略厳禁:

1. ステップ1: `git status --short | grep -v "public/build"`で未コミット漏れを確認
2. ステップ2: `routes/web.php`を変更していれば Ziggy 再生成
3. ステップ3: `VITE_APP_BASE_PATH`をさくら用(`/members`)に切り替えてビルド
4. ステップ4: 明示的なパス指定でコミット（`git add -A`は使わない）
5. ステップ5: `VITE_APP_BASE_PATH`をローカル用（空）に戻してリビルド（**コミットしない**）
6. ステップ6: push → さくらSSHで`git pull && php artisan migrate --force && ...`

## 5. Claude Codeが行っていないこと（あえて未実施）

- `git add` / `git commit` / `git push` は一切実行していない。
- さくら用ビルド（`VITE_APP_BASE_PATH=/members`でのビルド）は実施していない
  （ローカル用ビルドで動作確認のみ済み）。
- さくらへのSSH接続・デプロイは一切実施していない。

これらはユーザーの指示により、Codex側（そちらの環境）での実施を想定している。
