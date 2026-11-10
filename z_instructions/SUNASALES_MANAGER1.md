# SUNASALES_MANAGER1.md — サンエー印刷 売上レポート（営業）進捗管理

作成: 2026-11-10 / 設計: `SUNASALES_PLAN1.md` / 新セッション用: `SUNASALES1_PROMPT.md`

## 1. 作業フロー（各段階共通）

1. 設計書の該当段階を読む → 不明点は**1つずつ**質問
2. 実装（SunA 配置ルール・`suna_sl_*`・`ScopedToCompany`・fail-closed）
3. テスト（分離・権限・AIガード・取込・集計）→ `npm run build`
4. ローカルでユーザーが**ダミーデータ**で確認（ブラウザ確認はユーザー。CLAUDE.md 作業ルール11）
5. 確認後に本番デプロイ（`DEPLOY_SAKURA.md`。隔離 worktree＋SSH は事前提示・確認。売上DBの migrate は `--force`、事前に `migrate:status` 確認）
6. 本番確認 → この表を「完了」に更新

**禁止**: 本番の売上レコードの照会／実データのコミット・AIへの提示／サン・ブレーンの既存コード・`sales_*` テーブルの変更／`.gitignore` 前の Excel の `git add`

## 2. 進捗一覧

### 段階1 基盤

| ID | タスク | 状態 | 証跡・メモ |
|---|---|---|---|
| 1-0 | `.gitignore` に `★*得意先別*.xlsx` を追加（得意先名入りExcelの誤コミット防止） | ✅ | 2026-11-10 `z_instructions/★*得意先別*.xlsx` と `z_instructions/*得意先別*.xlsx` を追加。`git check-ignore` で対象ファイルが無視されること、`git status` に出ないことを確認 |
| 1-1 | マイグレーション（売上DB: departments/imports/facts/import_changes/audit_logs、通常DB: report_permissions） | ✅ | マイグレーション6本（売上DB: `suna_sl_departments`/`imports`/`facts`/`import_changes`/`audit_logs`、通常DB: `suna_sl_report_permissions`）。ローカル適用済み（未適用が自分の6件だけであることを確認してから`migrate`）。`amount`は decimal(15,2)（円未満の端数を持てるようPLANの(15,0)から変更） |
| 1-2 | Model・`ScopedToCompany`（fail-closed）・`SunaCompanyContext`・部門マスタ初期化 | ✅ | `SunaCompanyContext`（fail-closed・対象はサンエー印刷のみ）、`ScopedToCompany`（global scope＋作成時の会社付与＋`insertScoped`）、Model 6本、`DepartmentInitializer`（部門マスタの初期化・Excel表記→部/課の対応付け。「営業第三部」=直轄） |
| 1-3 | 分離テスト（2社・会社未設定で例外・サン・ブレーンは403・既存`sales_*`不変） | ✅ | `CompanyIsolationTest` 9件（他社の行が見えない・更新/削除できない・会社未設定で例外・他社company_idの作成/挿入を拒否・全`suna_sl_*`にcompany_id・サン・ブレーン不可・既存`sales_*`テーブル不変） |
| 1-4 | AIガードテスト（AI関連コードが SunA Sales / `suna_sl_` / `sales`接続を参照したら失敗） | ✅ | `AiIsolationTest` 4件（AI関連コードが SunA Sales / `suna_sl_` / `sales`接続を参照したら失敗。売上レポートのコードが AI・外部送信を使ったら失敗） |
| 1-5 | 許可設定画面（Admin エリア。ポートフォリオ同形＋役職チップ。閲覧/取込スイッチ） | 🔄 | `ReportPermissionController`（Admin エリア `admin/suna-sales/permissions`。ポートフォリオ同形＋役職チップ・見る/取込の2スイッチ・既定OFF・取込は閲覧が前提・変更なしの行は触らない・監査ログは件数のみ）、`Pages/SunA/Sales/Permissions.vue`、管理者タブ「売上レポート利用設定」（general のみ）。`ReportPermissionTest` 8件。ブラウザ確認待ち |
| 1-6 | ミドルウェア `suna_sales_report`・共有プロップ・`AppLayout.vue` の「営業」ボタン（PC/スマホ） | 🔄 | ミドルウェア`suna_sales_report:view/import/manage`、`ReportAccess`、共有プロップ`auth.salesReportAccess`/`salesReportCanImport`（general のみ計算）、`AppLayout.vue` ロール行・スマホメニューに「営業」（経理の後・ユーザーの前。general ガード内のみ）。ブラウザ確認待ち（サン・ブレーン表示が変わらないことも確認） |
| 1-7 | `ReportShell.vue`（独立レイアウト）・ルート・トップ（データ登録状況） | 🔄 | `ReportShell.vue`（独立レイアウト）・`ReportNavigationTabs.vue`、ルート`suna/sales/report`、データ登録状況（`RegistrationStatusService`/`Controller`/`RegistrationStatus.vue`）。`RegistrationStatusTest` 6件。ブラウザ確認待ち |
| 1-8 | `ClientSalesV1Reader`（形式 v1 の読み取り・検証・警告） | ✅ | `ClientSalesV1Reader`（形式v1。A列から得意先名・「2024年度」→暦年・空欄月=未登録・合計/総計の検算は警告・重複/部門空/年不正/数値不正はエラー・▲/△/カンマ/円記号対応・別形式は例外）、`ClientSalesWorkbookWriter`（ダミー/テスト用の書き出し）。`ClientSalesV1ReaderTest` 12件 |
| 1-9 | 取込（アップロード→差分プレビュー→確定。行単位 upsert・入れ替えオプション・変更ログ・履歴） | 🔄 | `SunaSalesImportService`（preview→confirm。得意先×年の行単位 upsert・ファイルで空欄の月は削除・入れ替える年は明示オプション・担当部門変更は全月更新・変更ログ・監査ログは件数のみ・一時ファイルは確定/破棄/1時間で削除・トークンは人と会社に紐づき1回限り）、`ImportHistoryService`、`ImportController`、`Import.vue`/`ImportHistory.vue`、ルート。`ImportTest` 14件。ブラウザ確認待ち |
| 1-10 | ダミーデータ作成コマンド（ローカル専用。架空の得意先名） | ✅ | `php artisan suna-sales:demo-excel {path?} --clients=60 --variant=main/supplement/update`（架空の得意先・本番では実行不可）。`DemoExcelCommandTest` 4件（main→supplement→update の取込の流れも確認） |
| 1-11 | ChangelogSeeder・テスト全体・`npm run build`・ユーザー確認・本番デプロイ | ⬜ | |

