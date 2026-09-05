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
| 4 | 取込確定・版管理・履歴 | ✅ | Claude Code | 2026-09-03 |
| 5 | 集計Query/Controller | ✅ | Claude Code | 2026-09-03 |
| 6 | ダッシュボードUI | ✅ | Claude Code | 2026-09-03 |
| R1 | Codexコードレビュー（1回目・Excel検証設計） | ✅ | Codex | 2026-09-03 |
| R1修正 | 1回目レビュー指摘修正・実機検証7ラウンド | ✅ | Claude Code | 2026-09-03 |
| R2 | Codexコードレビュー（2回目・実機検証まとめ＋新画面設計） | ✅ | Codex | 2026-09-03 |
| R2修正-High | 2回目レビューHigh 2件（除外ロジック・プレビュー確定の安全性）修正 | ✅ | Claude Code | 2026-09-03 |
| R2修正-Medium | 2回目レビューMedium-1（初期表示の並び順）修正 | ✅ | Claude Code | 2026-09-03 |
| 6B | データ登録状況画面（新規） | ✅ | Claude Code | 2026-09-03 |
| 6C | 年次分析・同月比較・左右比較 | ✅ | Claude Code | 年次分析: 2026-09-03、同月比較/左右比較: 2026-09-04 |
| 7 | 得意先統合（設定画面＋得意先分析画面） | ✅ | Claude Code | 2026-09-04 |
| 8 | Excel出力（年次分析画面のみ、初期版） | ✅ | Claude Code | 2026-09-04 |
| 9 | バックアップ | ⏸ | Claude Code | ユーザー判断で保留（2026-09-04、他の要望を先に聴取） |
| 10 | 総合検証・文書・リリース準備 | ⬜ | Claude Code | |
| 11 | REVIEW3対応 High3件（得意先詳細期間・登録月欠落・部署coverage可視化） | ✅ | Claude Code | 2026-09-04 |
| 12 | 可視化改修 Priority A（共通部品＋月次分析を完成見本として改修） | ✅ | Claude Code | 2026-09-04 |
| 13 | 可視化改修 Priority A 横展開（年次分析） | ✅ | Claude Code | 2026-09-04 |
| 14 | 可視化改修 Priority A 横展開（同月比較） | ✅ | Claude Code | 2026-09-04 |
| 15 | 可視化改修 Priority A 横展開（得意先分析）＋深いリンク解消 | ✅ | Claude Code | 2026-09-04 |
| 16 | 左右比較の横展開検討 | ✅（対象外判断） | Claude Code | 2026-09-04 |
| 17 | 期別分析（4月〜翌3月）を新規画面として独立 | ✅ | Claude Code | 2026-09-04 |

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
| 4-1 | SalesImportService transaction | ✅ | `DB::connection('sales')->transaction()`。通常DBの権限テーブルとの分散transactionは作らない |
| 4-2 | imports/orders/details保存 | ✅ | |
| 4-3 | active month atomic切替 | ✅ | transaction内でupdateOrCreate |
| 4-4 | 年次複数月atomic切替 | ✅ | 受注データの実際の`sales_month`から影響月を動的算出。データの無い月は変更しない |
| 4-5 | 同一hash検知 | ✅ | confirm時にfile_sha256重複を検知し422で拒否（原則確定させない） |
| 4-6 | 旧版との差分計算 | ✅ | `SalesImportService::calculateDiff()`。件数/金額差・追加/削除/変更受注数。previewレスポンスに含める |
| 4-7 | 取込履歴画面 | ✅ | `ImportHistoryController`+`ImportHistory.vue`。担当者名は通常DB Userを別クエリで解決 |
| 4-8 | 再取込・混在・rollback tests | ✅ | 同月再取込、年次月混在、hash重複拒否、失敗時active pointer不変を検証 |
| 4-9 | 監査ログ | ✅ | `SalesAuditLog`にimportアクション記録。得意先名・品名等は含めない |

### Phase 5: 集計

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 5-1 | active data共通scope/query service | ✅ | `SalesQueryService::activeOrdersQuery()`。sales_active_monthsと(import_id,year,month)でJOIN。全指標がここを経由 |
| 5-2 | 当月/前月/前年同月 | ✅ | `monthlyComparison()`。比較対象未取込は`null` |
| 5-3 | 年度累計/前年同期 | ✅ | 暦年・会計年度(4月始まり、開始年で呼称)の両モード実装、切替可能 |
| 5-4 | 5年月別推移 | ✅ | `monthlyTrend()`。未取込月は`total_amount`等が`null` |
| 5-5 | 得意先別・上位10/全件 | ✅ | `clientRanking()`。会社統合オン/オフ対応（`sales_client_group_members`参照） |
| 5-6 | 分類別 | ✅ | `categoryBreakdown()`。明細のline_amountで集計 |
| 5-7 | 項目別 | ✅ | `itemBreakdown()` |
| 5-8 | 品名別・部分一致 | ✅ | `searchByProductName()`。LIKEワイルドカードをエスケープ |
| 5-9 | 受注件数・平均額 | ✅ | `monthlyTotal()`に含む |
| 5-10 | 未取込月/0分母tests | ✅ | SalesQueryServiceTest 11件・SalesDashboardApiTest 5件、計16件で検証 |

### Phase 6: UI

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 6-1 | Dashboard Controller/Inertia props | ✅ | 部署・初期年月・hasAnyDataを渡す |
| 6-2 | 最新月初期表示 | ✅ | sales_active_monthsの最大(year,month)を採用 |
| 6-3 | フィルタUI | ✅ | 部署・年月・会社統合トグル |
| 6-4 | KPIカード | ✅ | 当月/前月比/前年同月比/年度累計（暦年⇄会計年度4月切替ボタン付き） |
| 6-5 | 5年折れ線Chart.js | ✅ | `chart.js/auto`、AnalysisPanel.vueのdestroy→再生成パターンを踏襲 |
| 6-6 | 得意先上位10棒グラフ | ✅ | 横棒グラフ＋全件表示切替テーブル |
| 6-7 | 分類・項目グラフ | ✅ | 分類は棒グラフ、項目は表形式（画面の煩雑化を避けるため） |
| 6-8 | loading/error/empty state | ✅ | 未取込時は取込導線を表示、fetch失敗時はエラーメッセージ |
| 6-9 | レスポンシブ・AppLayout規則確認 | ✅ | grid-cols sm:/lg:対応、AppLayout標準パターン準拠 |
| 6-10 | npm build | ✅ | 成功 |

### Phase 7: 得意先統合

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 7-1 | 決定的な名称正規化Service | ✅ | `ClientNameNormalizer`。候補提示のみ、自動確定禁止を維持 |
| 7-2 | 候補一覧 | ✅ | `ClientGroupService::candidates()`。未所属名称のみを対象、単独名称は除外 |
| 7-3 | group/member CRUD | ✅ | `ClientGroupController`（store/update/destroy/addMember/removeMember） |
| 7-4 | 保存前統合プレビュー | ✅ | `ClientGroupService::preview()`。DBへは書き込まない |
| 7-5 | 得意先分析画面の統合トグル（既定off） | ✅ | `ClientAnalysis.vue`。同月比較・年次分析と同じデフォルトOFFパターン |
| 7-6 | 誤統合防止tests | ✅ | `ClientNameNormalizerTest`（括弧内文言・法人格表記を勝手に同一視しないこと）、`SalesClientGroupHttpTest`（他グループ所属時の422・保存前プレビューが永続化しないこと） |

### Phase 8: Excel出力

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 8-1 | filter validation | ✅ | `AnnualAnalysisController::export()`（department_key/year/consolidate_clientsをvalidate） |
| 8-2 | 概要/月別/得意先/分類/項目/明細sheet | ✅ | `SalesExportService`（年次分析画面のみ。他4画面は要望があれば後続） |
| 8-3 | 会社統合条件反映 | ✅ | 2026-09-04、年次分析画面にトグル追加に伴い解消。`annualSummary($consolidateClients)`→`SalesExportService`→Excel出力まで一貫して反映される |
| 8-4 | formula injection対策 | ✅ | `setCellValueExplicit(..., DataType::TYPE_STRING)`＋先頭`=+-@`等へのアポストロフィ付与の二重対策 |
| 8-5 | stream download・一時ファイル削除 | ✅ | ob_start+php://output方式（既存ExpenseControllerと同パターン）。恒久保存なし |
| 8-6 | 画面と出力値一致test | ✅ | `SalesExportServiceTest`（KPI値・受注件数の一致、formula injection耐性）・`SalesAnnualExportHttpTest`（ヘッダー・監査ログ・権限） |

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

### Phase 11: REVIEW3対応 High3件

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 11-1 | 得意先詳細の期間絞り込み（`detail()`/`clientDetail()`に月境界追加） | ✅ | `ClientAnalysisController::detail()`にstart_month/end_month追加、`clientDetail()`を`clientRankingForPeriod()`と同じ月境界ロジックへ変更。`ClientAnalysis.vue`は既存refをparamsへ追加するのみ |
| 11-2 | 年次分析の`registered_months`/`missing_months`/`last_registered_month`追加 | ✅ | `annualSummary()`に3フィールド追加、`months_registered`は件数の意味に変更。`SalesExportService::buildOrdersSheet()`の引数を`last_registered_month`へ差替（動作変更なし）。`AnnualAnalysis.vue`に欠落月の警告バナーを追加 |
| 11-3 | 「全部署合計」の`coverage`（部署別登録状況）追加 | ✅ | `monthlyFiguresForYear()`が月ごとに`coverage.registered_departments`/`expected_departments`/`is_complete`を返すよう変更。`annualSummary()`の`monthly[]`へ`coverage`を追加。`AnnualAnalysis.vue`の月別表に「一部登録」バッジを追加 |
| 11-4 | 16.1節対応の回帰テスト追加 | ✅ | `SalesClientAnalysisTest`に境界年月絞り込みテスト1件、`SalesQueryServiceTest`に欠落月テスト1件・部署coverageテスト1件を追加。SalesAnalysis配下206件・プロジェクト全体320件成功、npm run build成功 |

### Phase 12: 可視化改修 Priority A

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 12-1 | 共通部品（PeriodNavigator/RankingPanel/useSalesChart）新設 | ✅ | `resources/js/Components/SalesAnalysis/`・`resources/js/Composables/useSalesChart.js` |
| 12-2 | バックエンド新規集計メソッド（recentMonthlyTrend/sameMonthAcrossYears/nearestRegisteredMonths/latestRegisteredMonth/monthlyClientPanel/monthlyBreakdownPanel） | ✅ | `SalesQueryService.php`、`MonthlyAnalysisController.php` |
| 12-3 | 月次分析画面（MonthlyAnalysis.vue）全面改修 | ✅ | KPI帯拡張・13ヶ月推移+移動平均・同月複数年比較・得意先比較モード切替・分類/項目タブ化 |
| 12-4 | 回帰テスト追加・全体テスト・build | ✅ | SalesAnalysis配下216件・プロジェクト全体330件成功、npm run build成功 |

### Phase 13: 可視化改修 Priority A 横展開（年次分析）

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 13-1 | PeriodNavigatorの年次対応（granularity/allowAllDepartments） | ✅ | `PeriodNavigator.vue` |
| 13-2 | バックエンド追加（latestRegisteredYear/annualClientPanel/annualBreakdownPanel） | ✅ | `SalesQueryService.php`、`AnnualAnalysisController.php` |
| 13-3 | AnnualAnalysis.vue改修（PeriodNavigator化・RankingPanel化・数値表折りたたみ） | ✅ | |
| 13-4 | 回帰テスト追加・全体テスト・build | ✅ | SalesAnalysis配下223件・プロジェクト全体335件成功、npm run build成功 |

### Phase 14: 可視化改修 Priority A 横展開（同月比較）

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 14-1 | PeriodNavigatorの`granularity='month-cyclic'`対応 | ✅ | `PeriodNavigator.vue` |
| 14-2 | `latestRegisteredMonthNumber()`追加 | ✅ | `SalesQueryService.php`、`SameMonthComparisonController.php` |
| 14-3 | SameMonthComparison.vue改修（PeriodNavigator化） | ✅ | 得意先マトリクス等はスコープ外（判断ログ参照） |
| 14-4 | 回帰テスト追加・全体テスト・build | ✅ | プロジェクト全体337件成功、npm run build成功 |

### Phase 15: 可視化改修 Priority A 横展開（得意先分析）＋深いリンク解消

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 15-1 | `ClientAnalysisController::index()`にdepartment_key/client_name深いリンク対応 | ✅ | |
| 15-2 | `clientAnalysisPanel()`＋`mergeClientAggregatesForRange()`共通化 | ✅ | 既存`clientRankingForPeriod()`は非破壊 |
| 15-3 | ClientAnalysis.vue改修（RankingPanel化・自動選択） | ✅ | |
| 15-4 | Monthly/AnnualAnalysis.vueの得意先クリックに深いリンク実装 | ✅ | Phase12/13で先送りしていた分 |
| 15-5 | 回帰テスト追加・全体テスト・build | ✅ | プロジェクト全体341件成功、npm run build成功 |

### Phase 16: 左右比較の横展開検討

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 16-1 | PeriodNavigator/RankingPanel適用可否の検討 | ✅（対象外判断） | 理由はPLAN1.md「Phase 16」参照。デュアル期間・デュアル金額構造が現行共通部品と不適合 |

これでREVIEW3 17章6番目（年次・同月比較・得意先分析・左右比較への横展開）が完了。

