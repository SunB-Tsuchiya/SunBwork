# SALES_ANALYSIS_MANAGER1.md — 売上分析機能 進捗管理

## 1. 運用ルール

- 設計の正本は`z_instructions/SALES_ANALYSIS_PLAN1.md`。
- 再開プロンプトは`z_instructions/SALES_ANALYSIS1_PROMPT.md`。
- 実装担当: Claude Code。
- 実装後レビュー担当: Codex。
- 本番売上DBのレコードをSSH、SQL、Tinker、dump、ログ、臨時スクリプトで閲覧しない。
- ローカル開発・テストは架空データだけを使う。
- 不明点は一度に一つだけユーザーへ質問する。
- フェーズ開始時に関連実装を読み、終了時にこのファイルを更新する。
- 設計変更はPLANと本ファイルの判断ログを同時更新する。

## 2. ステータス凡例

- ⬜ 未着手
- 🔄 作業中
- ✅ 完了
- ⏸ 保留
- ❌ 要修正

## 3. 全体進捗

| Phase | 内容 | 状態 | 担当 | 完了日 |
|---|---|---|---|---|
| 0 | 実装前確認・未決事項確定 | ✅ | Claude Code | 2026-09-02(0-5/0-6はPhase 9/10直前へ先送り) |
| 1 | 別DB接続・migration・Model・機密規則 | ✅ | Claude Code | 2026-09-02 |
| 2 | 個人別権限・SuperAdmin設定 | ✅ | Claude Code | 2026-09-02 |
| 3 | Excel読取・検証・プレビュー | ✅ | Claude Code | 2026-09-03 |
| 4 | 取込確定・版管理・履歴 | 🔄 | Claude Code | |
| 5 | 集計Query/Controller | ⬜ | Claude Code | |
| 6 | ダッシュボードUI | ⬜ | Claude Code | |
| 7 | 得意先統合 | ⬜ | Claude Code | |
| 8 | Excel出力 | ⬜ | Claude Code | |
| 9 | バックアップ | ⬜ | Claude Code | |
| 10 | 総合検証・文書・リリース準備 | ⬜ | Claude Code | |
| R1 | Codexコードレビュー | ⬜ | Codex | |
| R2 | 指摘修正と再検証 | ⬜ | Claude Code | |

## 4. タスク詳細

### Phase 0: 実装前確認

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 0-1 | AGENTS.md、UI、security、domain rulesを再読 | ✅ | CONSOLIDATED_01/02/09、AGENTS.md、PLAN/MANAGER/PROMPT全文読了 |
| 0-2 | 既存権限middleware・SuperAdmin設定画面パターン確認 | ✅ | `admin_permissions`/`leader_permissions`パターン確認。null=全許可のデフォルトは踏襲せず、sales_analysis_permissionsはdefault(false)＋レコードなし=拒否で実装する（PLAN 3.3要件と整合） |
| 0-3 | 既存PhpSpreadsheet import/export実装確認 | ✅ | phpoffice/phpspreadsheet ^5.7 導入済み。`ExpenseController`のob_start+php://outputストリーム出力、`TeamDutyTableController`のIOFactory::load読込パターンを流用予定 |
| 0-4 | 既存Chart.js実装確認 | ✅ | chart.js ^4.5.0（vue-chartjs等のラッパーなし）。`AnalysisPanel.vue`のdestroy→再生成パターンを踏襲 |
| 0-5 | Sakuraの別DB、dump、cron、容量確認 | ⏸ | 本番SSHは事前承認必須。Phase 9/10直前に確認する方針でユーザー了承済み |
| 0-6 | 外部バックアップ保存先をユーザーへ一問で確認 | ⏸ | Phase 9直前に確認する方針でユーザー了承済み |
| 0-7 | サンプルが匿名化済みか一問で確認 | ✅ | ユーザー確認: 匿名化済み・架空データ。fixtureへ値を利用可 |
| 0-8 | アップロード上限を実ファイル容量から決定 | ✅ | ユーザー確認: 年間2,000〜1万行程度の中規模。暫定10MBを上限として実装（PLAN 3.4の暫定値を採用） |
| 0-9 | 作業開始時のgit statusと既存変更確認 | ✅ | build/assets削除差分は無関係な既存状態。SALES_ANALYSIS系新規ファイルは保護対象として確認済み |

