# SUNASALES_PLAN1.md — サンエー印刷 売上レポート（営業）設計書 v1

作成: 2026-11-10 / ステータス: **設計確認済み・段階1の実装中**（2026-11-10）
関連: `SUNASALES_MANAGER1.md`（進捗・判断ログ）/ `SUNASALES1_PROMPT.md`（新セッション用）/ `SALES_ANALYSIS_PLAN1.md`（サン・ブレーン売上分析。流用元）/ `COLAYOUT_PLAN1.md`（会社別ナビ）/ `CLAUDE.md`「SunA 配置ルール」

---

## 1. 目的

営業部（営業第一部・第二部・第三部）からの要請: 得意先別の売上Excelが見づらいので、見やすい分析画面にしてほしい。
サン・ブレーンの売上分析（`/clerk/sales-analysis`）と同じ作り（AppLayout を使わない左右いっぱいの独立レイアウト、期間ナビ、年次/同月/左右比較/得意先分析 等）を、
**サンエー印刷専用機能（SunA）**として作る。今回のExcelは部分的なデータで、将来はもっと広い情報（商品・分類・受注明細 等）が加わる可能性があるため、**拡張できる設計**にする。

## 2. 確定事項（2026-11-10 ユーザーとの質疑で確定。経緯は MANAGER の判断ログ）

| # | 項目 | 確定内容 |
|---|---|---|
| Q1 | 期 | サンエー印刷は **1月〜12月が1期**（暦年）。Excelの「年度」= 暦年。**期別分析は作らない**（年次分析が期の分析）。4月始まりの変換は不要 |
| Q2 | 閲覧権限 | **管理者が個別に許可した人だけ**（部長クラスのみ）。許可設定画面は **Admin エリアに新設**（ポートフォリオ「利用メンバー設定」と同じ形。部署・区分などで選別）。SuperAdmin の既存「売上分析 利用許可設定」は**変更しない**（サン・ブレーン用のまま） |
| Q3 | データ置き場 | **既存の売上DB（`sales` 接続）に、サンエー印刷専用テーブル `suna_sl_*`** を作る。全テーブルに `company_id` ＋ 会社で自動絞り込みする仕組み（global scope）。混在しないことを自動テストで保証 |
| Q4 | 取込の運用 | 営業部が持つExcelを**そのまま取り込む**（同じ形式）。将来、形式が変わり得るので、読み取り部分は**形式ごとに差し替え可能**にする |
| Q5 | 補完データ | 2023年より前のデータも**同じ形式**のExcelで用意され、補完として取り込む。**一部の得意先だけのファイルが混ざる可能性が高い** → 取込は **得意先×年の行単位で追加・更新**（ファイルに無い行は残す）。年ごと入れ替えは明示オプション（既定OFF）。取込前に差分を確認できる |
| Q6 | 部門 | **部（第一部/第二部/第三部）＋課（第三部のみ: 第一課/第二課/直轄）**の2段。「営業第三部」とだけ書かれた得意先は**第三部の直轄**。担当部門は**年度（行）ごと**に持つ（13社は年度で変わる） |
| Q7 | 画面の範囲 | 3段階（§9）。商品分析・分類別は、データが無いので作らない（将来拡張） |
| — | メニュー | サンエー印刷のロール行に **「営業」ボタンを、経理の後・ユーザーの前**に追加。中に「売上レポート」（まず分析のみ）。許可された人にだけ表示 |
| — | AI | **AIはこのデータを参照しない設計**（§10） |
| — | 実データ | 機能が整ってから、**ユーザー自身が本番に入れる**。開発中はダミーデータのみ。Claude/Codex は本番の売上レコードを照会しない |

## 3. 絶対原則

1. **サン・ブレーンの挙動を一切変えない。** 変更は `company_type = 'general'` のガード内、または `suna_sl_*` / `SunA` 配下の新規ファイルに限る。既存の売上分析コード・`sales_*` テーブルは書き換えない
2. **SunA 配置ルール**（CLAUDE.md）に従う: `app/{Http/Controllers,Models,Services,Http/Requests}/SunA/Sales/`、`resources/js/{Pages,Components}/SunA/Sales/`、ルートは `routes/suna.php`、テーブルは `suna_sl_` 接頭辞（`sl` = Sales）
3. **会社分離は fail-closed**: 会社が解決できないクエリは例外にする（絞り込み漏れ＝他社データ閲覧になるため）
4. **AI は参照しない**（§10）。**実データは扱わない**（§12）
5. 新規ページは `CONSOLIDATED_01_layout_and_ui.md` を確認（ただし売上レポートは売上分析と同じ独立レイアウトで、AppLayout は使わない）。権限付与画面は標準形（申請者管理/ポートフォリオと同形）