### Phase 17: 期別分析（4月〜翌3月）を新規画面として独立

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 17-1 | バックエンド（fiscalYear*系メソッド・FiscalYearAnalysisController・Excel出力） | ✅ | annualSummary()等の暦年ロジックには手を入れず完全新規メソッドとして実装 |
| 17-2 | PeriodNavigatorのyearLabel prop対応 | ✅ | 期別分析では「年度」表示、前後移動ボタンも汎用化 |
| 17-3 | 新規FiscalYearAnalysis.vue | ✅ | AnnualAnalysis.vueと同一構成、月配列は4月始まり |
| 17-4 | ナビゲーションタブに「期別分析」追加、暦年/年度スイッチ撤去 | ✅ | `useFiscalMode.js`composable削除。MonthlyAnalysis.vueの「年度累計」カードは「年間累計」（暦年固定）に簡素化 |
| 17-5 | 回帰テスト追加・全体テスト・build | ✅ | プロジェクト全体361件成功、npm run build成功 |

### Phase 18: 商品分析画面を新規追加（新規/取扱終了商品パネル含む）

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 18-1 | バックエンド（product*系メソッド・ProductAnalysisController） | ✅ | clientAnalysisPanel()等と対称構造、consolidate系引数は無し |
| 18-2 | productYearOverYearComparison()（新規/取扱終了商品・増減額上位） | ✅ | 直近登録年対前年で固定。前年未登録時はhas_comparison_pair=falseで空リスト |
| 18-3 | 新規ProductAnalysis.vue | ✅ | ClientAnalysis.vueと同一構成＋購入得意先ミニランキング追加 |
| 18-4 | ナビゲーションタブに「商品分析」追加、ルート登録、Ziggy再生成 | ✅ | 得意先分析の直後に配置 |
| 18-5 | 回帰テスト追加・全体テスト・build | ✅ | 売上分析262件成功（新規15件）、npm run build成功 |
| 18-6 | 新規/取扱終了商品の年度表記誤検知を修正（ProductNameNormalizer） | ✅ | 実例「2027年度用〜」対「2026年度用〜」を同一商品として扱うよう修正。範囲は新規/取扱終了パネルのみとユーザーに確認済み。売上分析271件成功（新規9件） |

### Phase 19: 会社別データ分離（サンエー印刷追加対応）

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 19-1 | DBマイグレーション6本（company_id追加・一意制約変更・sales_department_definitions新設・後方補完） | ✅ | 全てsales接続、クロスDBのためFK無し。MySQL識別子64文字制限に一度ひっかかり修正（sales_active_months） |
| 19-2 | SalesQueryService/SalesExportService/ClientGroupServiceにforCompany()追加 | ✅ | 41メソッドのシグネチャ変更を避け、インスタンス状態として会社IDを保持する設計 |
| 19-3 | SalesDepartmentsをDB参照に全面書き換え、10コントローラー・3サービス・1 FormRequestを対応 | ✅ | labelsFor()/enabledKeysFor()/labelForKey()/isEnabledFor()、全てcompanyId必須 |
| 19-4 | SalesImportService/SalesImportValidatorにcompanyId引数を追加、confirm時の会社不一致チェック追加 | ✅ | プレビュー時点と確定時点の会社一致を二重チェック（SuperAdminの会社切替タイミングずれ対策） |
| 19-5 | ClientGroupControllerのルートモデルバインディングに会社所属チェック追加 | ✅ | IDを推測した他社データ操作を防止（authorizeGroupCompany()） |
| 19-6 | 既存テスト20ファイルの修正（RefreshesSalesDatabaseにテスト会社自動作成・セッション自動設定） | ✅ | 各テストファイル個別修正を回避。売上分析275件成功 |
| 19-7 | フロントエンド11ページにhasCompanySelected対応 | ✅ | 未選択時「会社を選択してください」案内、自動fetchページは422を予防するガード追加。npm run build成功 |