### Phase 1: DB・Model・機密規則

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 1-1 | `.env.example`へ空のSALES_DB_*追加 | ✅ | 秘密値なし。`.env`にはローカル値（sunbwork_sales, sailユーザー流用）を設定 |
| 1-2 | `config/database.php`へsales接続追加 | ✅ | mysql接続と同構成、env参照のみSALES_DB_*化 |
| 1-3 | 通常DB permissions migration | ✅ | `2026_09_02_100001_create_sales_analysis_permissions_table.php` |
| 1-4 | sales DB migrations（7テーブル） | ✅ | `2026_09_02_100002〜100008`。各`protected $connection='sales'`＋`Schema::connection('sales')`明示 |
| 1-5 | permissions Model | ✅ | `app/Models/SalesAnalysisPermission.php`（通常DB） |
| 1-6 | sales Modelsとrelations/casts | ✅ | `app/Models/Sales/`配下7ファイル。date系は`date:Y-m-d`でJST/UTC混在バグを回避 |
| 1-7 | `AGENTS.md`へ売上DB閲覧禁止規則追加 | ✅ | 「Sales Data Confidentiality」節を追記。CLAUDE.mdは未編集 |
| 1-8 | 架空sales DBでmigrate/rollback test | ✅ | ローカルDockerで migrate → 両DBのテーブル配置確認 → rollback --step=8 → 再migrate、すべて成功 |
| 1-9 | 通常DBへ売上テーブルができないことをtest | ✅ | `tests/Feature/SalesAnalysis/SalesDatabaseIsolationTest.php` 4件、2回連続実行で安定パス確認 |

### Phase 2: 権限

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 2-1 | `EnsureSalesAnalysisAccess`実装 | ✅ | `app/Http/Middleware/EnsureSalesAnalysisAccess.php`。SuperAdmin常時可、Admin/Clerkはenabled=trueのレコードがある場合のみ、それ以外は常に403 |
| 2-2 | middleware alias登録 | ✅ | `bootstrap/app.php`に`'sales_analysis'`エイリアス追加 |
| 2-3 | SuperAdmin permission Controller/routes | ✅ | `SuperAdmin/SalesAnalysisPermissionController`(index/update)。既存`AdminPermissionController`と違いSuperAdmin限定（`representative`ミドルウェアなし） |
| 2-4 | Admin/Clerk一覧・チェックUI | ✅ | `SuperAdmin/SalesAnalysisPermissions/Index.vue`。トグルスイッチで即時保存。Leaderは候補クエリから除外 |
| 2-5 | Inertia auth propへcanAccessSalesAnalysis追加 | ✅ | `HandleInertiaRequests.php`の`auth`直下（`canAccessScripts`と同パターン） |
| 2-6 | AppLayoutナビ追加 | ✅ | ヘッダーアイコン（スクリプトツールと同パターン）＋レスポンシブメニューの両方に`canAccessSalesAnalysis`条件で追加 |
| 2-7 | ロール×許可のFeature test | ✅ | `SalesAnalysisAccessTest.php` 14件（直URL403、SuperAdmin常時可、許可トグル即時反映、Leaderは許可レコードがあっても拒否、を含む） |