## 4. 入力データ分析（`z_instructions/★2023～2026.09_得意先別.xlsx`。中身は削除済みで構造のみ）

**重要: このファイルは得意先名が残っており、git に未追跡・未 ignore。誤コミット防止のため、実装着手の最初に `.gitignore` へ追加する（MANAGER 1-0）。**

| 項目 | 内容 |
|---|---|
| シート | 1枚（`Sheet2`）、889行×31列（A〜AE） |
| ヘッダー | 1行目: タイトル「2023年～2026年9月_得意先別」・単位（円）／2行目: 列見出し（フィルタ・得意先名・年度・1月〜12月・合計・担当部門）／3行目: 月ごとに「売上金額」「受注件数」 |
| 行の単位 | **得意先 × 年度（暦年）**。4行目〜886行目。得意先313社、年度行883行。4年そろい144社／3年44社／2年50社／1年75社（新規・終了が多い） |
| 列 | A=フィルタ用得意先名（**全行に入っている**）／B=得意先名（年度行を縦結合。先頭行のみ値）／C=「2024年度」形式の年（暦年）／D〜AA=1月〜12月×（売上金額, 受注件数）／AB〜AC=合計（売上金額, 受注件数）／AE=担当部門 |
| 総計 | 889行目 `総計`（取込対象外。検算に使う） |
| 担当部門 | 5種: 営業第一部／営業第二部／営業第三部（直轄）／営業第三部第一課／営業第三部第二課。年度行ごとに設定され、13社は年度で変わる |
| 未登録月 | 当年（2026）は9月まで。**空欄の月は「未登録」、0は0円**として区別 |
| 持っていない情報 | 商品・分類・受注明細・担当者・案件。**将来追加される可能性**（形式は不確定） |

### 読み取りルール（形式 v1「得意先別」）
- 得意先名は **A列**から取る（B列は結合で先頭行にしか値が無いため）。年は「(\d{4})年度」→ 暦年
- 金額・件数はどちらも空欄の月 = 未登録（行を作らない）。どちらかに値があれば登録済み（空の側は0）
- 総計行・空行・ヘッダー行は読み飛ばす。**合計列（AB/AC）と月合計の不一致、総計行との不一致は警告**
- 担当部門のラベルは `suna_sl_departments.source_label` で部・課に対応付け。**未登録のラベルは取込を止める**（部門設定に追加してから再取込）
- 同一ファイル内に同じ得意先×年が重複していたらエラー
- 得意先名は `ClientNameNormalizer`（全角半角・空白）で正規化したキーで同一性を判定（表記そのものは表示用に保持）

## 5. データ設計

### 5.1 売上DB（`sales` 接続）— 全テーブル `company_id` 必須・`ScopedToCompany`（global scope, fail-closed）

**`suna_sl_departments`**（部門マスタ。部・課の2段）
`id, company_id, key(部/課のキー), parent_key(null=部, 値=課の親部), label, source_label(Excelの表記。null可), is_direct(部直轄), sort_order, timestamps`／unique(company_id,key)
初期値（会社=サンエー印刷）: 第一部 / 第二部 / 第三部(第一課, 第二課, 直轄)。`source_label` = 「営業第一部」「営業第二部」「営業第三部」(直轄)「営業第三部第一課」「営業第三部第二課」

**`suna_sl_imports`**（取込1回＝ファイル1つ）
`id, company_id, format('client_sales_v1'), original_filename, file_sha256, status(completed/failed), imported_by(通常DB user_id), imported_at, year_min, year_max, row_count(得意先×年), changed_count, added_count, unchanged_count, replace_years(json), warnings(json), timestamps`／index(company_id, imported_at)