**本番デプロイ時の未対応事項（別途実施が必要）:**
- 本番`sales_department_definitions`にサンエー印刷分を投入（`SalesDepartmentDefinitionSeeder`）
- 本番サンエー印刷Admin/Clerkの`company_id`確認
- サンエー印刷ユーザーへの`SalesAnalysisPermission`個別付与

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
| 2026-09-03 | 「年度累計・前年同期累計」は暦年（1〜12月）と会計年度（4月〜翌3月）の両方を実装し、画面で切替可能にする（当初「暦年のみ」の回答から変更） |
| 2026-09-03 | 会計年度の呼称は開始年を采用（2026年4月〜2027年3月 = 「2026年度」） |
| 2026-09-03 | 半期等の複数月まとめ取込に対応するため、source_typeへ`range`を追加（`monthly`/`annual`とは別種別）。Excelタイトルには開始月のみが記載され終了月は判定できないため、開始月・終了月は画面フォームで明示指定させる。タイトルの年・開始月とフォーム入力の一致は検証するが、終了月はタイトルでは検証しない。sales_importsに`source_month_end`カラムを追加 |
| 2026-09-03 | ユーザー実データ確認（1回目）: 年次ファイルもタイトル行に開始月が入る。フォームへの自動入力を**ファイル名**（例: 企画_2026年08月.xlsx）から行う方式に変更する方針で着手 |
| 2026-09-03 | ユーザー訂正（2回目、重要）: タイトル行の月は出力側（帳票ソフト）の設定により**月次・年次・範囲すべてで常に開始月固定**。半期(1-6月)でも年次でも「1月」としか出ない。よってExcelタイトルの年月は取込時の整合性チェックにも使えない。**タイトル年月とフォーム入力の一致検証は撤廃**し、各行のSB下版日とフォーム入力の照合（行レベル検証）のみで整合性を担保する方針に修正。部署ラベルの照合（タイトルの部署名とフォーム選択の一致）は年月と別の情報のため維持 |
| 2026-09-03 | PLAN 2.1変更: 「初期実装の分析対象は企画のみ」を撤回し、**企画・制作・オンデマンドの3部署すべてを初期版から取込可能にする**（ユーザー要望）。`SalesDepartments::ENABLED_KEYS`を3部署に拡張。DB設計は元々department_keyを持たせる設計だったため、スキーマ変更は不要 |
| 2026-09-03 | ユーザー指示: `z_instructions/SALES_ANALYSIS_REVIEW2.md`にCodex2回目レビュー結果（High2件・Medium4件・Low2件の指摘、除外処理の二段階方式仕様、次期画面設計案、ユーザー確認済み要望9件）が追記された。「ファイルを確認し、作業をすすめてください」との指示により対応着手 |
| 2026-09-03 | Codexレビュー2回目 High-1（`excluded_order_numbers`に正常な受注Noを指定すると検証をスキップして除外できてしまう問題）に対応: `SalesImportValidator::validate()`を「除外指定に関わらず全受注を検証→invalid_ordersを確定→除外リクエストとinvalid_ordersの積集合のみ除外を許可、正常受注や存在しない受注Noが1件でも含まれていたらファイル全体を確定不可にする」の二段階方式へ変更。他月重複エラーも同じ枠組みで個別除外可能にした |
| 2026-09-03 | Codexレビュー2回目 High-2（プレビューキャッシュが作成者と結び付いておらず、他ユーザーのプレビュートークンを確定できてしまう／同一トークンの同時確定で二重登録され得る問題）に対応: プレビュー保存時に`previewed_by`（Auth::id()）を暗号化データへ含め、確定時に一致を必須化。`SalesImportService::confirm()`をトークン単位の排他ロック（`Cache::lock()`、TTL60秒）で囲み、ロック取得失敗時は「現在処理中」エラーを返すよう変更。DB側にも`sales_imports.file_sha256`のUNIQUE制約を追加（migration `2026_09_03_100003`）し、異なるトークン間の競合（同一ファイルの二重アップロード等）を最終防御。QueryException（unique制約違反）を捕捉しユーザー向けメッセージへ変換 |
| 2026-09-03 | Codexレビュー2回目 8.1 Medium-1（`activated_at`最新を初期表示にすると、古い年度を後から登録した直後にその古い年度が開いてしまう問題）に対応: `DashboardController::index()`のソート順を`sales_year`→`sales_month`→`activated_at`の優先順に変更（対象期間そのものを優先し、取込操作の時刻はタイブレークのみに使う） |
| 2026-09-03 | 回帰テスト13件追加（Validator: 除外の正当性検証3件・他月重複除外1件、Service: プレビュー作成者不一致1件・同一ユーザー確認1件・トークン再利用拒否1件・fileストア大容量1件（既存）、HTTP: 除外系2件・他ユーザー確定拒否1件、Dashboard: 初期表示順序1件）。SalesAnalysis配下テスト計95件全成功 |
| 2026-09-03 | PLAN1.mdへPhase 6B（データ登録状況画面）・Phase 6C（年次分析・同月比較・左右比較画面）を追記（Codexレビュー2回目11章の実装順指示に従う）。新画面の実装は着手前にワイヤーフレーム・JSON例をユーザーへ提示し確認を取る方針（Codex 12章の指示どおり） |
| 2026-09-04 | 同月比較画面の「分類別・項目別」増減表示の範囲をユーザーへ一問確認: 「去年だけよかった時などあるので、できれば1・3・5年比較があるとよい」との回答により、複数年マトリクスにはせず**基準年（直近登録年）に対し1年前・3年前・5年前の3点比較**で確定。PLAN1.md「6D-2」を`category_item_comparison`の`comparisons`（`years_ago`配列）形式に修正 |
| 2026-09-04 | 同月比較画面を実装。`SalesQueryService::sameMonthComparison()`（年配列は今年を終点に5/10年分を機械的生成、`monthFigures()`/`monthOrdersGroupedByClient()`/`buildSameMonthClientComparison()`/`monthOffsetComparison()`を新設し既存の`activeOrdersQuery(array)`・`resolveDepartmentKeys()`・`clientDisplayNameResolver()`・`periodDetailBreakdown()`を再利用）、`SameMonthComparisonController`（index/summary）、`SameMonthComparison.vue`を新規作成。ルート`same_month_comparison`/`api.same_month_comparison`を`$registerSalesAnalysisRoutes`へ追加。AnnualAnalysis.vue⇄SameMonthComparison.vueに相互導線を追加 |
| 2026-09-04 | 得意先軸（年次マトリクス・新規/離脱・増減額上位）は「表示年のうち最新登録年 対 その前年」の2年間のみで判定、分類・項目軸は基準年に対し1・3・5年前のオフセット比較（未登録年はnull、0円と誤表示しない）という設計どおりに実装。回帰テスト16件追加（`SalesQueryServiceTest`に年配列生成・前年差・全部署合算・needs_review・得意先マトリクス（未登録年null判定含む）・分類オフセット比較（欠落年のnull判定含む）の6件、新規`SalesSameMonthComparisonTest`にindex空表示/深いリンク/アクセス制御、summary形状/全部署合計/years不正値拒否/不正部署拒否/アクセス制御/統合トグルの9件）。Docker Desktop停止によりテスト実行が一時中断、ユーザーに起動を依頼し再開。SalesAnalysis配下テスト計132件・プロジェクト全体246件成功（27件skipは既存の設定ルート未登録によるもので無関係）、npm run build成功（manifestにSameMonthComparison出力確認済み）、Ziggy再生成・config/cache/routeキャッシュクリア実施 |
| 2026-09-04 | ユーザー指示「次にやることに進んでください」を受け、「6.次にやること」2番目の左右比較画面に着手。PLAN1.mdへ「6E. 左右比較 詳細設計」（ルーティング方針・ワイヤーフレーム・JSON例）を追記。既存の`periodOrdersGroupedByClient()`/`periodDetailBreakdown()`（year,startMonth,endMonth形式）をそのまま両側（A/B）に再利用する設計とし、「同月前年」は独立APIモードにせずUI側の入力補助（月対月モードのショートカット）とする方針にした。「年対年」で登録済み月数が両側で異なる場合（進行中の年を含む比較）にAとBの期間を揃えるかどうかが唯一の設計判断点だったため一問確認。ユーザー回答: 「揃えず、それぞれの実績をそのまま出す（推奨）」で確定。PLAN1.md 6E-2の設計どおり実装着手 |
| 2026-09-04 | 左右比較画面を実装。`SalesQueryService::sideBySideComparison()`（期間A/Bは`['type'=>'year'\|'month', ...]`形式）を新設。既存`monthFigures()`を月範囲対応の`rangeFigures()`へ一般化（単一月呼び出しは委譲）、既存`monthOrdersGroupedByClient()`も同様に`rangeOrdersGroupedByClient()`へ一般化し、同月比較・左右比較で共用。年型期間は「その年のうち登録済みの月」だけを合算し、AとBの期間長は揃えない（ユーザー確認どおり）。得意先/分類/項目は`combineSideBySideRows()`で両側のラベルを突き合わせ、片方にしか無い項目も0円で残す（新規行はrate=null、消滅行はrate=-100.0）。`SideBySideComparisonController`（index/summary）、`SideBySideComparison.vue`（年対年/月対月モード切替、「同月前年にする」ショートカットボタン）を新規作成。ルート`side_by_side_comparison`/`api.side_by_side_comparison`を追加。AnnualAnalysis.vueに導線を追加 |
| 2026-09-04 | 回帰テスト14件追加（`SalesQueryServiceTest`に年対年の期間長不一致・月対月・未登録期間null判定・得意先0円補完（新規/消滅のrate判定含む）・全部署合算・得意先統合トグルの6件、新規`SalesSideBySideComparisonTest`にindex表示/アクセス制御、summary形状（年対年/月対月）/month必須バリデーション/不正部署拒否/アクセス制御/全部署合計の8件）。SalesAnalysis配下テスト計160件・プロジェクト全体260件成功（27件skipは既存の設定ルート未登録によるもので無関係）、npm run build成功（manifestにSideBySideComparison出力確認済み）、Ziggy再生成・config/cache/routeキャッシュクリア実施。これでPLAN1.md「6.次にやること」1〜2番目（同月比較・左右比較）が完了、次は3番目（得意先分析画面）とPhase 7（得意先統合）の接続 |
| 2026-09-04 | ユーザー指示「続けてください」を受け、Phase 7（得意先統合）に着手。REVIEW2.md 11章の実装順（Phase 7=得意先統合ON/OFFと統合管理、得意先分析画面と接続）に従い、既存モデル（`SalesClientGroup`/`SalesClientGroupMember`）のみでController/Service/UIが未実装だったことを確認。PLAN1.mdへ「Phase 7-0 詳細設計」（正規化アルゴリズム`ClientNameNormalizer`の変換内容・得意先統合設定画面/得意先分析画面のワイヤーフレーム・JSON例）を追記。正規化は候補提示のみで自動確定しない設計（PLAN 2.7の既存合意を踏襲）。得意先分析画面は「得意先を選んでその推移を見る」逆方向の画面として新規実装する意味があると判断し、新規/離脱集計は同月比較・左右比較と重複するため実装しない方針にした。コードの実装はまだ行っていない（確認事項2点をユーザーへ提示予定） |
| 2026-09-04 | 確認事項2点をユーザーへ一問ずつ提示、両方とも推奨案で確定（統合設定画面へのアクセス権限は既存の売上分析権限と同一／得意先分析のランキング初期表示期間は登録済み全期間）。実装着手 |
| 2026-09-04 | Phase 7実装。`ClientNameNormalizer`（正規化候補生成専用、mb_convert_kana('KVa')＋空白除去＋括弧幅統一のみ。法人格表記・括弧内文言は変更しない）、`ClientGroupService`（unassignedClients/candidates/groups/preview）、`ClientGroupController`（index/data/store/update/destroy/addMember/removeMember/preview）、`ClientGroups.vue`を新規作成。グループ作成・メンバー追加は常にユーザーの手動操作のみで確定（自動統合なし）。監査ログ（`sales_audit_logs`）にはmember_countのみ記録し得意先名は含めない（PLAN 4.8の既存規則どおり） |
| 2026-09-04 | 得意先分析画面を実装。`SalesQueryService::clientRankingForPeriod()`（年またぎ期間対応、キーワード絞り込み前の合計をshare_pct分母に固定）・`clientDetail()`（年別推移・受注一覧、統合ON時は`rawNamesForDisplayName()`でグループ名→原名称群へ逆引き）を新設。`ClientAnalysisController`（index/ranking/detail）、`ClientAnalysis.vue`（ランキング→個別得意先の推移・受注一覧の2段階構成）を新規作成。新規/離脱集計は同月比較・左右比較と重複するため実装しないというPhase 7-0設計どおりとした。ルート追加、年次分析画面に「得意先分析」「得意先統合設定」への導線を追加 |
| 2026-09-04 | 回帰テスト33件追加（Unit `ClientNameNormalizerTest` 6件、`SalesClientGroupServiceTest` 8件（正規化候補・未所属抽出・プレビュー非永続化・年またぎランキング・統合済みdetail等）、`SalesClientGroupHttpTest` 11件（CRUD・他グループ所属時の409/422・監査ログへの得意先名非混入・アクセス制御）、`SalesClientAnalysisTest` 8件）。テスト作成中に「同一年月へ`seedMonth`を2回呼ぶとactive版が上書きされ1件目のデータが集計対象から消える」というテストヘルパー側のバグを2箇所で発見・修正（複数得意先は1回のimportにまとめる`seedMonthOrders`方式へ変更）。SalesAnalysis配下テスト計179件・プロジェクト全体293件成功（27件skipは既存の設定ルート未登録によるもので無関係）、npm run build成功（manifestにClientGroups/ClientAnalysis出力確認済み）、Ziggy再生成・config/cache/routeキャッシュクリア実施。これでPLAN1.md「6.次にやること」3番目（得意先分析）とPhase 7（得意先統合）が完了。次はPhase 8（Excel出力）・Phase 9（バックアップ）・Phase 10（総合検証・リリース） |
| 2026-09-04 | ユーザー実機報告: 同月比較・左右比較のボタンを押すと「データの取得に失敗しました」。Docker停止で一時中断していたテスト実行環境をユーザーがDocker Desktop再起動で復旧後、調査再開 |
| 2026-09-04 | 調査: `SalesQueryService`をtinkerで直接実行（全部署×全月×5/10年のスイープ）→エラーなし。実HTTPカーネル経由で認証済みリクエストを直接dispatchし同一パラメータで200 OK確認。`storage/logs/laravel.log`に本日分のエラーなし。`route:list`でルート登録も正常 → サーバー側は正常と判断し、ユーザーへハードリフレッシュを依頼したが改善せず |
| 2026-09-04 | ユーザーにブラウザDevToolsのNetworkタブ確認を依頼。`GET .../api/same-month-comparison?...&consolidate_clients=false`が**422**であることが判明。原因特定: Laravelの`'boolean'`バリデーションルールは`true`/`false`/`0`/`1`/`'0'`/`'1'`のみを許可し、クエリ文字列由来の**文字列**`"true"`/`"false"`（axiosがJSのbooleanをそのままクエリへ渡すとこの形になる）を拒否する既知の挙動。自分のtinker/PHPUnit再現テストは`route()`ヘルパーの`http_build_query()`がbool→`"1"`/空文字に変換していたため偶然この不具合を踏んでおらず、テストで検知できていなかった |
| 2026-09-04 | 既存の`MonthlyAnalysis.vue`（動作実績あり）が`consolidateClients.value ? 1 : 0`という変換で同じ問題を回避していたパターンを発見。同じ修正を`SameMonthComparison.vue`・`SideBySideComparison.vue`・`ClientAnalysis.vue`（ranking/detail両方）の計4箇所に適用。回帰テスト6件追加（'0'/'1'文字列送信が成功すること、'true'/'false'文字列が422になることをそれぞれ明示検証）。SalesAnalysis配下テスト全成功、プロジェクト全体299件成功、npm run build成功、config/cacheクリア実施。**教訓: axiosでbooleanをGETクエリへ渡すときは必ず`? 1 : 0`等で数値/文字列化すること。PHPUnitの`route(name, [...])`によるURL生成はbool値を`http_build_query`経由で暗黙変換するため、この種のクエリ文字列バグを再現できない場合がある（生のクエリ文字列を直接connat/結合するテストで初めて検知できた）** |
| 2026-09-04 | ユーザー指示「あとで細かい要望を言うので、残りがあれば進めてください」を受け、Phase 8（Excel出力）に着手。既存の`ExpenseController`のob_start+php://outputストリーム出力パターンを踏襲。PLAN 2.8が要求するシート構成（概要・月別推移・得意先別・分類別・項目別・該当明細）に最も一致する「年次分析」画面を対象に実装（同月比較・左右比較・得意先分析への拡張は要望があれば後続対応）。`SalesQueryService::periodOrders()`（該当明細シート用の受注一覧取得）、`SalesExportService`（`annualAnalysisWorkbook()`、PhpSpreadsheetで6シート構築）を新規作成。formula injection対策として得意先名・品名等の利用者由来文字列は`setCellValueExplicit(..., DataType::TYPE_STRING)`で明示的に文字列型として書き込み、先頭が`=+-@`等の場合はアポストロフィも付与する二重対策を実装（PLAN 3.4の要件）。`AnnualAnalysisController::export()`を追加しExcel出力操作を`sales_audit_logs`へ記録（得意先名は含めない）。ルート`annual_analysis.export`を追加、`AnnualAnalysis.vue`に「Excel出力」ボタンを追加（`<a href>`直接ナビゲーション、既存の請求書Excel出力と同じダウンロードパターン） |
| 2026-09-04 | **作業中の事故と復旧（重要）**: `SalesExportService`の動作確認のため`php artisan tinker`で`planning`部署・2026年1月のダミーデータを直接作成した際、`SalesActiveMonth::updateOrCreate()`が**本物のローカル開発DB（sunbwork_sales、ユーザーが実際に取込済みの2024年1月〜2026年6月分）**の2026年1月のactive pointerを、ダミーデータの取込（id=4）へ上書きしてしまった（本来有効だったのはユーザーが2026-09-03に取り込んだ「企画_2026年01-06月.xlsx」＝id=2）。取込履歴照会で発見し、即座に`sales_active_months`のポインタをid=2へ戻し、ダミーのimport/orderレコード（id=4）を削除して復旧。`sales_active_months`の行数が事故前と同じ30件であることを確認済み。本番DBには一切触れていない。**教訓: tinkerでの動作確認は`RefreshDatabase`が効くPHPUnitテスト（`sunbwork_sales_testing`）内で行うか、既存データと衝突しない架空の年月・部署を使うこと。実際に運用中のローカルDBに対して`updateOrCreate`等の書き込み系操作を安易にtinkerで実行しない** |
| 2026-09-04 | ユーザー要望「価格協力等の値引きで単価・金額がマイナスになるが、損益を正しく測るためマイナスを許容する方針に変更してほしい」。確認したところ単価・M列（明細金額）は2026-09-03から既に負数許容済みで、未対応なのはN列（受注金額、受注単位の最終合計）のみと判明。一問確認したところ、ユーザーは実データ（受注No 4304133）を調査し「正の値がありません」というエラーメッセージだけでは原因（マイナスなのか、空欄で関連行が無い孤立データなのか）を判別できなかったと報告。方針を「①N列の負数を許容する（事故・刷り直し等で受注全体がマイナスになり得るため除外しない）②エラーメッセージに空欄行数・0円行数・正負の内訳を明示し原因を判別可能にする」の2点に確定 |
| 2026-09-04 | `SalesImportValidator`を修正。行レベルの受注金額（N列）負数拒否チェックを削除。`buildOrder()`のN列規則を「正の値がちょうど1行・最後の行」から「0以外の値（正または負）がちょうど1行・最後の行」へ緩和。エラーメッセージを詳細化: 該当行が無い場合は「全{総行数}行中、空欄{n}行・0円{n}行」を明示、複数行ある場合は「正{n}行・負{n}行」の内訳を明示、位置違反の場合は実際の値と符号（正/負）を明示。PLAN1.md（2.4/2.3/5.2節）を新方針に合わせて更新（「値引き・返品・取消によるマイナス受注金額はない」の記述を撤回）。DBスキーマ（`sales_orders.order_amount` decimal(15,2)、unsigned制約なし）・集計ロジック（`SalesQueryService`の`>0`ガードは全てdiv-by-zero防止でnullへ安全に縮退）に追加変更は不要と確認 |
| 2026-09-04 | 回帰テスト更新: 既存の`test_negative_amount_is_rejected`（2026-09-03のM列許容より前の名残テスト）を新仕様（受注全体マイナスを許容）に合わせて`test_negative_amount_single_row_order_is_now_allowed`へ書き換え。既存2件（正の値0件・複数件）を新メッセージに合わせて更新・改名。新規5件追加（0円/空欄の詳細メッセージ・複数0以外値・受注No付きの空欄検証・負数が最後の行なら許可・負数が最後の行でなければ引き続き拒否）。SalesAnalysis配下テスト計197件・プロジェクト全体311件成功（回帰なし）。Vue側の変更は無し（エラーメッセージ文字列に依存した表示分岐が無いことを確認済み）のためnpm run buildは不要 |
| 2026-09-04 | REVIEW3.md 11.2節のHigh 3件（得意先詳細が期間を無視／年次の`months_registered`が登録月数ではなく最終登録月／「全部署合計」で一部部署未登録の月が完全登録に見える）の対応方針について、欠落月・部分登録月がある場合の年間合計の扱いを一問確認。ユーザー回答:「含めて警告表示（推奨）」で確定。欠落・部分登録があっても期間合計（period_amount等）にはそのまま実データを含め、比較不可にはしない。UIには`missing_months`/`coverage.is_complete`に基づく警告バッジを表示する方針とした（比較数値は隠さない） |
| 2026-09-04 | 年次分析への展開スコープを、REVIEW3 13.3節の全ワイヤーフレーム（3/5年切替の複数年重ね線・12ヶ月移動合計・Pareto構成比等）ではなく、14章のPriority A分類（同月3年平均差・3ヶ月移動平均は月次専用、Top10/20+詳細・得意先別増減寄与・期間ナビゲーターは全画面共通）に厳密に従う方針とした。理由: 14章が「まずPriority Aを完成させる」と明示しており、13章の画面別ワイヤーフレームは各Priorityの実現イメージであって全項目が横展開対象ではないため。12ヶ月移動合計・Pareto構成比はPriority B（14章）に分類されており、17章7番目で扱う。ユーザーへの確認は行っていない（Claude裁量の範囲と判断。REVIEW3 12.1「名称や最終デザインは任せる」の趣旨に従う） |

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
| 2026-09-03 | Claude Code | Phase 4着手・方針提示・承認取得。SalesImportService実装（confirm/nextVersion/affectedMonths/calculateDiff） |
| 2026-09-03 | Claude Code | ImportController::store()追加、preview()に差分情報を追加。ImportHistoryController・ImportHistory.vue実装 |
| 2026-09-03 | Claude Code | Import.vueに確定ボタン・差分表示を実装。ルート追加・Ziggy再生成・npm build成功 |
| 2026-09-03 | Claude Code | Feature test 3ファイル追加（版管理6件、確定HTTPフロー3件、既存分と合わせ計43件）すべて成功 |
| 2026-09-03 | Claude Code | Phase 4完了。Phase 5(集計API/Controller)着手前の設計確認をユーザーへ提示予定 |
| 2026-09-03 | Claude Code | Phase 5方針提示。年度累計の年度区分についてユーザーと調整（暦年→暦年+会計年度両対応・切替可能、会計年度呼称は開始年）し承認取得 |
| 2026-09-03 | Claude Code | SalesQueryService実装（monthlyTotal/monthlyComparison/fiscalYearToDate/monthlyTrend/clientRanking/categoryBreakdown/itemBreakdown/searchByProductName） |
| 2026-09-03 | Claude Code | DashboardControllerにAPIアクション追加（summary/trend/clients/categories/items/products）、ルート・Ziggy追加 |
| 2026-09-03 | Claude Code | MySQL only_full_group_by対応: activeOrdersQuery()のデフォルトselectがselectRaw集計と衝突する不具合を発見・修正 |
| 2026-09-03 | Claude Code | Feature test 2ファイル追加（SalesQueryServiceTest 11件、SalesDashboardApiTest 5件）。SalesAnalysis配下合計59件全成功 |
| 2026-09-03 | Claude Code | Phase 5完了。Phase 6(ダッシュボードUI)着手前の設計確認をユーザーへ提示予定 |
| 2026-09-03 | Claude Code | Phase 6着手・承認取得。DashboardController::index()で最新active月を初期表示データとして提供 |
| 2026-09-03 | Claude Code | Dashboard.vue本実装（フィルタ・KPIカード・5年折れ線・得意先棒グラフ+全件表示・分類/項目内訳・品名検索）。AnalysisPanel.vueのChart.jsパターンを踏襲 |
| 2026-09-03 | Claude Code | npm build成功。SalesDashboardIndexTest 3件追加、SalesAnalysis配下合計62件全成功 |
| 2026-09-03 | Claude Code | Phase 6完了。Phase 7(得意先統合)着手前の設計確認をユーザーへ提示予定。ブラウザでの実動作確認はユーザー依頼待ち |
| 2026-09-03 | Claude Code | ユーザー報告: SuperAdminダッシュボードに売上分析への入り口が見当たらない。調査の結果、実際のナビ本体はAppLayoutの#tabsスロットが自動描画する`SuperAdminNavigationTabs.vue`等のロール別タブコンポーネントであり、そちらに未追加だったことが判明（ヘッダーアイコンのみでは不十分だった）。`SuperAdminNavigationTabs.vue`（+利用許可設定タブ）・`AdminNavigationTabs.vue`・`ClerkNavigationTabs.vue`に売上分析タブを追加、npm build成功 |
| 2026-09-03 | Claude Code | ユーザー要望: 半期（1-6月等）まとめ取込に対応、Import画面にファイル名サンプルの説明文を追加。設計提示・承認取得（判断ログ参照） |
| 2026-09-03 | Claude Code | migration `2026_09_03_100001` でsales_importsにsource_month_end追加。SalesImportValidator/SalesImportService/UploadSalesWorkbookRequest/ImportControllerをsource_type='range'に対応（開始月はタイトルと照合、終了月はフォーム入力のみを正とし範囲外の行はエラー） |
| 2026-09-03 | Claude Code | Import.vueに種別「範囲指定（半期など）」と開始月・終了月の入力欄、月次/年次/範囲それぞれのファイル名サンプルとタイトル行仕様の説明文を追加。npm build成功 |
| 2026-09-03 | Claude Code | range対応のテスト10件追加（Validator 3件、Service 1件、Preview HTTP 2件、+関連ヘルパー整備）。SalesAnalysis配下合計68件全成功 |
| 2026-09-03 | Claude Code | ユーザー報告: SuperAdminで売上分析へ移動するとAppLayoutのロールタブがUserになってしまう。原因: `currentRouteContext`がルート名プレフィックス（superadmin./admin./clerk.等）でロール判定するため、単一の`sales_analysis.*`名前空間だと`user`にフォールバックしていた。設計提示・承認取得（判断ログ参照） |
| 2026-09-03 | Claude Code | 既存の`ClientController`パターンに合わせ、`superadmin`/`admin`/`clerk`ミドルウェアグループ内にsales-analysisルートを複製登録する共通クロージャ`$registerSalesAnalysisRoutes`をroutes/web.phpに追加。URLも`/superadmin/sales-analysis`等のロール別prefixに変更 |
| 2026-09-03 | Claude Code | `ResolvesSalesAnalysisRoutePrefix`traitを追加しController(Dashboard/Import/ImportHistory)がInertia propsへ`routePrefix`を渡すように変更。Dashboard.vue/Import.vue/ImportHistory.vueのroute()呼び出しを動的化（`rn()`ヘルパー）。AppLayout.vueのヘッダーアイコン・レスポンシブナビもロール別ルート名に対応。SuperAdmin/Admin/ClerkNavigationTabs.vueも合わせて更新 |
| 2026-09-03 | Claude Code | 既存テスト5ファイルのルート名をロール別（superadmin.sales_analysis.*等）に更新。ziggy再生成・npm build成功。SalesAnalysis配下68件・既存回帰確認11件、すべて成功 |
| 2026-09-03 | Claude Code | ユーザー報告: 08月ファイルを読み込むと対象月が9月になる。原因調査の結果、Import.vueの「対象月」初期値が`new Date().getMonth()+1`（＝今日の月）で、ファイル選択と連動せず残ってしまうUI設計が原因と判明（getMonth()自体の0始まり変換は正しい）。ユーザーに対応方針を確認し「ファイル選択時に自動で年月を読み取りフォームに反映」を採用 |
| 2026-09-03 | Claude Code | `SalesWorkbookReader::readTitleOnly()`（タイトル行のみの軽量読取）、`ImportController::inspectTitle()`、`InspectWorkbookTitleRequest`を追加。ファイルバリデーションの重複を`ValidatesXlsxFile`traitへ共通化（UploadSalesWorkbookRequestも移行）。ロール別ルート`*.sales_analysis.import.inspect_title`を追加 |
| 2026-09-03 | Claude Code | Import.vueのonFileChangeを非同期化し、ファイル選択直後にタイトル読取APIを呼んで対象年月（rangeなら開始月、annualなら種別も）を自動反映。読取中/自動入力済みの状態表示を追加。npm build成功 |
| 2026-09-03 | Claude Code | SalesImportInspectTitleTest 3件追加。SalesAnalysis配下合計71件全成功 |
| 2026-09-03 | Claude Code | ユーザー報告: (1)自動読取後も対象月が9のまま「検証処理に失敗しました」となる (2)入力欄がファイル選択より上にあり手動入力を誘発している。原因(1): `mimetypes`バリデーションルールがOS/ブラウザのfinfo判定に依存し、xlsx(実体はzip)を`application/octet-stream`等と誤判定する環境で422になっていた。`ValidatesXlsxFile::xlsxFileRules()`から`mimetypes`ルールを削除し、拡張子＋ZIPマジックバイト検証のみに変更（安全性は維持） |
| 2026-09-03 | Claude Code | 原因(2)対応: Import.vueを再構成。ファイル選択を①として先頭に固定表示し、②の対象部署/種別/年月フォームは`selectedFile`がセットされてから出現する構成に変更。年月の既定値（今日の日付）は廃止し、タイトル読取結果のみを使う（読取失敗時はプレースホルダ「未取得」でユーザー入力を促す）。npm build成功、SalesAnalysis配下71件全成功（回帰なし） |
| 2026-09-03 | Claude Code | ユーザー報告: 実データ（年次ファイル）でタイトル読取が失敗し年月が自動入力されない。原因調査で「タイトルの月は出力側設定により常に開始月固定」という運用実態が判明（判断ログ参照）。自動入力の情報源をExcelタイトル行からファイル名パースへ全面変更 |
| 2026-09-03 | Claude Code | Import.vueにファイル名パースロジック（`parseFileName`）を実装し、サーバー往復（inspect-title API）を廃止。SalesImportValidatorから`validateTitlePeriod()`を削除（タイトル年月とフォーム入力の照合を撤廃、行レベルのSB下版日照合のみ残す）。不要になった`inspectTitle`系コード一式（Controller/Request/Reader::readTitleOnly/ルート/テスト）を削除 |
| 2026-09-03 | Claude Code | SalesImportValidatorTestの該当2件を新仕様に合わせて修正（タイトル不一致テスト→行データ不一致テストへ、rangeのタイトル不一致テスト→「タイトル不一致でも行データが範囲内なら有効」テストへ）。npm build成功。SalesAnalysis配下68件全成功 |
| 2026-09-03 | Claude Code | ユーザー報告: 対象部署が企画のセレクタしかなく制作・オンデマンドを選べない。ユーザー確認の結果、3部署すべてを初期版から取込可能にする方針に変更（PLAN 2.1修正） |
| 2026-09-03 | Claude Code | `SalesDepartments::ENABLED_KEYS`を`['planning']`から3部署へ拡張。DB/UI/Vueは元々部署キー非依存の汎用実装だったためコード変更はこの1箇所のみ。「部署が無効」前提だった既存テスト2件を「存在しない部署キー」テストに修正し、制作・オンデマンドの取込成功を確認するテストを追加。SalesAnalysis配下69件全成功（Vue側はビルド不要） |
| 2026-09-03 | Claude Code | ユーザー報告: 実際のアップロードで`import/preview`が500エラー。laravel.logで`hash_file(): ... Is a directory`を確認。原因は`storage/app/private/sales_imports`ディレクトリがroot:root・700権限になっており書き込めなかったこと（環境要因、コードのバグではない）。1回目の修正（www-data:www-data）は誤りで再発 — `ps aux`で実際のWebサーバー(`php artisan serve`)は`sail`ユーザー（UID1000）で稼働していると判明。`chown -R sail:sail storage/app/private && chmod -R 775`に修正し、sailユーザーでの保存・hash計算を実際に検証して解決を確認 |
| 2026-09-03 | Claude Code | ユーザー報告: 実データで「判型が空でエラー」「受注金額0でエラー」が発生。ユーザー指示により**実装作業を一旦停止**。「空データはエラーなしでとりあえず取り込みたい」「M/N列は複数行にまたがり最後に合計になる仕様（既に説明済み）をちゃんと生かしてほしい」との要望を受け、`z_instructions/SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md`を作成しCodexへレビュー依頼。PLANの合意事項「M/N不一致は警告またはエラー」に対し実装は無条件でエラー化していた不整合を含め、Codexの提案を待って再設計する |
| 2026-09-03 | Codexレビュー結果（詳細は`SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md`6章）: High-1空欄行の丸ごと除外、High-2 ValidatorとDBのnullable不整合、High-3 N列「最後の行のみ正値」規則の未検証、High-4 年次/range再取込で受注0件になった月のactive_monthが旧版のまま残る別バグを発見。Medium: M/N不一致の扱い、日付実在性未検証、金額float比較のリスク |
| 2026-09-03 | ユーザー確認（Codex提案の最初の一問）: **M列合計とN列受注金額が不一致でも、N列を正式な売上として採用し、差額は「未配賦額」として表示したうえで警告付き取込を許可する**（従来のPLAN記述「M/N不一致は取込検証、不一致なら自動確定せず警告またはエラー」を「エラーではなく警告として扱う」方向に確定） |
| 2026-09-03 | Codexレビュー6.6節の作業順に沿って再設計を実装完了。SalesImportValidatorを全面書き換えし、blocking error（受注No・SB下版日欠損/解析不能、実在しない日付、受注内の得意先名・品名・下版日矛盾、N列規則違反、負数）とwarning（得意先名・品名・分類・項目・判型・色数・台数・単価・金額の空欄、M/N不一致=未配賦額）を分離。行の部分除外を廃止（High-1解消） |
| 2026-09-03 | N列（受注金額）規則を実装: 正の値を持つ行は同一受注内でちょうど1行・かつ最後の行であることを検証（0件/複数件/非最終行はerror）。途中行のNULLは0として扱い警告のみ（High-3解消） |
| 2026-09-03 | nullable化migration `2026_09_03_100002`をsales DBへ適用（client_name/product_name/category/item_name/format_size/color_count/quantity/unit_price/line_amountをNULL許容化）。doctrine/dbal未インストール環境のため生SQL ALTER TABLEで実装。`sales_orders.unallocated_amount`カラム追加（High-2解消） |
| 2026-09-03 | SalesImportService::targetMonths()を新設し、active month切替を「実際に受注データがある月」から「取込指定範囲全体（monthly=1ヶ月/range=開始〜終了月/annual=1〜12月）」へ変更。受注0件の月でも新版のactive pointerへ切り替わるようにした（High-4解消）。差分表示用の`affectedMonths()`は従来通り「データがある月のみ」で維持 |
| 2026-09-03 | SalesWorkbookReader::normalizeDate()にcheckdate()による実在日検証を追加（例: 2026/02/31を拒否）（Medium-2解消） |
| 2026-09-03 | SalesQueryService: 得意先名/分類/項目がNULLの場合に「（得意先未設定）」等のラベルでグループ化する対応を追加。monthlyTotal()にtotal_unallocated_amountを追加。Dashboard.vue/Import.vueに未配賦額の表示を追加 |
| 2026-09-03 | 既存回帰テスト2件（annual/range のactive month切替テスト）を新仕様（範囲全体を切替対象とする）に合わせて更新。Codexの15項目テストケースを参考に新規回帰テスト9件を追加（optional欄空欄許容、N列途中NULL、N列正値0件/複数件/非最終行、M/N不一致=warning、不正日付拒否、annual再取込での0件月切替、nullable保存の確定成功）。SalesAnalysis配下テスト計78件全成功 |
| 2026-09-03 | `php artisan test`でプロジェクト全体のテストスイートを実行し既存機能への回帰がないことを確認（192件成功・27件skip（既存のsettings routes未登録によるもので今回の変更と無関係）・失敗0件）。Codexレビュー6.6節の作業順（ステップ1〜10）が完了 |
| 2026-09-03 | ユーザー実機検証（1回目）: (1)項目・判型の空欄がエラーに見える (2)金額（M列）の負数が事故損金入力で発生するが拒否される (3)N列に正値が無い受注（Excelのミス）1件で全体が確定不可になる、の3件を報告。(1)は既にwarning実装済みと確認（②③のエラーが同画面に混在して見えていたと推測）。②③は方針を提示しユーザー確認を取得（判断ログ次項） |
| 2026-09-03 | ユーザー確認: 負数を許可する項目は**金額・単価**（色数・台数は従来通り負数拒否）。事故損金等の値引き・調整行を想定 |
| 2026-09-03 | ユーザー確認: 「エラーのある受注を個別に除外して残りを取込む」設計で進める（推奨案採用）。受注No自体が読み取れない行は除外不可としファイル全体を止める仕様を維持（安全のため） |
| 2026-09-03 | SalesImportValidatorを再構成: groupRows()が受注No単位にエラー・警告をバケツ化し、受注に紐づくblocking errorは`invalid_orders`（ファイル全体を止める`errors`とは別枠）へ分離。`excludedOrderNumbers`引数を追加し、指定受注を完全スキップ（DB未保存・warningsに記録）できるようにした。checkCrossMonthDuplicates()も受注No単位のエラーマップへ変更。負数チェックはOPTIONAL_NUMBER_FIELDSのうち金額・単価を対象外化 |
| 2026-09-03 | 実装中に発見: PHPは純数字文字列（例:"8000013"）を配列キーにすると自動的にintへ変換するため、受注No単位のバケツ化を配列キーだけに頼ると、除外リスト（HTTPからは文字列で渡る）との厳密比較がずれて除外が効かない場合があるバグを検出。バケツ内に`order_number`を文字列のまま明示的に保持する方式に修正して解消（テスト`assertSame`で顕在化） |
| 2026-09-03 | UploadSalesWorkbookRequest/ImportControllerに`excluded_order_numbers`の受け渡しを追加。Import.vueに「エラーのある受注」一覧＋チェックボックス＋「選択した件数を除外して再検証」ボタンを追加（ファイルは選び直さず保持したFileオブジェクトで再送信）。npm run build成功 |
| 2026-09-03 | SalesImportValidatorTestを新仕様に合わせて更新（受注単位エラーは`invalid_orders`を検証するよう6件修正）。負数許可2件・受注除外機能2件（除外で残りが確定できること／受注No不明行は除外不可でファイル全体を止めること）の新規テスト4件を追加。SalesAnalysis配下テスト計82件全成功 |
| 2026-09-03 | プロジェクト全体`php artisan test`を再実行し回帰なしを確認（196件成功・27件skip・失敗0件）。ユーザーへ実機再確認を依頼 |
| 2026-09-03 | ユーザー実機検証（2回目）: 「対象部署が一致しません（ファイル記載: ｵﾝﾃﾞﾏﾝﾄﾞ / 選択: オンデマンド）」を報告。原因は帳票ソフト出力の半角カナ表記と全角カナ比較のずれ。`SalesWorkbookReader::parseTitle()`でタイトルから抽出した部署ラベルを`mb_convert_kana($label, 'KV')`で全角へ正規化するよう修正（Vフラグで半角濁点も結合）。半角カナ受入テスト1件追加 |
| 2026-09-03 | ユーザー実機検証（3回目）: 「判型が空欄です」「項目が空欄です」の警告について「デジタル仕事だと判型ないんですよ」と説明を受け、これらは警告自体を出さない方針に変更（従来は非ブロッキングのwarningとして表示していたが、恒常的に空欄になる項目のため警告する意味がないと判断）。`OPTIONAL_TEXT_FIELDS`から`item_name`/`format_size`を除外し、`SILENT_OPTIONAL_TEXT_FIELDS`として警告なしで空欄許容する定数に分離。既存テストのアサーションを更新（警告が出ないことを検証）。SalesAnalysis配下テスト計83件全成功、config/cache clear実施（Vue変更なしのためnpm buildは不要） |
| 2026-09-03 | ユーザー実機検証（4回目）: (1)「対象部署が一致しません（ファイル記載: ｵﾝﾃﾞﾏﾝﾄﾞ / 選択: オンデマンド）」（2)企画は分類の警告＋受注No 4004075のN列エラーのみに絞り込めたが確定に進めない（3）制作・オンデマンドはプレビュー自体が失敗する、を報告。(1)は帳票ソフト出力が半角カナのため `mb_convert_kana($label, 'KV')` でタイトル抽出時に全角へ正規化して解消（半角カナ受入テスト1件追加）。(3)のログを確認したところ `PDOException: SQLSTATE[22001] String data, right truncated: 1406 Data too long for column 'value'`（`ImportController::preview()`のCache::put経由）を発見。原因は既定のDBキャッシュ（`0001_01_01_000001_create_cache_table.php`の`value`列がTEXT=65,535byte上限）で、暗号化済みプレビューJSONが行数の多い部署（制作・オンデマンド）で上限を超えていた（企画は行数が少なく偶然収まっていた）。`SalesImportService::previewCacheStore()`を新設し`Cache::store('file')`（サイズ制約の緩いfileストア）を明示指定するよう`ImportController::preview()`・`SalesImportService::confirm()`を変更。100,000文字（約300KB）のペイロードがfileストアなら保存できることを検証する回帰テストを追加。(2)については、UIの「エラーのある受注」チェックボックスで4004075を選び「除外して再検証」すれば確定できる設計どおりの挙動であり、追加のバグではないとユーザーへ説明予定 |
| 2026-09-03 | ユーザー実機検証（5回目）: 「分類も空白許可にしてください」を報告（360/846/946〜3223行目で「分類が空欄です」の警告が多数）。判型・項目と同様、分類も入力が無い運用が常態化していると判断し、`OPTIONAL_TEXT_FIELDS`から除外して`SILENT_OPTIONAL_TEXT_FIELDS`（警告なしで空欄許容）へ移動（`item_name`/`format_size`/`category`の3項目に）。得意先名・品名のみが警告付き空欄許容として残る。既存テストのアサーションを更新。SalesAnalysis配下テスト計84件全成功、config/cache clear実施（Vue変更なしのためnpm buildは不要） |
| 2026-09-03 | ユーザー実機検証（6回目）: 「制作、オンデ、企画ともにプレビューに失敗します。企画はチェックを入れて許可した後にプレビュー失敗になります。500エラーです。」を報告。laravel.logを確認したところ`file_put_contents(.../storage/framework/cache/data/67/65/...): Failed to open stream: No such file or directory`を発見。原因は自分自身の作業ミス: 前回`previewCacheStore()`をfileストア固定にした直後、`docker compose exec laravel`（既定rootユーザー）で`php artisan test`を実行したため、`SalesImportService::previewCacheStore()`がテスト中でも実際に`storage/framework/cache/data`へ書き込み、そのディレクトリの一部が`root:root`所有・755権限で作られてしまった。実サーバー（`php artisan serve`は`sail`ユーザーで稼働、2026-09-02の`storage/app/private`権限事故で判明済み）がハッシュ衝突するサブディレクトリに書き込めず500になっていた |
| 2026-09-03 | 恒久対策: `config/sales_analysis.php`を新規作成し`import_preview_cache_store`（既定`file`、env `SALES_ANALYSIS_PREVIEW_CACHE_STORE`で上書き可）を追加。`SalesImportService::previewCacheStore()`をこの設定経由に変更。`phpunit.xml`に`SALES_ANALYSIS_PREVIEW_CACHE_STORE=array`を追加し、**テスト実行時は誰が（root/sailどちらが）実行しても実ファイルへ一切書き込まない**構成に変更（既存の`CACHE_STORE=array`は既定ストアにしか効かず、明示的な`Cache::store('file')`呼び出しには無効だったため、今回の事故を防げなかった）。既存の汚染ディレクトリ（`storage/framework/cache/data`配下、および同時に発覚した`storage/framework/testing`配下も同様の汚染）を`chown -R sail:sail`＋`chmod 775(dir)/664(file)`で復旧し、`sail`ユーザーでの書き込み成功を実地確認 |
| 2026-09-03 | 今後の運用注意（重要）: `storage/`配下へ書き込みが発生するartisanコマンド（特に`php artisan test`）は、実サーバーと同じ`sail`ユーザーで実行すること（`docker compose exec --user sail laravel bash -lc "..."`）。`docker compose exec laravel`の既定ユーザー（root）で実行すると、今回と同種の所有権汚染が再発する。以後のテスト実行は`--user sail`に統一 |
| 2026-09-03 | ユーザー実機検証（7回目）: 「取り込みは成功。そのあとにすすまない。取り込み画面の他、タブとかで分析とかそういうページの入り口がない。また、リロードすると、データなしとなってしまい、実際に取り込み成功したのに？となってしまう。」を報告。原因は`DashboardController::index()`で`$departmentKey = SalesDepartments::ENABLED_KEYS[0]`（常に企画固定）を使い、`hasAnyData`・初期表示部署・年月をすべて企画の取込状況だけで判定していたバグ。制作・オンデマンドしか取込データが無い状態では、企画側は永遠に未取込のため`hasAnyData=false`となり、Dashboard.vueは部署セレクタも何も無い「まだ取込データがありません」の空表示のみを出し続け、他部署のデータへ辿り着く手段が画面上に存在しなかった |
| 2026-09-03 | 修正: `DashboardController::index()`を全有効部署（`SalesDepartments::ENABLED_KEYS`）を対象に`activated_at`降順（同時刻ならsales_year→sales_month降順でタイブレーク）で最新のアクティブ月を検索するよう変更。直近に取込・確定した部署が自動的に初期表示部署になるため、企画以外だけにデータがあっても正しく表示される。回帰テスト`test_dashboard_shows_data_when_only_a_non_first_department_has_imports`を追加 |
| 2026-09-03 | 追加でImport.vueのUXを改善（「取り込み画面の他に入口が無い」への対応）: ヘッダーに「売上分析ダッシュボードへ」「取込履歴」リンクを常設。取込確定後の成功バナーに「売上分析ダッシュボードで確認する」ボタンを追加し、確定した部署・版数も表示するようにした。SalesAnalysis配下テスト計85件全成功、npm run build成功、config/cache clear実施 |
| 2026-09-03 | ユーザー指示: 実機で取込成功を確認。「codexにレビューさせますので、レビュー用のファイルを作ってください」。`z_instructions/SALES_ANALYSIS_REVIEW2.md`を新規作成し、実機検証ラウンド（7件の不具合と対応）を時系列表・変更後コード抜粋・Codexへの依頼事項8点・関連ファイル一覧としてまとめた。1回目レビュー（EXCEL_VALIDATION_REVIEW.md）との関係も明記 |
| 2026-09-03 | ユーザーがCodexへ2回目レビューを依頼し、`SALES_ANALYSIS_REVIEW2.md`へ結果（High2件・Medium4件・Low2件、除外処理の二段階方式仕様、9〜13章の新画面設計案、ユーザー確認済み要望9件）が追記された。「ファイルを確認し、作業をすすめてください」との指示を受け対応着手 |
| 2026-09-03 | High-1（除外リクエストが検証をスキップする問題）・High-2（プレビューが作成者と結び付いていない問題）・Medium-1（初期表示の並び順）を修正。回帰テスト13件追加、SalesAnalysis配下計95件・プロジェクト全体209件成功。対応結果を`SALES_ANALYSIS_REVIEW2.md`14章へ記録 |
| 2026-09-03 | ユーザー指示: 「おおきなものから着手しましょう」（Phase 6B/6Cの新画面設計に着手する指示）。Codexレビュー2回目12章の指示（実装前にワイヤーフレーム・JSON例を文書化してレビューへ出す）に従い、`SALES_ANALYSIS_PLAN1.md`のPhase 6B/6C節へ詳細設計（ルーティング方針・状態定義・ワイヤーフレーム・JSON例・未確定事項3点）を追記。コードの実装はまだ行っていない（ユーザー確認待ち） |
| 2026-09-03 | ユーザーが設計を確認し3点とも推奨案で確定（ルーティング方針/全部署合計の得意先名合算/黄バッジは情報表示のみ）。実装着手 |
| 2026-09-03 | Phase 6B実装: `SalesQueryService`に`registrationStatusByDepartment()`（部署×年度×月の状態算出。`no_data`/`future`/`zero`/`has_sales`の4状態＋`needs_review`/`has_issue`バッジ）と`registrationStatusFiles()`（年度を構成するファイル一覧、`active_month_count`/`total_month_count`）を追加。`needs_review`は新規カラムを追加せず`sales_active_months.created_at !== updated_at`（再取込でupdated_atだけ進む）で判定する設計にした。`RegistrationStatusController`（index/data/files）を新規作成し、`dashboard`ルートをこちらへ差し替え |
| 2026-09-03 | Phase 6C（改名部分）: 旧`DashboardController`/`Dashboard.vue`を`AnnualAnalysisController`/`AnnualAnalysis.vue`へリネーム・移設し、新ルート`annual_analysis`（`/sales-analysis/annual`）で存続させた（内容・APIロジックは変更なし、ヘッダーに「データ登録状況へ」の戻るリンクを追加しただけ）。同月比較・左右比較・年次分析のKPI再設計（進行中年の同期間比較等）はまだ未着手 |
| 2026-09-03 | `ImportHistoryController`にも同じ`active_month_count`/`total_month_count`ロジックを適用し、`is_active`の単純booleanを「5/6有効」等の表示に強化（Codexレビュー2回目 8.1 Medium-2対応）。`RegistrationStatus.vue`（部署タブ・年度行・月グリッド・ファイル一覧展開）を新規作成 |
| 2026-09-03 | 回帰テスト: `SalesDashboardIndexTest.php`を新しい登録状況APIのテストへ全面書き換え（8件）。旧Dashboard.vueのテストは`SalesAnnualAnalysisIndexTest.php`へ複製・ルート名のみ更新（5件、内容は変更なし）。`SalesImportConfirmHttpTest`にactive_month_count/total_month_countのアサーションを追加。SalesAnalysis配下テスト計103件全成功、npm run build成功（RegistrationStatus/AnnualAnalysisともにmanifestへ出力確認済み）、config/cache clear実施 |
| 2026-09-03 | 命名の見直し: 直前でリネームした`AnnualAnalysisController`/`AnnualAnalysis.vue`は実態が「単月KPI（当月・前月比・前年同月比・5年推移）」であり、Codexが構想する年次分析（年間累計・進行中年の同期間比較）とは別物と判断。サブエージェントに委任し`MonthlyAnalysisController`/`MonthlyAnalysis.vue`（ルート名`monthly_analysis`）へ再改名。あわせて`department_key`/`year`/`month`のクエリパラメータで深いリンク（登録状況画面の月セルから直接遷移）できるよう`index()`を拡張 |
| 2026-09-03 | サブエージェント作業中の事故: テスト用DBの状態異常を調査する過程で`php artisan tinker`から`Schema::connection('sales')->dropAllTables()`を実行した際、tinkerが接続先を**ローカル開発**環境（`sunbwork_sales`）に向けていたため、意図せずローカル開発DBの売上系7テーブルを全削除してしまった。migrationを再実行しテーブル構造は復旧したが、ローカルで取込済みだった架空テストデータは全て消失（0件）。本番・ステージング・テスト用DBへの影響はなし。ユーザーに直接報告し「ローカルなので大きな問題はない」と了承済み。**教訓: tinker/artisanで`sales`接続を操作する際は、実行前に必ず接続先DB名（`config('database.connections.sales.database')`等）を確認すること** |
| 2026-09-03 | Phase 6C本体を新規実装: `SalesQueryService::annualSummary()`（年間KPI・月別テーブル・得意先/分類/項目ランキング。進行中の年は登録済み最終月までを前年同期間と比較し、前年12ヶ月合計は参考情報として分離）、`searchByProductNameForYear()`、`activeOrdersQuery()`を複数部署対応（`department_key='all'`で企画・制作・オンデマンドを合算、得意先ランキングは得意先名で横断合算）に変更。新規`AnnualAnalysisController`（index/summary/products）と`AnnualAnalysis.vue`をルート`annual_analysis`・`api.annual_summary`・`api.annual_products`で追加。`RegistrationStatus.vue`の年度行ボタンをこの新画面へ接続し、月セルもクリックで月次分析へ遷移できるようにした |
| 2026-09-03 | 回帰テスト: `SalesQueryServiceTest.php`に年次サマリー5件（満年比較・進行中年の部分比較・全部署合計・複数回取込フラグ・年間品名検索）、新規`SalesAnnualAnalysisTest.php`に9件（index空表示/深いリンク/アクセス制御、summary形状/全部署合計/不正部署/アクセス制御、products キーワード必須/検索結果）を追加。SalesAnalysis配下テスト計117件全成功、npm run build成功（AnnualAnalysis/MonthlyAnalysis/RegistrationStatusともにmanifest出力確認済み）、config/cache clear実施 |
| 2026-09-03 | **354行目の記録を訂正・追記**: ユーザーから「local hostでsuper adminのパスワードが通らない」と報告を受け調査したところ、354行目で報告した被害範囲（salesDB限定）は誤りで、**通常DB（`sunbwork`）も完全に空になっていた**（users/companies/departments/project_jobs/diaries/events/changelogs等すべて0件）ことが判明。サブエージェントの完了報告には通常DBへの影響が記載されておらず、自分でテーブル件数を確認して発見した。原因コマンドはサブエージェントの実行ログ生データ（JSONL全文）を直接読めない制約があり特定できていないが、状態（スキーマは最新・データ0件・migrationsテーブルが再生成済み）から`--database=sales`等のスコープ指定なしで通常DB全体に影響するコマンド（`migrate:fresh`等）を誤実行したと推定される |
| 2026-09-03 | Superadmin専用シーダー（`CreateSuperadminCompanySeeder`→`CreateSuperadminSeeder`→`CreateSuperadminTeamSeeder`）と`php artisan db:seed`（冪等設計のマスターデータ群）でログイン・マスターデータを復旧。実際の業務データ（project_jobs/diaries/events等）はサンプルデータ投入が既定offのためシーダーでは戻せないと判明 |
| 2026-09-03 | プロジェクト直下に`db_backup_1762855429.sql`（2025-11-11時点の通常DBフルダンプ、usersからdiaries/project_jobs/chatまで含む）を発見。ユーザー了承のうえ復元を試みたが、バックアップのスキーマが10ヶ月分古く、現在のマイグレーション（`is_ghost`カラム追加など256件）と不整合が発生（`Column not found: is_ghost`等）。ユーザー判断で**復元を中止**（「多分データ構成が合わない」）。その後、復元試行で生じた新旧スキーマ混在状態（`migrate:fresh`が`sales`接続の残存テーブルと衝突して失敗）を、`sunbwork_sales`側の空テーブルを一旦削除してから`php artisan migrate --force`を完走させる形で解消し、現在は**通常DB・sales DBともに最新スキーマ・空データ（マスターデータのみ復元済み）**の状態で正常動作を確認済み |
| 2026-09-03 | ユーザー指示「本番で実行しないように厳重注意」「後工程に残すこと」を受け、恒久的な再発防止策として`AGENTS.md`に新セクション「Destructive Database Operations — Never Delegate to Subagents」を追加（インシデントの経緯・恒久ルールを明記）。あわせてClaude Code側の永続メモリにも同内容を記録（`feedback_subagent_db_destructive_ops.md`） |
| 2026-09-03 | ユーザー指示「seederはやっておいてほしいのと、hookに今回の事故防止を書くとよいと聞きました」を受け、`.claude/hooks/block_destructive_db.py`（`dropAllTables`/`DROP TABLE`/`DROP DATABASE`/`TRUNCATE`/`--database=`未指定の`migrate:fresh`を検知して`permissionDecision: deny`でブロック）を作成し、`.claude/settings.local.json`のPreToolUseフックとして登録。実際にBashコマンドがブロックされることをセッション内で実地確認済み。フックはgit管理外（`.git/info/exclude`）のローカル専用設定であることをAGENTS.mdに明記 |
| 2026-09-03 | `git add`で個別ファイルを指定してステージング（`storage/`配下の権限ビットのみの無関係な差分は除外）し、売上分析Phase 6B/6C一式（データ登録状況・年次/月次分析画面、Codexレビュー2回目の指摘対応、実機検証の修正）をコミット・push（`9ff769827`） |
| 2026-09-04 | Claude Code | 新セッション再開。SALES_ANALYSIS1_PROMPT.md・AGENTS.md・PLAN1.md・MANAGER1.md・REVIEW2.md・CONSOLIDATED_01を読了し、「6.次にやること」1番目の同月比較画面に着手。既存コード（SalesQueryService/AnnualAnalysisController/AnnualAnalysis.vue/routes/SalesDepartments）を調査し、同じ設計パターン（activeOrdersQuery(array)による全部署合算、clientDisplayNameResolver()による統合ON/OFF、needs_review/has_issue判定、ロール別prefix複製）を踏襲する形でPLAN1.mdへ「6D. 同月比較 詳細設計」（ルーティング方針・ワイヤーフレーム・JSON例）を追記。コードの実装はまだ行っていない（ユーザー確認待ち）。分類・項目別は年次マトリクス化せず「直近年vs前年」の2期間比較に留める設計判断をした（要確認事項としてユーザーへ提示予定） |
| 2026-09-03 | ユーザー指示「引継ぎファイルを作ってください」を受け、`z_instructions/SALES_ANALYSIS1_PROMPT.md`（実装開始前の初期プロンプトのまま古くなっていた）を現在の状態に合わせて全面刷新。現状サマリー・必読ファイル・アーキテクチャ要点・次にやること（同月比較→左右比較→得意先分析→Phase7〜10）・新セッション向け再開プロンプトを収録 |
| 2026-09-04 | ユーザーからPhase 9着手の一部承認（さくらcron/dump調査、DBは触らず読み取り専用）を得たため、`crontab -l`・`mysqldump --version`・`df/du`・sales DB接続設定有無（値は非表示）・`schedule:list`の読み取り専用SSHコマンド案を提示。実行前にユーザーが方針転換: 「さくらのほうはまだやらないでいい。このシステムをSunBWorkにあげるか、他のLaravelを組み上げるか、まだ迷っている」との申告を受けSSH調査を中止。売上分析機能をSunBWork本体に残すか別Laravelアプリへ切り出すかの未決定事項をU-4として記録。この判断が付くまでPhase 9（さくらバックアップ調査含む）は着手しない |
| 2026-09-04 | ユーザー「残り作業はありますか」に対し、保留中（Phase9/10のさくら関連）と着手可能（10-1〜10-4等）を整理して提示。続けてユーザー「さくらにデプロイは保留。それ以外で、分析画面に追加する機能などの作業は」に対し、Excel出力の他画面拡張・年次分析の得意先統合トグル未実装・プレビューキャッシュ期限切れ監視（Medium-3未対応）の3候補を提示 |
| 2026-09-04 | ユーザー指示「Excel出力の他画面拡張は保留（経理側の希望フォーマットを確認してから）。年次分析の得意先統合トグル追加とプレビューキャッシュの期限切れ削除は対応してほしい。時間がかからなければ両方対応してからCodexレビュー依頼ファイルを作成してほしい」を受け対応着手 |
| 2026-09-04 | 年次分析画面に得意先統合トグルを追加。`SalesQueryService::annualSummary()`に`$consolidateClients`引数を追加し`periodClientRanking()`/`periodOrdersGroupedByClient()`にconsolidate対応を追加（既存の`clientDisplayNameResolver()`を再利用）。`AnnualAnalysisController::summary()`にconsolidate_clientsパラメータ追加。`SalesExportService::annualAnalysisWorkbook()`が実際にconsolidateClientsを`annualSummary()`へ渡すよう修正（従来は概要シート表示用にのみ使い、集計には反映されていなかった不整合を解消）。`AnnualAnalysis.vue`にOFF/ONトグルUI追加、Excel出力リンクのconsolidate_clientsも実際のトグル値に接続（従来ハードコード0だった）。回帰テスト4件追加（Service/HTTP/Export各層）、axiosのboolean文字列問題を踏まえ`? 1 : 0`変換を最初から適用 |
| 2026-09-04 | プレビューキャッシュの期限切れ削除・容量監視を実装（Codexレビュー2回目 8.1 Medium-3対応）。売上分析専用の独立キャッシュストア`sales_preview`をconfig/cache.phpへ新設（既存の汎用`file`ストアとは別ディレクトリにすることで、年齢ベースの一括削除を他機能に影響させず安全に行えるようにした）。`config/sales_analysis.php`の既定ストアを`file`から`sales_preview`へ変更、TTL設定値`preview_cache_ttl_minutes`を追加。新規Artisanコマンド`sales:prune-preview-cache`（TTLの2倍より古いファイルを削除、削除件数・残存容量をログ記録）を作成し毎時実行のスケジュールに登録。テスト実行時（arrayストア）は何もしないことをテストで保証。回帰テスト3件追加。SalesAnalysis配下テスト計203件・プロジェクト全体317件成功、npm run build成功、config/cacheクリア実施 |
| 2026-09-04 | Codexレビュー依頼ファイル`z_instructions/SALES_ANALYSIS_REVIEW3.md`を新規作成。同月比較・左右比較・得意先統合（Phase 7）・Excel出力（Phase 8）・axiosのboolean GETクエリ問題・N列マイナス許容変更・年次分析統合トグル・プレビューキャッシュprune、の9項目を新規/変更ファイル一覧・設計判断・Codexへの依頼事項とともにまとめた。git statusを確認し、今回のセッションの変更（同月比較〜今日の項目2/3まで）が全て未コミットで`codex exec review --uncommitted`の対象に収まることを確認済み。ユーザーへコミット・codexレビュー実行を委ねる（自分では未実行） |
| 2026-09-04 | ユーザーがCodexへ3回目レビューを実行し、`SALES_ANALYSIS_REVIEW3.md`に結果（11〜17章）が追記された。**結論: Phase 6C〜8の基本構成・boolean変換・N列マイナス許容・得意先統合方針はいずれも妥当**。ただしHigh 3件（得意先詳細が選択期間を無視してAPIへ渡していない／年次analysisの`months_registered`が欠落月を隠す／全部署合計で一部部署未登録でも完全登録に見える）と、Medium 9件・Low 1件の実装レベルの指摘、加えて月次・年次・同月比較・左右比較・得意先分析を「概況→変化の原因→証拠」の3段階に再設計する大規模なUI/API改修案（共通期間ナビゲーター、Top10/20+詳細ドロワー、負数の0基準線表示、寄与度分析等）が12〜17章として提案された。テスト（203件・691 assertions）はCodex側でも再実行し全成功を確認済みとの記載あり |
| 2026-09-04 | ユーザー指示「それを新しいclaudeにやらせます。あなたは現状の引継ぎファイルを作るのみでいい」を受け、REVIEW3.mdの指摘・改修案への対応（High 3件の修正、PLAN/MANAGERへの次Phase追記、ワイヤーフレーム設計、共通部品実装、Priority A〜C展開）は次セッションへ引き継ぐことを決定。自分ではこれらの実装に着手せず、`SALES_ANALYSIS1_PROMPT.md`の全面刷新のみを行う |
| 2026-09-04 | `SALES_ANALYSIS1_PROMPT.md`を全面刷新完了。現状サマリー（Phase0〜8完了・Phase9保留・Phase10未着手・Codexレビュー3回実施済み）、必読ファイル（REVIEW3.mdを★最重要として追加）、特に重要な保留事項（5.1アーキテクチャ未決定U-4／5.2 Excel出力他画面拡張保留）、次にやること（REVIEW3 17章の実装依頼順を要約）、tinker書き込み事故の教訓・axiosのboolean変換の教訓を含む作業前チェックリスト、新セッション向け再開プロンプトを収録。コードの実装（REVIEW3対応）は次セッションへ引き継ぎ、本セッションでは着手しない |
| 2026-09-04 | 新セッション開始。`SALES_ANALYSIS1_PROMPT.md`・`AGENTS.md`・`SALES_ANALYSIS_REVIEW3.md`と対象コード（`ClientAnalysisController`/`SalesQueryService`の`annualSummary`/`clientDetail`/`monthlyFiguresForYear`、`SalesExportService`、`ClientAnalysis.vue`/`AnnualAnalysis.vue`）を読了。REVIEW3 17章1番目（High3件修正）に着手する前に設計をユーザーへ提示し、唯一の判断点（欠落月・部分登録月の年間合計への含め方）を一問確認して確定（判断ログ参照）。PLAN1.mdへ「Phase 11: REVIEW3対応」を追記、本ファイルへPhase 11の進捗行・タスク詳細表を追加。次はコード実装（11-1〜11-4）に着手 |
| 2026-09-04 | Phase 11実装完了。11-1: `ClientAnalysisController::detail()`/`SalesQueryService::clientDetail()`に開始月・終了月を追加し、`clientRankingForPeriod()`と同じ月境界ロジック（境界年は該当月のみ、中間年は通期）で年別推移・受注一覧（200件、`(year*100+month)`範囲判定）を絞り込むよう変更。呼び出し元3箇所（`ClientAnalysis.vue`、`SalesClientGroupServiceTest`2件）を新シグネチャへ追従。11-2: `annualSummary()`に`registered_months`/`missing_months`/`last_registered_month`を追加し、`months_registered`の意味を「最終登録月」から「登録月数（件数）」へ変更。`SalesExportService::buildOrdersSheet()`は`last_registered_month`を参照するよう修正（既存の「該当明細シートの対象月上限」という動作は維持、意味変更のみ）。`AnnualAnalysis.vue`に欠落月の警告バナーを追加（確定方針どおり、期間合計はそのまま表示し警告のみ添える）。11-3: `monthlyFiguresForYear()`が月ごとに`coverage`（`registered_departments`/`expected_departments`/`is_complete`）を返すよう変更し、`annualSummary()`の`monthly[]`へ伝播。`AnnualAnalysis.vue`月別表に「一部登録」バッジを追加（対象部署の内訳をtitle属性で表示）。11-4: 回帰テスト3件追加（得意先詳細の境界年月絞り込み、年次の欠落月`registered_months`/`missing_months`検証、全部署合計の部署別`coverage.is_complete`検証）。SalesAnalysis配下テスト計206件・プロジェクト全体320件成功（27件skipは既存の設定ルート未登録によるもので無関係）、npm run build成功。次はREVIEW3 17章2番目以降（ワイヤーフレーム文書化・共通期間ナビゲーター等のPriority A着手）をユーザーへ確認のうえ進める |
| 2026-09-04 | REVIEW3 17章3〜5番目（ワイヤーフレーム→共通部品→月次分析を完成見本として改修）の設計をユーザーへ提示し、承認を得て着手（PLAN1.md「Phase 12」参照）。共通部品として`PeriodNavigator.vue`（期間移動・境界越え・未登録月案内・最新登録月ジャンプ）、`RankingPanel.vue`（Top10/20＋全件詳細ドロワー、検索300msデバウンス・並べ替え・サーバー側ページング）、`useSalesChart.js`（金額フォーマッタ等）を新設。バックエンドに`monthSeries()`private helper（`monthlyTrend()`と`recentMonthlyTrend()`が共用）、`sameMonthAcrossYears()`、`nearestRegisteredMonths()`／`latestRegisteredMonth()`、`monthlyClientPanel()`／`monthlyBreakdownPanel()`＋共通`paginateRankingRows()`を追加。`clients()`/`categories()`/`items()`の応答形状を`{rows,total_count,total_amount,page,limit}`へ統一（呼び出し元は月次分析のみのため影響なしと確認済み）。`trend`エンドポイントのパラメータを`years`→`months`（既定13）へ変更（破壊的、他に呼び出し元が無いことを確認済み）。`MonthlyAnalysis.vue`を13.2節の構成へ全面改修（KPI帯に同月3年平均差・sparkline追加、月の推移グラフを5年通し線→直近13ヶ月+3ヶ月移動平均へ変更、同月複数年比較を新設、得意先比較に当月/前月増減/前年同月増減の3モード切替を追加、分類/項目のみタブ化・得意先はD区画に統合してREVIEW3のD/E重複を解消、品名検索は「詳細を調べる」折りたたみへ移動）。得意先分析への深いリンク（クリックした得意先の自動選択）はClientAnalysisController側の対応が必要なため、得意先分析への展開ステップへ先送りと明記。回帰テスト12件追加（controller層7件・service層5件）。SalesAnalysis配下テスト計216件・プロジェクト全体330件成功、npm run build成功、Ziggy再生成・route/config/cacheクリア実施。次はユーザーの実機確認を経てから、年次・同月比較・得意先分析・左右比較への横展開（17章6番目）に進む |
| 2026-09-04 | ユーザー指示「細かい部分はあとで指摘します。現状OKとして、先に進んでください」を受け、月次分析の実機確認を待たずに横展開（17章6番目）へ着手。1画面目として年次分析（PLAN1.md「Phase 13」）を実装。横展開の対象を14章のPriority A分類に厳密に絞るスコープ判断を行った（判断ログ参照）。`PeriodNavigator.vue`に`granularity`（`month`/`year`）・`allowAllDepartments`propを追加し年次分析でも共用可能にした。`latestRegisteredYear()`（'all'対応）・`annualClientPanel()`／`annualBreakdownPanel()`（既存`periodClientRanking()`/`periodDetailBreakdown()`と`lastRegisteredMonthForYear()`ヘルパーを再利用し、月次と同じTop10/20+全件詳細ドロワー契約へ統一）を追加。`RankingPanel.vue`に`diffColumns`propを追加（modeトグルが無い画面でも常に差額列を表示できるようにする汎用化）。`AnnualAnalysis.vue`の独自フィルタUIをPeriodNavigatorへ、得意先/分類/項目の静的Top10表をRankingPanelへ置換。月別数値表を初期折りたたみ化。回帰テスト7件追加。SalesAnalysis配下テスト計223件・プロジェクト全体335件成功、npm run build成功、Ziggy再生成・route/config/cacheクリア実施。次は同月比較画面へ展開する |
| 2026-09-04 | 同月比較画面へ展開（PLAN1.md「Phase 14」）。同月比較は「年」を持たず1〜12月だけを巡回する画面のため、PeriodNavigatorに`granularity='month-cyclic'`（年表示なし、12⇄1月巡回時に年を変更しない）を追加。得意先マトリクス（年×得意先）・新規/離脱リスト・分類/項目の1・3・5年前比較は、RankingPanelが前提とする単純な`{label,amount}`ランキング形状とは異なる多次元データ構造であり、13.4節が示す将来のタブ/カード化（Priority B/C寄り）に近いと判断し、今回はスコープ外とした（Priority A「期間ナビゲーターと条件引継ぎ」の展開のみ実施。判断ログ参照）。`latestRegisteredMonthNumber()`（'all'対応、年を返さず月だけ返す）を追加。回帰テスト2件追加。プロジェクト全体337件成功、npm run build成功、Ziggy再生成・route/config/cacheクリア実施。次は得意先分析画面へ展開する（月次分析D区画からの深いリンク未実装分もここで解消する） |
| 2026-09-04 | 得意先分析画面へ展開（PLAN1.md「Phase 15」）。得意先ランキングの静的テーブルをRankingPanelへ置換。既存`clientRankingForPeriod()`の集計ロジックを`mergeClientAggregatesForRange()`ヘルパーへ共通化し、新設`clientAnalysisPanel()`がTop10/20+全件詳細ドロワー契約で返すようにした（既存`clientRankingForPeriod()`/`ranking()`エンドポイントは非破壊、既存テストとの互換性を維持）。Phase12（月次分析D区画）・Phase13（年次分析得意先別）で先送りしていた「クリックした得意先の自動選択」深いリンクを解消: `ClientAnalysisController::index()`が`department_key`/`client_name`クエリを受け取り`initialDepartmentKey`/`initialClientName`として渡し、`ClientAnalysis.vue`がマウント時に自動で該当得意先の推移を表示するようにした。年別推移（棒グラフ）・受注一覧（最大200件）は、月次専用指標（3ヶ月移動平均・同月3年平均）の年次転用に集計方式の再設計が要ることと、受注一覧のRankingPanel化がその契約に合わないことから、Phase13/14と同じ基準でスコープ外とした（判断ログ参照）。回帰テスト5件追加。プロジェクト全体341件成功、npm run build成功、Ziggy再生成・route/config/cacheクリア実施。これでREVIEW3 17章6番目（年次・同月比較・得意先分析）まで完了、残るは左右比較 |
| 2026-09-04 | 左右比較への横展開を検討し、対象外と判断（PLAN1.md「Phase 16」）。PeriodNavigator/RankingPanelはいずれも単一期間・単一金額列を前提に設計しており、左右比較はA/B2つの独立した期間を同時に扱い、比較表も`amount_a`/`amount_b`の2列を並べて見せることが目的のため、現行の共通部品をそのまま適用すると片方の金額列が失われる。13.6節が示す「横棒の比較チャートでAとBを同じ行で並べる」はデュアル期間・デュアル金額専用の新規コンポーネントが必要でPriority B/C相当と判断。得意先比較は既にTop N＋「その他」集計を備えており14章Priority Aの趣旨は既存実装でも一定満たしている。`SideBySideComparisonController`/`SideBySideComparison.vue`には変更を加えていない。これでREVIEW3 17章6番目（年次・同月比較・得意先分析・左右比較への横展開）が完了。次はユーザーの実機確認を経て、17章7番目（Priority B追加）または他の要望へ進む |
| 2026-09-04 | ユーザーが月次分析（ステップ1）の実機確認フィードバックを提示、対応着手（ステップ2以降はユーザーが並行確認中）。①`PeriodNavigator.vue`の「会社統合」ラベルが「3部署統合」と誤解されたため「得意先統合（表記ゆれの名寄せ）」に変更しhover説明を追加（全画面共通コンポーネントのため一括反映）。②当月売上カードのsparkline下に「直近6ヶ月」の期間キャプションを追加。③年度累計の暦年/年度(4月)切替を、KPIカード内の小さいトグルから、KPI帯の上にある行全体の目立つトグルへ移動（ただし実際に連動するのは年度累計カードのみで、他の指標は暦年/年度の概念を持たないため対象外である旨を回答で説明する）。④13ヶ月推移グラフに選択中の月を赤丸で強調表示し、キャプションで凡例を明示。⑤「◯月の複数年比較」グラフにクリック操作の説明キャプションを追加。⑥得意先分析画面: 深いリンクで到達した場合、得意先ランキング一覧を初期状態で折りたたみ、個別得意先の推移をページ先頭へ移動して強調表示。⑦得意先詳細のグラフを「この得意先」単独の棒から「この得意先 vs 部署合計」の2系列＋構成比tooltipへ拡張（`clientDetail()`に`company_amount`/`share_pct`を追加、年別表にも構成比列を追加）。回帰テスト1件追加。プロジェクト全体342件成功、npm run build成功。**保留（ユーザーに要判断）**: 「品名検索の使いどころが不明、上部メニューに検索モード（年代/全体/月で絞る）を用意した方が良いのでは」という指摘は複数画面（月次・年次）にまたがる設計変更のため未着手。「13ヶ月推移グラフは月次分析には不要かもしれない（年次と重複）」も明確な削除指示ではなく保留 |
| 2026-09-04 | 追加フィードバック2件に対応。①「13ヶ月推移グラフは不要」が明確な削除指示として確定したため、月次分析から完全に削除（`renderTrendChart()`・関連canvas・click drill-down含む）。sparkline用の`api.trend`呼び出しは`months`パラメータを13→6へ縮小（13ヶ月分の全データはもう使わないため）。②「sparklineでは今どの月か分からない、最後の月なら赤丸にすべき」を受け、sparklineの最終点（=選択中の月）に赤丸を描画するようSVGを拡張し、キャプションも「直近6ヶ月（赤丸=今月）」に更新。③（年次分析の画面写真とともに）「メニューが無く画面間移動しにくい、月別・データ一覧等を1箇所に集約したい」との指摘を受け、共通コンポーネント`SalesAnalysisNavigationTabs.vue`を新設（既存の`UserNavigationTabs.vue`等と同じ、モバイル=ドロップダウン/デスクトップ=タブ、AppLayoutの`#tabs`スロットを使うパターンに準拠）。データ登録状況・月次分析・年次分析・同月比較・左右比較・得意先分析・得意先統合設定・Excel取込・取込履歴の全9画面のヘッダーから個別のナビゲーションボタン（画面によって行き先がバラバラだった）を撤去し、この共通タブへ統一。ページ固有のアクション（年次分析の「Excel出力」のみ）は`#headerExtras`へ分離し、戻るボタン（L-02標準）は`#header`に維持。プロジェクト全体342件成功（バックエンド変更なし）、npm run build成功 |
| 2026-09-04 | ユーザーからナビゲーションタブのスタイル指摘（コンテンツ下に「くっついている」、メニューらしい枠が欲しい）を受け、`SalesAnalysisNavigationTabs.vue`の外側divへ`mb-4 rounded-lg border border-gray-200 bg-gray-50 p-2 shadow-sm`を追加（`UserNavigationTabs.vue`が持つ`mb-4`を新規コンポーネント作成時に落としていたのが主因）。あわせて、`#tabs`スロットをこのコンポーネントで上書きしたことで、従来デフォルト表示されていた役割別（SuperAdmin/Admin等）の全社共通タブが売上分析画面では出なくなる点をユーザーへ開示（上部ハンバーガーメニューからの他機能への移動は引き続き可能）。ユーザーからは追加指示待ち |
| 2026-09-04 | ユーザーが得意先分析のスクリーンショットを提示: タイトルと表の間に大きな空白があり、意図したグラフが表示されていないと判明（Phase15で「グラフが無い」と指摘された箇所の実際の原因）。調査の結果、`ClientAnalysis.vue`の`showDetail()`に非同期タイミングバグを発見: `detailResult.value = response.data`を設定した直後に`renderTrendChart()`を呼んでいたが、`detailLoading.value = false`は`finally`ブロックでその後に実行される。テンプレートは`v-if="detailLoading"`〜`v-else-if="detailResult"`という排他分岐のため、`renderTrendChart()`実行時点では`detailLoading`がまだtrueで`v-else-if`側（canvasを含む）がDOMに存在せず、`trendChartRef.value`がnullのまま関数が早期returnし、何も描画されていなかった（このバグはPhase 7の実装当初から存在していたと推測される。RankingPanel等の他のloading/data分岐は`v-if`が独立しているため同様の問題はないことを確認済み）。`detailLoading.value = false`を先に実行してから`nextTick()`・`renderTrendChart()`を呼ぶ順序へ修正。npm run build成功（バックエンド変更なし、PHPUnitでは検知できない純粋なフロントエンドの非同期タイミングバグだった） |
| 2026-09-04 | ユーザー指摘: 「増加=赤、減少=青」という配色が日本の慣習（赤字=マイナス）と逆になっている。売上分析全体で増加=青・減少=赤へ統一するよう修正。共通`pctClass()`（`useSalesChart.js`、MonthlyAnalysis/AnnualAnalysis/RankingPanelが共用）と、ローカルに同名関数を複製していた3画面（`ClientAnalysis.vue`/`SameMonthComparison.vue`/`SideBySideComparison.vue`）の計4箇所を修正。ClientGroups.vueの赤（削除ボタン）は数値の増減とは無関係の破壊的操作の慣習色のため対象外と判断し変更していない。npm run build成功（バックエンド変更なし） |
| 2026-09-04 | ステップ2/4/7の実機フィードバックに対応。**①（重要バグ）URLクエリでのリロード時に状態が初期化される問題**を調査し根本原因を特定: `MonthlyAnalysisController`/`AnnualAnalysisController`/`SameMonthComparisonController`の`index()`が`hasAnyData`を「選択中の厳密な年月/年/月に登録があるか」で判定していたため、Phase 12〜14で追加した「未登録期間への自由な移動」（期間ナビゲーターの矢印・最新登録月ジャンプ等）と組み合わさると、未登録期間のURLでリロードした瞬間にインターフェース全体が「まだ取込データがありません」という空表示に落ちてしまっていた（クエリパラメータ自体は正しくパースされていたが、`v-if="!hasAnyData"`が全体を隠すため無意味になっていた）。3コントローラーとも判定を「その部署に1件でも登録済みか」へ緩和し、未登録期間固有の案内は既存のPeriodNavigator/欠落月バナーに任せる設計へ統一。回帰テスト3件追加。**②年次分析の月クリックが反応しないバグ**: `AnnualAnalysis.vue`の月別推移グラフ（折れ線）はChart.js既定のヒット判定（点の真上のみ）だとクリックがほぼ反応しないため、`interaction: {mode:'index', intersect:false}`を追加し縦帯全体をクリック対象にした（月次分析の同月比較チャートは棒グラフのため既定でも反応していた）。あわせてクリック操作の説明キャプションを追加。**③「差額が何との差額か分からない」**: `RankingPanel.vue`に`diffLabel`/`rateLabel`propを追加し、年次分析の得意先別パネルへ「前年差」「前年比」を指定（月次分析はモード切替ボタン自体が比較対象を示すため据え置き）。**④「分類/項目タブがクリックできそうなのに反応しない」**: `RankingPanel.vue`に`clickable`prop（既定true）を追加し、遷移先が無い分類/項目パネル（月次・年次の計4箇所）へ`:clickable="false"`を指定してポインター/ホバー演出を消した。**⑤ソート選択が分かりにくい**: 既存の並べ替えセレクターに「並び替え:」ラベルを追加。**⑥URLでの状態保存にlocalStorageも使えると良い**という提案は、①の根本修正で症状の大部分が解消される見込みのため一旦保留し、再確認後に必要であれば追加対応する方針とした。プロジェクト全体345件成功、npm run build成功 |
| 2026-09-04 | 追加フィードバック3件に対応。**①得意先分析の受注一覧が長い**: `ClientAnalysis.vue`の受注一覧（最大200件）を初期20件表示にし、「全件を見る（N件）」トグルで展開できるようにした（クライアント側スライス、データ取得自体は既存どおり最大200件のまま）。**②ランキングの得意先クリックで上部の内容が変わったことに気づきにくい**: `showDetail()`実行時に個別得意先の推移エリアへ`scrollIntoView({behavior:'smooth'})`でスクロールするようにした。**③年次分析の「月別売上（○対○）」を3年/5年で重ね表示したい**: 新規`SalesQueryService::multiYearMonthlySeries()`（$endYearを終点にN年分の月別金額を返す、未登録月はnull）→ `AnnualAnalysisController::multiYearTrend()`→ `api/annual-multi-year-trend`を追加。`AnnualAnalysis.vue`に「対前年/過去3年/過去5年」トグルを追加し、選択年数分の折れ線を重ねて表示（最新年は太線・実線、過去年は細線・破線・色分け。Chart.js既定の凡例クリックで個別年の表示/非表示も可能）。あわせて、月クリックのヒット判定を`mode:'index'`から`mode:'nearest'`へ変更し、複数年の線が重なっていても`datasetIndex`から正しい年を判定して月次分析へ遷移するよう修正（従来の`mode:'index'`のままだと常に配列の先頭＝最古年へ遷移してしまう問題があったため、2年重ねの動作確認時に合わせて修正）。回帰テスト2件追加。プロジェクト全体347件成功、npm run build成功、Ziggy再生成・route/config/cacheクリア実施 |
| 2026-09-04 | 得意先分析の受注一覧に並べ替え（新しい順/金額順）を追加。取得済みの最大200件をクライアント側で並べ替えるだけのため、サーバーへの再取得は発生しない。得意先を選び直した際は「新しい順」にリセットする。npm run build成功（バックエンド変更なし） |
| 2026-09-04 | ユーザー報告: ランキングの得意先クリックでスクロールが上に移動しない。前回実装した`detailSectionRef.value?.scrollIntoView(...)`（要素refへのnextTick後呼び出し）は、DOM構造やVueのタイミングに依存する分バグの余地があった。原因の切り分けを待たず、より頑健な実装（`window.scrollTo({top:0, behavior:'smooth'})`をshowDetail()の先頭で即座に呼ぶ、要素ref不要）へ置き換えた。npm run build成功（バックエンド変更なし） |
| 2026-09-04 | ユーザーからの複数の指示に対応。**①アーキテクチャ決定**: 「売上分析機能はSunBWork本体に残す」と確定（U-4解決）。ただし「さくらへのデプロイはまだ待って」と同時に明示指示があったため、Phase 9（バックアップ・さくらデプロイ・本番SSH調査）は別途デプロイ指示が出るまで保留を継続。永続メモリ`project_sales_analysis_architecture_decision.md`を更新。**②役割別タブの復元**: 「全社共通タブ（SuperAdmin等）も売上分析画面に残す」との指示を受け、`SalesAnalysisNavigationTabs.vue`がroutePrefixから役割を判定し、SuperAdmin/Admin/ClerkNavigationTabsを`active="sales_analysis"`で自前描画するよう変更（AppLayout.vue自体は変更せず、`#tabs`スロット上書きによる副作用をこのコンポーネント内で解消）。**③年度累計の集計区分を全画面共通のスイッチへ**: 新規`useFiscalMode.js`（モジュールスコープの単一ref+localStorage永続化）を作成し、`SalesAnalysisNavigationTabs.vue`のタブ行右端（「取込履歴」の右）に暦年/年度(4月)スイッチを追加。`MonthlyAnalysis.vue`はページ内トグルを廃止しこの共有状態を参照するのみに変更。**④品名検索の配置変更**: 「下に置かず、部署などの白い枠に検索ボタンを作り押したら検索窓が出るように」との指示を受け、`PeriodNavigator.vue`に`#extra`スロットを追加（同じ白い枠内に画面固有の追加操作を置けるようにする汎用的な拡張ポイント）。`MonthlyAnalysis.vue`/`AnnualAnalysis.vue`双方で、ページ下部にあった品名検索セクション（Monthlyは「詳細を調べる」折りたたみ、Annualは常時表示）を廃止し、期間ナビゲーターの「🔍品名検索」ボタン→直下に開閉パネル、という形に統一。バックエンド変更なし。npm run build成功、SalesAnalysis配下233件成功 |
| 2026-09-04 | ユーザー指摘: 「年と期の分類、意味がないので削除（見えなくするのでもよい）、年次分析と期別分析を作ってください。期別は4月から3月で」。前回追加した暦年/年度スイッチ（`useFiscalMode.js`）を全面的に撤去し、代わりに会計年度（4月始まり）専用の新規画面「期別分析」を新設した（機能範囲はユーザーに一問確認し「年次分析とフル機能で対応」を選択）。PLAN1.md「Phase 17」に詳細設計を記録済み。要点: `SalesQueryService`に`fiscalYear*`系メソッド一式（既存の暦年ロジックには一切手を入れず完全新規、年またぎ集計は得意先分析Phase15で作った`mergeClientAggregatesForRange()`を再利用し分類/項目向けに`mergeDetailBreakdownForRange()`を新設）、新規`FiscalYearAnalysisController`（`AnnualAnalysisController`と対応する9アクション）、新規`FiscalYearAnalysis.vue`（月配列は4月始まり、`PeriodNavigator`に追加した`yearLabel`propで「年度」表示）、`SalesExportService::fiscalYearAnalysisWorkbook()`。ナビゲーションタブに「期別分析」を追加しスイッチは削除、`useFiscalMode.js`は削除。`MonthlyAnalysis.vue`の「年度累計」カードは期別の概念を持たせず「年間累計（1〜12月、暦年固定）」に簡素化。回帰テスト14件追加（controller層10件・service層4件）。プロジェクト全体361件成功、npm run build成功、Ziggy再生成・route/config/cacheクリア実施 |
| 2026-09-05 | さくら本番へPhase11〜17一式をデプロイ（`SALES_DB_*`用に新規DB`silverlamb759_sales`をユーザーが作成、migrate --force、DEPLOY_SAKURA.mdの6ステップでビルド・push・pull）。あわせてローカルの実データ（imports28/active_months240/orders38191/order_details159789/audit_logs30）をmysqldumpで本番へ移行、件数完全一致を確認。デプロイ後、SuperAdminがadmin/clerk向け売上分析URLを開くと常にsuperadminタブが表示される不具合が発覚し修正（`ResolvesSalesAnalysisRoutePrefix`が実際のユーザーロールではなく現在のルート名からprefixを判定するよう変更）。ヘッダーの重複「売上分析」アイコンも削除。回帰テスト1件追加、さくら本番へ再デプロイ済み |
| 2026-09-05 | ユーザーからの追加要望「分析画面で今実装しているもののほかにあると便利なもの」に対し、得意先分析と対称な「商品分析」を提案・合意。さらに事務・経理からの要望「前年比較で大きく差があったときに何がなくなったのか、追加になったのかを調べたい」を受け、新規`ProductAnalysisController`/`ProductAnalysis.vue`を追加。PLAN1.md「Phase 18」に詳細設計を記録済み。要点: `productRankingForPeriod()`/`productAnalysisPanel()`/`productDetail()`は`clientAnalysisPanel()`等と対称構造（consolidate系引数は無し）、`productDetail()`には得意先分析には無い「購入している得意先ランキング」を追加、`productYearOverYearComparison()`は常に「直近登録年対前年」で固定比較し新規/取扱終了商品・増減額上位を返す（前年未登録時はhas_comparison_pair=falseで空リスト）。ナビゲーションタブに「商品分析」を追加。回帰テスト15件追加（service層6件・controller層9件）。売上分析262件成功、npm run build成功、Ziggy再生成実施 |
| 2026-09-05 | ユーザー指示によりCodexへ売上分析機能を全体的にレビューさせた（`codex exec review --base 02e302fc9`、Phase1〜18の基盤導入前コミットとの差分、112ファイル・約20,600行）。指摘3件: **[P1]** 商品分析コントローラー・ページが未コミット（`ProductAnalysisController.php`等が未追跡=git管理外だったため、レビューの差分に含まれずroutes/web.phpだけが参照する形になり「実装が無い」と誤検知された。実ファイルは存在。次回レビュー前は`git add`で追跡させることをメモ）。**[P2・実バグ]** `annualClientPanel()`/`fiscalYearClientPanel()`が`$current`のキーだけを回していたため、前年（前期）のみに存在し今年（今期）は受注が無い＝離脱した得意先が一覧・diffパネルから丸ごと消えていた。前年/前期のキーも合流させ、離脱得意先を`amount=0, diff=マイナス`で含めるよう修正。**[P2・実バグ]** 同月比較`buildSameMonthClientComparison()`の増加額上位/減少額上位が符号で絞り込んでおらず、対象が全員減少（または全員増加）の期間では反対符号の行が混ざって見出しと矛盾していた。同じ設計だった自前の`productYearOverYearComparison()`（Phase18で新規実装）にも同種のバグがあったため、両方とも`diff>0`/`diff<0`でフィルタするよう修正。回帰テスト4件追加。売上分析275件成功、PHPのみの変更のためbuild不要 |

## 7. ブロッカー・未決事項

| ID | 内容 | 状態 | 解決 |
|---|---|---|---|
| U-1 | 暗号化backupの外部保存先 | 未決 | Phase 9直前に一問確認 |
| U-2 | xlsx最大容量とupload上限 | 解決 | 中規模(年2,000〜1万行)と確認。暫定10MB上限を採用 |
| U-3 | 年末backup保持年数 | 未決 | backup実装前に一問確認 |
| U-4 | 売上分析機能をSunBWorkに残すか、別Laravelアプリへ切り出すか | **解決（2026-09-04）** | ユーザー確定: **SunBWork本体に残す**（切り出さない）。2026-09-05にさくら本番へデプロイ・実データ移行済み（U-1「暗号化backupの外部保存先」とは別件、本番運用開始） |

## 8. 完了条件

- Phase 0〜10がすべて✅。
- Codex Review R1が完了し、重大・高リスク指摘が残っていない。
- 修正後tests/buildが成功。
- ChangelogSeederと統合文書が更新済み。
- 本番売上DBをClaude/Codexがレコード照会していない。
- ユーザーが機能と運用手順を確認済み。
- 本ファイルを含む3文書が`z_instructions/archived/`へ移動済み。