### Phase 3: Excel読取・検証・プレビュー

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 3-1 | Upload Request（xlsx/size） | ✅ | `UploadSalesWorkbookRequest`。拡張子・MIME・ZIPマジックバイト・10MB上限検証 |
| 3-2 | 非公開一時保存と確実な削除 | ✅ | `storage/app/private/sales_imports/`（localディスク）へ保存、`finally`で削除 |
| 3-3 | `SalesWorkbookReader`実装 | ✅ | `getValue()`のみ使用（式は評価しない）。全角数字・カンマ・Excelシリアル日付を安全変換 |
| 3-4 | 15列ヘッダー正規化 | ✅ | 括弧注記・空白差異を吸収して比較 |
| 3-5 | 月次タイトル年月読取 | ✅ | タイトル正規表現パース。サンプル実データで年月・部署抽出を確認 |
| 3-6 | 年次下版日から月判定 | ✅ | 年次は行ごとのSB下版日からsales_month算出。JST date文字列のみ扱いCarbon UTC変換は不使用 |
| 3-7 | 行バリデーション | ✅ | 必須値・負数拒否・年月整合性 |
| 3-8 | 受注単位グループ化・M/N整合性 | ✅ | 受注No単位でM/N列合計比較（1円未満の丸め誤差のみ許容） |
| 3-9 | 重複受注・他月重複検知 | ✅ | `sales_active_months`経由で他月の同一受注Noを検知 |
| 3-10 | 改ざん耐性のあるプレビューtoken/一時データ | ✅ | Cache+`Crypt::encrypt`、UUIDトークン、TTL30分。クライアントからの明細再送は正としない設計 |
| 3-11 | Import.vueプレビューUI | ✅ | ファイル選択・部署/種別/年月フォーム・検証結果表示。確定ボタンはPhase4まで無効化 |
| 3-12 | fixture tests | ✅ | サンプルExcel＋動的生成Excel計34件成功。実在データは使用せず架空値のみ |

### Phase 4: 取込・版管理

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 4-1 | SalesImportService transaction | ⬜ | sales接続 |
| 4-2 | imports/orders/details保存 | ⬜ | |
| 4-3 | active month atomic切替 | ⬜ | |
| 4-4 | 年次複数月atomic切替 | ⬜ | |
| 4-5 | 同一hash検知 | ⬜ | |
| 4-6 | 旧版との差分計算 | ⬜ | |
| 4-7 | 取込履歴画面 | ⬜ | |
| 4-8 | 再取込・混在・rollback tests | ⬜ | |
| 4-9 | 監査ログ | ⬜ | 機密本文をログしない |

### Phase 5: 集計

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 5-1 | active data共通scope/query service | ⬜ | |
| 5-2 | 当月/前月/前年同月 | ⬜ | |
| 5-3 | 年度累計/前年同期 | ⬜ | |
| 5-4 | 5年月別推移 | ⬜ | |
| 5-5 | 得意先別・上位10/全件 | ⬜ | |
| 5-6 | 分類別 | ⬜ | |
| 5-7 | 項目別 | ⬜ | |
| 5-8 | 品名別・部分一致 | ⬜ | |
| 5-9 | 受注件数・平均額 | ⬜ | |
| 5-10 | 未取込月/0分母tests | ⬜ | |

### Phase 6: UI

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 6-1 | Dashboard Controller/Inertia props | ⬜ | |
| 6-2 | 最新月初期表示 | ⬜ | |
| 6-3 | フィルタUI | ⬜ | |
| 6-4 | KPIカード | ⬜ | |
| 6-5 | 5年折れ線Chart.js | ⬜ | |
| 6-6 | 得意先上位10棒グラフ | ⬜ | |
| 6-7 | 分類・項目グラフ | ⬜ | |
| 6-8 | loading/error/empty state | ⬜ | |
| 6-9 | レスポンシブ・AppLayout規則確認 | ⬜ | |
| 6-10 | npm build | ⬜ | |

### Phase 7: 得意先統合

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 7-1 | 決定的な名称正規化Service | ⬜ | 自動確定禁止 |
| 7-2 | 候補一覧 | ⬜ | |
| 7-3 | group/member CRUD | ⬜ | |
| 7-4 | 保存前統合プレビュー | ⬜ | |
| 7-5 | dashboard統合トグル（既定off） | ⬜ | |
| 7-6 | 誤統合防止tests | ⬜ | |