**`suna_sl_facts`**（売上の事実。1行=得意先×年×月。登録済みの月だけ行がある）
`id, company_id, client_key(正規化キー), client_name(表示名), sales_year(smallint,暦年), sales_month(tinyint), department_key(部), section_key(課。第三部の課/直轄、それ以外null), amount(decimal(15,2)。円未満の端数を持てるように), order_count(unsigned int), import_id(最後に書いた取込), timestamps`
unique(company_id, client_key, sales_year, sales_month)／index(company_id, sales_year, sales_month)／index(company_id, department_key, section_key)
**拡張**: 将来の次元（商品分類・担当者・案件 等）は、nullable な列の追加、または `suna_sl_facts` と同形の別 fact テーブル（`suna_sl_detail_*`）で足す。形式ごとの取込は reader の差し替えで対応

**`suna_sl_import_changes`**（取込ごとの変更ログ。差分の履歴表示用。変更のあった行だけ）
`id, company_id, import_id, client_key, client_name, sales_year, sales_month(null=担当部門の変更), kind(added/changed), field(amount/order_count/department), old_value, new_value`／index(company_id, import_id)

**`suna_sl_audit_logs`**（`company_id` つきの監査ログ。取込・閲覧許可の変更・Excel出力）
`id, company_id, user_id, action, target_type, target_id, context(json), created_at`

### 5.2 通常DB
**`suna_sl_report_permissions`**: `id, user_id(unique), enabled, can_import, granted_by, granted_at, timestamps`（`portfolio_permissions` と同形。`can_import` = 取込もできる）

### 5.3 会社分離の担保
- 全 Model に `ScopedToCompany` トレイト: 現在の会社（`ResolvesContextCompany` 相当をサービスが `SunaCompanyContext` に設定）が未設定なら**例外**。`withoutGlobalScopes()` はテスト・保守コマンド以外で使用禁止
- 会社は **サンエー印刷（company_type='general' かつ SuperAdmin 会社でない）**のみ許可。サン・ブレーン（`sunbrain`）の会社コンテキストでは 403
- 自動テスト: (a) 2社分のデータを入れ、クエリ・API・集計が他社の行を返さない (b) 会社未設定で Model を使うと例外 (c) サン・ブレーン所属ユーザーは全ルートで 403 (d) 既存の `sales_*` テーブルに触れない（スキーマ・行数不変）

## 6. 取込設計（サン・ブレーン売上取込・経費取込の流用）

流れ: **アップロード → 解析・検証 → 差分プレビュー → 確定**（SB売上取込の preview/confirm と同じ2段階。経費取込の版・差分の考え方を流用）

1. アップロード（.xlsx、サイズ上限。Shift-JIS等のCSVは対象外）。ファイルは private ディスクの一時領域に置き、**確定または破棄で必ず削除**（保存しない）
2. `ClientSalesV1Reader` が解析（§4）。エラー（未登録の部門ラベル・重複・形式不正）は確定不可、警告は確認して進める
3. **差分プレビュー**: 現在のデータと突き合わせ、**追加・更新・変更なし**の件数、金額/件数が変わる行（得意先・年・月の旧→新）、担当部門が変わる行を一覧
4. 確定: **得意先×年の行単位で upsert**。ファイルにある得意先×年の行は、その行の内容で更新（**ファイルで空欄になった月は削除**）。ファイルに無い得意先×年の行は残す。オプション「この年はファイルの内容で入れ替える」（既定OFF。ONの年は、その年のファイルに無い行を削除）。`suna_sl_imports` と `suna_sl_import_changes` を記録（トランザクション）
5. 同一内容（sha256）の再取込は警告（強制で続行可）
6. 取込履歴: 取込ごとの件数・ファイル名・取込者・日時、変更ログから「何が変わったか」を表示（経費取込の差分表示と同じ見せ方）
7. 取込できる人: 閲覧許可とは別の `can_import` スイッチ（許可設定画面に並べる）。SuperAdmin は常に可

## 7. 権限・メニュー設計