### 段階2 中核の分析
| ID | タスク | 状態 |
|---|---|---|
| 2-1 | 年次分析（KPI・月別推移・部/課別・得意先ランキング・新規/終了/増減） | ⬜ |
| 2-2 | 同月比較 | ⬜ |
| 2-3 | 得意先分析（年別・月別推移・担当部門の履歴） | ⬜ |
| 2-4 | テスト・ユーザー確認・本番デプロイ | ⬜ |

### 段階3 拡張
| ID | タスク | 状態 |
|---|---|---|
| 3-1 | 部門比較 | ⬜ |
| 3-2 | 左右比較 | ⬜ |
| 3-3 | 月次分析（平均受注単価） | ⬜ |
| 3-4 | 得意先の名寄せ設定 | ⬜ |
| 3-5 | Excel出力 | ⬜ |
| 3-6 | 注目ポイント（大きく増減した得意先・新規・休眠） | ⬜ |
| 3-7 | テスト・ユーザー確認・本番デプロイ・CONSOLIDATED更新・3文書を archived/ へ | ⬜ |

## 3. 判断ログ

| 日付 | 内容 |
|---|---|
| 2026-11-10 | ユーザーから、サンエー印刷専用の「売上レポート」の要望（営業部からの依頼。Excel「★2023～2026.09_得意先別.xlsx」の視認性改善）。構想段階のため質疑応答で設計を固める方針 |
| 2026-11-10 | Excel構造を分析（1シート・313社×4年・月別の売上金額/受注件数・担当部門5種）。サン・ブレーンの売上分析のうち、年次/同月/左右比較/得意先分析が流用可能、商品・分類は不可と整理。売上DBの会社分離（本番は別DB `silverlamb759_sales`、会社IDで分離。サンエー印刷の売上は未投入）をSSHで**構造のみ**確認（レコードは読んでいない）。AI機能（`Bot`）は会話テーブルのみ参照で、売上データへの経路が無いことを確認 |
| 2026-11-10 | Q1: サンエー印刷は1月〜12月が1期 → 期別分析は不要（ユーザー確定） |
| 2026-11-10 | Q2: 閲覧は管理者が個別許可（部長クラスのみ）。許可設定は Admin エリアに新設しポートフォリオ「利用メンバー設定」と同形（部署・区分等で選別）。SuperAdmin の既存「売上分析 利用許可設定」は変更しない（ユーザー確定） |
| 2026-11-10 | Q3: データは既存の売上DBに `suna_sl_*`＋`company_id`＋global scope（案A。別DBは不採用。理由: 既存の取込・分析を流用しやすく、さくらのDB契約数・バックアップ運用が増えない。分離は自動テストで保証）（ユーザー確定） |
| 2026-11-10 | Q4/Q5: 営業部のExcelをそのまま取り込む。2023年より前は同じ形式のExcelで補完。一部の得意先だけの可能性が高い → 得意先×年の行単位 upsert（入れ替えは明示オプション）、取込前に差分確認（ユーザー確定。「わからない・一部だけの可能性が高い」との回答を受けて設計側で決定） |
| 2026-11-10 | Q6: 部（第一/第二/第三）＋課（第三部のみ 第一課/第二課/直轄）の2段。「営業第三部」とだけ書かれた得意先は第三部の直轄（ユーザー確定） |
| 2026-11-10 | Q7: 3段階の内容でよい・要望なし（ユーザー確定）。設計文書3点を作成（実装前の確認待ち） |
| 2026-11-10 | 気づき: Excelは得意先名が残っており git 未追跡・未 ignore → 誤コミット防止の `.gitignore` 追加を段階1の最初のタスク（1-0）に設定 |
| 2026-11-10 | ユーザーがサンプルExcelをgitに上げないよう指示 → `.gitignore` に追加（1-0完了）。U-1（取込できる人）は案のとおり `can_import` スイッチで確定。設計確認を受けて段階1の実装に着手 |
| 2026-11-10 | 段階1の実装（1-1〜1-10）を実施。設計からの変更: ①取込の「行」の意味を明確化=ファイルにある得意先×年の行は、その行の内容で更新し、ファイルで空欄になった月は削除（ファイルに無い得意先×年の行は残す）②`amount`を decimal(15,2) に ③ダミーExcel作成コマンドを追加（ブラウザ確認用）。開発中、Bashのフックが、文字の省略表示用のCSSクラス名（Tailwind）を含むコマンドを「DBを破壊しうるSQL」と誤検知してブロックした（実行はされず、DB操作ではない）。ファイル作成は専用の書き込みツールで行い、フックは変更していない |

## 4. ブロッカー・未決事項

| ID | 内容 | 状態 |
|---|---|---|
| U-1 | 取込できる人（閲覧許可とは別の `can_import` スイッチ。SuperAdmin は常に可） | ✅ 2026-11-10 ユーザー確定（案のとおり） |
| U-2 | 画面での年の表記（既定案: 「2024年」） | 既定案で進める |
| U-3 | 担当部門の履歴の見せ方（既定案: 年ごとの所属の時系列表） | 段階2で確認 |
| U-4 | 閲覧ログ（既定案: 取込・許可変更・Excel出力のみ記録） | 既定案で進める |

## 5. 完了条件

- 段階1〜3のタスクが完了し、各段階でユーザーが確認済み
- 分離・権限・AIガードのテストが成功。サン・ブレーンの売上分析・メニューの挙動が変わっていない
- `ChangelogSeeder` に修正ログ、関連 `CONSOLIDATED_*.md` を更新、本ファイルを含む3文書を `z_instructions/archived/` へ移動
- 本番の売上DBをClaude/Codexがレコード照会していない