### Phase 8: Excel出力

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 8-1 | filter validation | ⬜ | |
| 8-2 | 概要/月別/得意先/分類/項目/明細sheet | ⬜ | |
| 8-3 | 会社統合条件反映 | ⬜ | |
| 8-4 | formula injection対策 | ⬜ | |
| 8-5 | stream download・一時ファイル削除 | ⬜ | |
| 8-6 | 画面と出力値一致test | ⬜ | |

### Phase 9: バックアップ

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 9-1 | Sakura capability確認 | ⬜ | SSH前承認 |
| 9-2 | 暗号化backup command | ⬜ | 秘密を引数/ログへ出さない |
| 9-3 | 取込後backup dispatch | ⬜ | |
| 9-4 | 日次scheduler | ⬜ | |
| 9-5 | 30日prune | ⬜ | |
| 9-6 | 年末長期保持 | ⬜ | |
| 9-7 | 外部保存 | ⬜ | 保存先要確認 |
| 9-8 | 架空DB restore test/手順書 | ⬜ | 本番レコード禁止 |
| 9-9 | 失敗記録・通知 | ⬜ | |

### Phase 10: 完了処理

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 10-1 | PHP tests | ⬜ | |
| 10-2 | npm build | ⬜ | AGENTSの指定パス差異に注意 |
| 10-3 | 通常機能回帰 | ⬜ | |
| 10-4 | security checklist | ⬜ | |
| 10-5 | ChangelogSeeder `updateOrCreate` | ⬜ | |
| 10-6 | CONSOLIDATED_09更新 | ⬜ | |
| 10-7 | Sakura deployment手順作成 | ⬜ | VITE=/members規則 |
| 10-8 | 本番migration/seed | ⬜ | SSHコマンド事前確認、--force |
| 10-9 | 3文書をarchivedへ移動 | ⬜ | Codex review後 |

### Review R1: Codex

| ID | レビュー観点 | 状態 | 指摘 |
|---|---|---|---|
| R1-1 | PLANとの仕様一致 | ⬜ | |
| R1-2 | 本番sales DBを照会していないこと | ⬜ | |
| R1-3 | DB接続分離・通常DBへの漏洩 | ⬜ | |
| R1-4 | 権限のサーバー側強制 | ⬜ | |
| R1-5 | 取込金額・年月・版管理の正確性 | ⬜ | |
| R1-6 | transaction・再取込・失敗時atomicity | ⬜ | |
| R1-7 | ファイル処理・ログ・export security | ⬜ | |
| R1-8 | 集計SQLと0/未取込の扱い | ⬜ | |
| R1-9 | UI規則・Ziggy・/members対応 | ⬜ | |
| R1-10 | tests/build結果 | ⬜ | |
| R1-11 | バックアップ暗号化・復元性 | ⬜ | |

## 5. 判断ログ