- **許可設定画面**（Admin エリア。SuperAdmin も開ける）: ポートフォリオ「利用メンバー設定」（`PortfolioPermissionController` / `Pages/Portfolio/Permissions.vue` / `MemberList`）と同形。
  対象=現在の会社（サンエー印刷）の全ロールのユーザー。部署・区分（雇用区分）・担当のチップに加え、**役職のチップ**（部長クラスを選びやすく）。「表示中をすべて ON/OFF」、スイッチ（閲覧 / 取込）を動かして「保存する」でまとめて保存。並びは 雇用区分 → 役職 → 氏名。変更のない行は触らない
- 既定は**全員OFF（Admin も OFF。自分に許可を付けられる）**。**SuperAdmin は常に利用可**（開発・確認用）
- **ロール行**（`AppLayout.vue` の一般会社ブロック）: 「経理」の後・「ユーザー」の前に「営業」ボタン。表示条件=`auth.salesReportAccess`（許可ON、または SuperAdmin）。スマホメニューにも追加。サン・ブレーンのマークアップは変えない
- 共有プロップ `auth.salesReportAccess` / `auth.salesReportCanImport`（`HandleInertiaRequests`、`general` のときだけ計算）
- ルート保護: ミドルウェア `suna_sales_report`（`company_type:general`＋許可ON。SuperAdmin は通過）。URL直打ちでも許可の無い人は403
- 「営業」ボタンの遷移先: 売上レポートのトップ（データ登録状況）

## 8. 画面・ルート設計

- URL: `/suna/sales/report/...`、ルート名 `suna.sales.report.*`（営業は専用エリア。clerk 接頭辞は使わない）
- レイアウト: 売上分析と同じ独立レイアウト `Components/SunA/Sales/ReportShell.vue`（AppLayout を使わない／`SuperAdminContextSwitcher` 付き／ナビタブ／「通常メニューへ」）。サン・ブレーンの `SalesAnalysisShell` は変更しない
- ナビタブ（段階ごとに追加）: 概要・データ登録状況・年次分析・同月比較・得意先分析・部門比較・左右比較・月次分析・設定・取込（取込は `can_import` のみ）

## 9. 段階別の内容

**段階1 基盤**: 権限（許可設定画面・ミドルウェア・共有プロップ）／ロール行の「営業」／`suna_sl_*` テーブル・Model・`ScopedToCompany`・分離/AI ガードのテスト／部門マスタ初期化／`ClientSalesV1Reader`／取込（プレビュー・確定・行単位 upsert・入れ替えオプション・変更ログ・履歴）／データ登録状況（年×月の登録状況・最終実績月・部門別件数）／ダミーデータ作成コマンド（ローカル専用）
**段階2 中核の分析**: 年次分析（年のKPI・前年比、月別推移、部・課別、得意先ランキング、新規/終了/増減）／同月比較（同じ月を年どうしで。未登録月は除外）／得意先分析（得意先ごとの年別・月別推移、担当部門の履歴）。サン・ブレーンの `PeriodNavigator` / `RankingPanel` / `ShareCompositionBar` / `useSalesChart` 等を流用（コピーではなく、共通部品はそのまま import。変更が要る場合は SunA 側にラッパーを作り、元は変えない）
**段階3 拡張**: 部門比較（部・課・直轄の年推移と構成比）／左右比較／月次分析（平均受注単価=売上÷件数）／得意先の名寄せ設定／Excel出力／注目ポイント（大きく増減した得意先・新規・休眠）

各段階の最後に**ダミーデータで動作確認 → ユーザー確認 → 次へ**。本番デプロイは段階ごと（さくら手順は `DEPLOY_SAKURA.md`。売上DBのマイグレーションは `migrate --force`、サン・ブレーンに影響しないことを確認）。

## 10. AI が参照しない設計

現状: アプリ内AI（`Bot/ChatBot.vue`・`BotController`・`SummarizeConversationJob`）は `ai_*` の会話テーブルだけを読み、売上DB・業務テーブル・画面の内容は渡していない。ファイル解析（`useFileAnalyzer`）はPDFの寸法判定でAI非使用。
追加の防御:
1. **自動テスト（ガード）**: AI関連コード（`Bot`・`AiSetting`・`SummarizeConversationJob`・`AiHistoryConsolidator`・`config/ai_*`）が `App\Models\SunA\Sales` / `suna_sl_` / `connection('sales')` を参照したら失敗する
2. 売上レポートの画面・APIの応答を、AIの入力（会話・要約・プロンプト）に含める経路を作らない（ページ文脈をAIへ渡す仕組みは現状無し。今後も作らない）
3. 取込Excelは**保存しない**（確定/破棄で削除）。`storage/app/public` や添付（`AttachmentService`）の経路には置かない
4. 監査ログ・エラーログに、得意先名・金額などの内容を出さない（件数・IDのみ）
5. 開発では**ダミーデータのみ**。Claude/Codex が本番の売上レコードを照会しない（構造の確認のみ可）。`★…得意先別.xlsx` は `.gitignore` に追加し、AIに読ませない