| 日付 | 決定 |
|---|---|
| 2026-09-02 | 部署名は企画・制作・オンデマンド。初期対象は企画のみ |
| 2026-09-02 | 月間売上は受注金額の合計、税抜 |
| 2026-09-02 | 同一受注は複数明細。途中N=0、最後に合計。受注Noは全期間一意・月またぎなし |
| 2026-09-02 | 同月再取込あり。最新版を自動採用し旧版を保持 |
| 2026-09-02 | 年次一括1シートと月次ファイルの両方へ対応 |
| 2026-09-02 | 初期分析優先順は前月、前年同月、5年推移、得意先、分類、項目、品名 |
| 2026-09-02 | 得意先統合は既定off、候補を人が確定。自動統合しない |
| 2026-09-02 | 対象者はSuperAdminおよび個人許可済みAdmin/Clerk。Leader対象外 |
| 2026-09-02 | SuperAdminは常時アクセス可とし、Admin/Clerkの個人許可を管理 |
| 2026-09-02 | 許可者は閲覧・取込・設定・出力をすべて利用可 |
| 2026-09-02 | 初期版はAIなし。後からLocal LLMを補助用途だけに追加 |
| 2026-09-02 | Excel出力は画面フィルタを反映 |
| 2026-09-02 | 元xlsxは取込後削除、経理保管を原本とする |
| 2026-09-02 | 売上DBを通常DBから分離。本番データをAIが照会しない運用規則で保護 |
| 2026-09-02 | ローカルは架空データのみ |
| 2026-09-02 | DB全体を取込後+日次backup、日次30日、年末長期保持 |
| 2026-09-02 | 最新月初期表示、会社統合off、得意先上位10社 |
| 2026-09-02 | sanbrain_meisai_sample.xlsxは匿名化済み・架空データとユーザー確認。値をfixtureへそのまま利用可 |
| 2026-09-02 | アップロード規模は年間2,000〜1万行程度と確認。暫定10MB上限を採用（PLAN 3.4の暫定値のまま） |
| 2026-09-02 | sales系migrationは通常DBのmigrationsテーブルに実行記録が残る（Laravel標準挙動、salesDB側にmigrationsテーブルは作らない）ため、`RefreshDatabase`の`migrate:fresh`がデフォルト接続のテーブルしかdropせず、sales接続に前回テストプロセスの残骸テーブルが残ると**sales機能と無関係な既存テスト全体**が「already exists」で壊れる問題を発見。`tests/TestCase.php`の`refreshApplication()`をオーバーライドし、プロセス開始時に1回だけ`sales`接続をdropAllTablesする対策を追加（既存テスト回帰なしを確認済み） |
| 2026-09-03 | サンプルファイル（架空データ）の受注No 4602841はM列410,000円に対しN列0円と不整合。ユーザー確認: 「途中で切ったデータで本来は続きがある。本番データでは起こりえない」。M/N不一致を検証エラーとする実装はそのまま維持し、サンプルはこの不一致がエラー検知されることを確認するテストケースとして使用。正常系テストは動的生成した完結データ（`Tests\Concerns\BuildsSalesWorkbook`）で別途検証 |
| 2026-09-03 | 年次ファイルのタイトル年月書式は実サンプルが月次のみで未確認。年次は元々PLAN通り「行ごとのSB下版日」で月判定する設計のため、タイトルからは年（および部署名）のみ検証し、月には依存しない設計とした（質問せず合理的デフォルトとして採用） |

## 6. 作業ログ