## 11. 変更ファイル一覧（段階1。段階2・3は着手時に追記）

| 種別 | ファイル | 内容 |
|---|---|---|
| 新規 | `database/migrations/*_create_suna_sl_*`（売上DB 4本＋通常DB 1本） | §5 |
| 新規 | `app/Models/SunA/Sales/{Department,Import,Fact,ImportChange,AuditLog,ReportPermission}.php`、`Concerns/ScopedToCompany.php` | Model・分離トレイト |
| 新規 | `app/Services/SunA/Sales/{ClientSalesV1Reader,ImportService,ImportDiffService,RegistrationStatusService,DepartmentSeeder,SunaCompanyContext}.php` | 取込・差分・登録状況 |
| 新規 | `app/Http/Controllers/SunA/Sales/{ReportPermissionController,ImportController,RegistrationStatusController}.php` | 許可設定・取込・登録状況 |
| 新規 | `app/Http/Middleware/SunASalesReportAccess.php`、`bootstrap/app.php`（エイリアス） | ルート保護 |
| 変更 | `app/Http/Middleware/HandleInertiaRequests.php` | `auth.salesReportAccess` / `salesReportCanImport` |
| 変更 | `routes/suna.php`（＋Admin エリアの許可設定ルート）、`resources/js/ziggy.js` | ルート |
| 変更 | `resources/js/layouts/AppLayout.vue` | ロール行・スマホメニューに「営業」（general ガード内のみ） |
| 新規 | `resources/js/Pages/SunA/Sales/{Permissions,Import,ImportHistory,RegistrationStatus}.vue`、`Components/SunA/Sales/ReportShell.vue` | 画面 |
| 新規 | `tests/Feature/SunA/Sales/*`（分離・権限・取込・AIガード） | §5.3・§10 |
| 変更 | `.gitignore` | `★*得意先別*.xlsx` を追加 |
| 変更 | `database/seeders/ChangelogSeeder.php` | 段階ごとの修正ログ |

## 12. リスクと注意

- **Excelの誤コミット**（得意先名入り）→ `.gitignore` を最初に（MANAGER 1-0）
- **サン・ブレーンへの影響**: AppLayout の変更は general ガード内のみ。許可設定・共有プロップも general 限定。`sales_*` テーブルは触らない
- **会社分離の漏れ**: fail-closed＋テストで担保。Controller は `ResolvesContextCompany` で会社を解決
- **部門ラベルの増減**: 新しい部門名が出たら取込が止まる（設定画面で追加）。意図的な安全策
- **行単位 upsert は削除しない**: ファイルから消した得意先は残る。消したいときは「この年を入れ替える」または個別削除（段階3で検討）
- **形式の変化**: reader を形式ごとに作る。形式の判定はヘッダー行で行い、不明な形式は取込を止める
- **本番反映**: 売上DB（`silverlamb759_sales`）への migrate。事前に `migrate:status` で未適用を確認（構造確認のみ・レコードは読まない）

## 13. 未決事項（着手前に確認）

| ID | 内容 | 既定案 |
|---|---|---|
| U-1 | **取込できる人**（✅ 2026-11-10 確定） | 閲覧許可とは別の `can_import` スイッチ。SuperAdmin は常に可 |
| U-2 | 画面での年の表記 | 「2024年」（期=暦年）。Excel の「2024年度」は取込時に暦年へ |
| U-3 | 担当部門の履歴の見せ方（得意先分析） | 年ごとの所属を時系列表で表示 |
| U-4 | 売上レポート画面のユーザー名・IP等の閲覧ログ | 取込・許可変更・Excel出力のみ記録（閲覧は記録しない）。要望があれば追加 |