| 日付 | 担当 | 内容 |
|---|---|---|
| 2026-09-02 | Codex | ユーザーへの一問ずつの要件確認完了 |
| 2026-09-02 | Codex | PLAN/MANAGER/PROMPT作成 |
| 2026-09-02 | Claude Code | Phase 0開始。git status確認（public/build/assets削除差分は既存・無関係、SALES_ANALYSIS系新規ファイルは保護対象として確認） |
| 2026-09-02 | Claude Code | sanbrain_meisai_sample.xlsxが匿名化済み・架空データであるとユーザーに確認済み |
| 2026-09-02 | Claude Code | 既存の権限middleware・SuperAdmin設定パターン、PhpSpreadsheet実装、Chart.js実装の調査完了。詳細は0-2〜0-4欄 |
| 2026-09-02 | Claude Code | アップロード規模(年2,000〜1万行、暫定10MB上限)を確認。Phase 0完了、0-5/0-6はPhase 9/10直前へ先送り。Phase 1着手 |
| 2026-09-02 | Claude Code | Dockerコンテナ起動、sunbwork_sales/sunbwork_sales_testing DB作成、sailユーザーへGRANT付与 |
| 2026-09-02 | Claude Code | sales接続追加(.env/.env.example/config/database.php/phpunit.xml)、通常DB1migration＋sales DB7migration作成、Model8件作成、AGENTS.md機密規則追記 |
| 2026-09-02 | Claude Code | migrate/rollback/再migrateを実機確認。DB分離Feature test 4件作成、2回連続実行で安定確認（`tests/Concerns/RefreshesSalesDatabase.php`で複数DB接続のRefreshDatabase対応trait新設） |
| 2026-09-02 | Claude Code | Phase 1完了。Phase 2(権限)着手前の設計確認をユーザーへ提示予定 |
| 2026-09-02 | Claude Code | Phase 2着手。既存middleware/SuperAdmin設定画面パターンを確認し、方針提示・承認取得 |
| 2026-09-02 | Claude Code | EnsureSalesAnalysisAccess middleware、SuperAdmin/SalesAnalysisPermissionController、User::salesAnalysisPermission()リレーション、routes追加(superadmin配下の許可設定＋sales-analysisダッシュボード暫定ルート) |
| 2026-09-02 | Claude Code | HandleInertiaRequestsにauth.canAccessSalesAnalysis追加。AppLayout.vueにヘッダーアイコン＋レスポンシブナビ追加（既存レイアウトパターンに準拠） |
| 2026-09-02 | Claude Code | SuperAdmin/SalesAnalysisPermissions/Index.vue、SalesAnalysis/Dashboard.vue(Phase6までの暫定プレースホルダ)作成。Ziggy再生成、npm run build成功 |
| 2026-09-02 | Claude Code | sales migrationがテストスイート全体に波及する問題を発見・修正（判断ログ参照）。SalesAnalysisAccessTest 14件＋既存DB分離test 4件、計18件成功。既存Featureテスト(ProofCoordinatorAccessTest等)への回帰なしを確認 |
| 2026-09-02 | Claude Code | Phase 2完了。Phase 3(Excel読取・検証・プレビュー)着手前の設計確認をユーザーへ提示予定 |
| 2026-09-03 | Claude Code | Phase 3着手。サンプルExcel（架空データ確認済み）をPhpSpreadsheetで検査し、タイトル行フォーマット・合計行・複数明細受注の実例を確認。方針提示・承認取得 |
| 2026-09-03 | Claude Code | サンプルのM/N不一致（受注No 4602841）についてユーザーに一問確認、検証ロジックの扱いを確定（判断ログ参照） |
| 2026-09-03 | Claude Code | SalesWorkbookReader、SalesImportValidator、SalesDepartments、UploadSalesWorkbookRequest、ImportController実装。Import.vue、Dashboard.vueへの導線追加。ルート追加・Ziggy再生成・npm build成功 |
| 2026-09-03 | Claude Code | tests/Fixtures/SalesAnalysis/にサンプルExcelを配置。tests/Concerns/BuildsSalesWorkbook.php（動的Excel生成ヘルパー）作成。Unit/Feature test 計34件成功（Reader 5件、Validator 7件、Preview HTTP 4件、既存の権限・DB分離18件含む） |
| 2026-09-03 | Claude Code | Phase 3完了。Phase 4(取込確定・版管理・履歴)着手前の設計確認をユーザーへ提示予定 |

## 7. ブロッカー・未決事項

| ID | 内容 | 状態 | 解決 |
|---|---|---|---|
| U-1 | 暗号化backupの外部保存先 | 未決 | Phase 9直前に一問確認 |
| U-2 | xlsx最大容量とupload上限 | 解決 | 中規模(年2,000〜1万行)と確認。暫定10MB上限を採用 |
| U-3 | 年末backup保持年数 | 未決 | backup実装前に一問確認 |

## 8. 完了条件

- Phase 0〜10がすべて✅。
- Codex Review R1が完了し、重大・高リスク指摘が残っていない。
- 修正後tests/buildが成功。
- ChangelogSeederと統合文書が更新済み。
- 本番売上DBをClaude/Codexがレコード照会していない。
- ユーザーが機能と運用手順を確認済み。
- 本ファイルを含む3文書が`z_instructions/archived/`へ移動済み。
