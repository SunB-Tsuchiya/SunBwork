# SALES_ANALYSIS_PLAN1.md — 売上分析機能 設計仕様

## 1. 文書の目的

SunBWork 内に、印刷帳票ソフトから出力した Excel を取り込み、売上を比較・分析・Excel 出力する機能を追加する。

実装担当は Claude Code、実装後レビュー担当は Codex とする。本書は実装の基準となる設計図であり、判断に迷った場合は勝手に仕様を拡張せず、`SALES_ANALYSIS_MANAGER1.md` の判断ログへ記録してユーザーへ一問ずつ確認する。

この機能は機密性の高い売上データを扱う。売上データは通常の SunBWork DB と分離し、Claude/Codex は本番売上 DB のレコードを SSH、Tinker、SQL 等で閲覧しない。ローカル開発・テストでは架空データだけを使用する。

---

## 2. 合意済み要件

### 2.1 対象部署

- 帳票上の正式名称は `企画`、`制作`、`オンデマンド`。
- 「情報出版」「製版」という呼称は本機能では使用しない。
- 2026-09-03変更: 当初「初期実装の分析対象は企画のみ」としていたが、ユーザー要望により**企画・制作・オンデマンドの3部署すべてを初期版から取込・分析可能**にする。
- 3部署は別々の Excel ファイルとして出力される。
- DB と処理は元々部署キーを持たせる設計だったため、スキーマ変更なしで3部署に対応する。

### 2.2 入力ファイル

- 形式: `.xlsx`
- 年次ファイル名: `企画_2025年.xlsx`
- 月次ファイル名: `企画_2026年09月.xlsx`
- 範囲指定（半期等）ファイル名: `企画_2026年01-06月.xlsx`
- 2026-09-03 実データ確認により変更: Excel内部のタイトル行（1行目）は出力側（帳票ソフト）の設定により、年次・月次・範囲のいずれでも**常に開始月固定**（例: 半期でも年次でも「1月」としか表示されない）で信頼できない。そのため**アップロード画面での対象部署・対象年月の自動入力はファイル名から行う**（Excelタイトル行は使わない）。取込確定時の整合性チェックは、各行の`SB下版日`とフォーム入力の年月を照合する行レベル検証のみで行い、タイトル年月そのものとフォーム入力の一致は検証しない。タイトル行の部署ラベル（「(企画)」等）は年月とは別の情報のため、対象部署の照合には引き続き使用する。
- 年次ファイルは1シートに1月〜12月の明細が下版日順で入る。
- 年次・範囲指定ファイルは各行の `SB下版日` から売上月を決める。月次ファイルは行の`SB下版日`とフォーム入力年月が一致することを検証する。
- 列構成は年次・月次・範囲指定とも同じ15列。
- 現行サンプル: `z_instructions/sanbrain_meisai_sample.xlsx`。実装前に匿名化済みかをユーザーへ確認し、確認できない場合は構造確認だけに留め、値をテストfixtureへ転載しない。
- 元 Excel は経理側に原本が保存される。アプリ側では取込処理中だけ非公開一時領域へ保存し、成功・失敗にかかわらず処理後に削除する。

### 2.3 Excel 列

2026-09-03 Codexレビューにより更新（`SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md` 6.2 High-2）。実帳票では説明項目・明細内訳に欠損があり得るため、列ごとに必須度を分ける。

| 列 | 帳票見出し | DB上の意味 | 型・必須度 |
|---|---|---|---|
| A | 受注No | order_number | 文字列。全期間で一意。**必須（欠損・解析不能はブロッキングエラー）** |
| B | 得意先名 | client_name | 経理マスタ由来。完全一致を基本。**空欄可（警告付きNULL保存）** |
| C | 品名 | product_name | 検索・集計対象。**空欄可（警告付きNULL保存）** |
| D | 部品名 | part_name | nullable。空欄可・警告不要 |
| E | 分類 | category | 集計対象。**空欄可（警告付きNULL保存）** |
| F | 項目 | item_name | 集計対象。**空欄可（警告付きNULL保存）** |
| G | 進行 | progress | nullable。空欄可・警告不要 |
| H | 備考 | remarks | nullable。空欄可・警告不要 |
| I | 判型 | format_size | 文字列。**空欄可（警告付きNULL保存）** |
| J | 色数 | color_count | 0以上の整数を基本とするが原文も保持可能な設計。**空欄可（警告付きNULL保存）** |
| K | 台数 | quantity | 0以上の数値。**空欄可（警告付きNULL保存）** |
| L | 単価 | unit_price | 税抜、0以上。**空欄可（警告付きNULL保存）** |
| M | 金額 | line_amount | 明細金額、税抜、0以上。**空欄可（警告付きNULL保存）** |
| N | 受注金額 | order_amount_component | 同一受注の途中行は0またはNULL、まとまりの最後（1行だけ）に合計が入る（正・負いずれも可、2026-09-04変更）。**受注単位で必須**（同一受注内に0以外の値が1つも無い、または最後の行以外にある場合はブロッキングエラー） |
| O | SB下版日 | plate_date | `YYYY/MM/DD`、月またぎなし。実在日であることを検証（例: 2/31は拒否）。**必須（欠損・解析不能はブロッキングエラー）** |

**空欄を許容する列（B, C, E, F, I, J, K, L, M）は行を除外せず、NULLのまま保存し警告として提示する。数値の空欄を0へ勝手に変換して保存しない（NULLと0は意味が異なる）。**

### 2.4 売上計算ルール

2026-09-03 Codexレビューにより更新（同6.2 High-3 / Medium-1、6.3、ユーザー確認済み）。

- 月間売上は、受注ごとの `受注金額`（N列由来）の合計。
- 同一受注Noには複数明細があり得る。
- 同一受注内では途中行の受注金額は0またはNULL、最後の行に受注全体の金額が入る。**0以外の値（正または負）を持つN列は原則その受注内で1行だけ、かつ受注グループの最後の行であることを検証する**（複数行にある・最後の行でない・0以外の値が1つも無い、はいずれもブロッキングエラー）。
- DB上の受注金額 `order_amount` は、上記規則で確定した「最後の行の0以外の値」を採用する（単純な N 列合計ではない）。
- 同一受注Noの M 列合計と N 列受注金額が不一致でも、**N列を正式な売上として採用し、警告付きで取込を許可する**（ブロッキングエラーにしない）。差額は「未配賦額」（受注金額合計 − 明細内訳合計）としてプレビュー・ダッシュボード・Excel出力で常に確認できるようにし、隠さない。
- 売上金額は税抜。
- 2026-09-04変更（ユーザー確認）: 事故・刷り直し等により受注全体の合計（N列最終行）が正当にマイナスになるケースがあるため、**受注金額（N列）のマイナスは許容する**（従来「マイナス受注金額はない」としていたが撤回）。損益を正しく把握するため、登録されているデータはできる限り除外せず取り込む方針とする。途中行（最後の行以外）に0以外の値がある、または0以外の値を持つ行が複数ある場合は引き続きブロッキングエラー（構造規則違反）。
- 途中経過ファイルでも、掲載済み受注は受注金額まで確定している。
- 受注Noは全期間で一意で、月またぎしない。
- 一部の行・受注だけを黙ってスキップする部分取込は行わない。行レベルで確定不可能な重大エラー（受注No・SB下版日の欠損、N列規則違反等）が1件でもあれば、ファイル全体を確定不可にする。

### 2.5 再取込と版管理

- 同じ部署・同じ月は再アップロードされる可能性がある。
- アップロード単位を「取込版」として保存する。
- 新版の検証とDB保存がすべて成功してから、その版を該当月の最新版に切り替える。
- 分析は各月の最新版だけを使用する。
- 旧版のDBデータと取込履歴は残す。
- 年次ファイルと月次ファイルが混在できること。
  - 例: 2026年1〜8月を一括取込し、9月以降を月別取込。
  - `sales_active_months` が部署・年月ごとに現在有効な `sales_import_id` を指す。
  - 月別再取込はその月のポインタだけを切り替え、他月へ影響させない。
- 同じファイルハッシュの再アップロードは二重取込警告を出し、原則確定させない。

### 2.6 分析優先順位

1. 今月と前月
2. 前年同月との比較
3. 過去数年の月別推移（初期表示5年、将来10〜20年へ拡張可能）
4. 得意先別
5. 分類別
6. 項目別
7. 品名別集計・品名部分一致検索

初期画面は企画の最新取込月を自動表示する。得意先ランキングは上位10社を初期表示し、全件表示へ切替可能にする。

### 2.7 得意先統合

- 経理側の選択名称を原文のまま別得意先として扱う。
- 括弧内の区分が違う名称を勝手に統合しない。
- 通常は統合オフ。
- 「会社統合」オン時だけ、利用者が確定した会社統合グループで合算する。
- 初期版の候補生成は決定的な正規化（全角半角、空白、記号等）による候補提示までとし、自動確定しない。
- 将来 Local LLM を使う場合も候補提示だけに限定し、利用者の確定を必須とする。

### 2.8 Excel 出力

- 初期版から実装する。
- 画面で指定した期間・得意先・分類・項目・品名・会社統合オンオフ等の条件を反映する。
- 最低限、概要、月別推移、得意先別、分類別、項目別、該当明細を別シートで出力する。
- 運用後に帳票レイアウトを追加できる構造にする。
- PhpSpreadsheet を使用する。
- CSV/Excel formula injection 対策として、利用者由来の文字列を数式として書き込まない。

### 2.9 AI の位置付け

- 初期版にAI機能を入れない。
- 集計、比較、増減率、順位、グラフは SQL/PHP で決定的に計算する。
- 運用後、スクリプトで賄えない文章要約・観点提案等だけを Local LLM へ追加する。
- クラウドAIへの送信は初期スコープ外。

---

## 3. セキュリティ境界

### 3.1 DB分離

- 通常の `mysql` 接続とは別に、`sales` 接続を `config/database.php` へ追加する。
- 本番では売上専用DBと最小権限の専用DBユーザーを使用する。
- 売上Modelは明示的に `sales` 接続を使う。
- 売上DB migration は `Schema::connection('sales')` または migration の接続指定を使用し、通常DBへ誤作成しない。
- `users` へのクロスDB外部キーは作成しない。操作者IDは監査値として保持し、表示時だけ通常DBのUserを別クエリで解決する。
- 売上分析の個人別許可は非機密なので通常DBに置く。

想定環境変数（実値をコミットしない）:

```dotenv
SALES_DB_CONNECTION=mysql
SALES_DB_HOST=
SALES_DB_PORT=3306
SALES_DB_DATABASE=
SALES_DB_USERNAME=
SALES_DB_PASSWORD=
```

`.env.example` には空値または架空値だけを追加する。本番認証情報をログ、例外、画面、計画書へ出力しない。

### 3.2 AIエージェント向け禁止規則

実装時にルート `AGENTS.md` へ、少なくとも次の趣旨を追記する。`CLAUDE.md` はユーザーの明示依頼なしに編集しない。

```text
## Sales Data Confidentiality

- Production sales data uses the dedicated `sales` database connection.
- Never inspect production sales records through SSH, SQL, Artisan Tinker,
  database clients, dumps, logs, or ad-hoc scripts.
- Do not copy production sales data into the local environment.
- Use only synthetic fixtures and `z_instructions/sanbrain_meisai_sample.xlsx`
  for development and review.
- Schema/migration inspection is allowed from repository files. Do not query
  production schema if doing so risks returning record data.
- Never print sales DB credentials or backup contents.
```

### 3.3 権限

- 対象ロール: `SuperAdmin`、`Admin`、`Clerk`。
- `Leader` は対象外。許可候補一覧にも出さない。
- SuperAdmin は常時アクセス可とし、Admin/Clerkの個人許可を管理する。
- Admin/Clerkはロールだけではアクセス不可。SuperAdminが個人ごとに許可をオンにした場合のみ利用可能。
- 許可された利用者は閲覧、取込、会社統合設定、Excel出力をすべて利用可能。
- ナビ非表示だけで保護せず、専用middleware/policyで全ルートをサーバー側拒否する。
- 403 とし、存在や集計値を権限外利用者へ漏らさない。
- SuperAdminの許可設定ルートはSuperAdmin限定。

### 3.4 アップロード

- 許可拡張子 `.xlsx`、MIMEとZIPシグネチャを検証する。
- サイズ上限を設定する。実ファイル容量を確認後に値を決め、暫定10MBを超える場合はユーザー確認する。
- 複数シート、列不足、ヘッダー不一致、対象部署不一致、年月不一致を検知する。
- 外部リンク、マクロ、式の評価は行わない。重要列の数式セルは拒否または明示警告する。
- PhpSpreadsheetで計算式を実行せず、保存値または生値を安全に読む。
- 読取中の一時ファイルは `storage/app/private` 配下等の非公開領域へ置く。
- `finally` で一時ファイルを削除する。
- 元ファイル名はbasename化し、パスとして使用しない。
- 読み取った文字列は画面上でVueの通常テキストとして表示し、`v-html`を使わない。

### 3.5 ログと監査

- アプリログへ得意先名、品名、備考、金額明細、DB認証情報を出力しない。
- 監査ログには操作種別、user_id、対象部署・年月、import_id、日時、結果のみを保存する。
- 画面上で誰がいつアップロード・切替・統合設定変更・出力したか確認可能にする。
- Excel出力のダウンロードファイルはストリーム生成を基本とし、サーバーへ恒久保存しない。

---

## 4. DB設計

実際のカラム型・命名は既存Laravel規約を確認してmigrationで確定する。金額は浮動小数点を使わず `DECIMAL(15,2)` を基本とする。

### 4.1 通常DB: `sales_analysis_permissions`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| user_id | bigint unsigned unique | 対象User。通常DB FK可 |
| enabled | boolean | 許可オンオフ |
| granted_by | bigint unsigned nullable | 操作したSuperAdmin |
| granted_at | timestamp nullable | 許可日時 |
| created_at / updated_at | timestamps | 監査 |

対象候補はAdminとClerk。Leaderを含めない。

### 4.2 売上DB: `sales_imports`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| department_key | varchar(32) | 初期値 `planning`（表示名: 企画） |
| source_type | varchar(16) | `annual` / `monthly` / `range`（2026-09-03追加: 半期等の複数月まとめ取込） |
| source_year | smallint | ファイル対象年 |
| source_month | tinyint nullable | 月次・rangeで使用（rangeは開始月） |
| source_month_end | tinyint nullable | rangeのみ。終了月（2026-09-03追加） |
| version | unsigned int | 同部署・対象期間内の版 |
| original_filename | varchar(255) | basenameのみ |
| file_sha256 | char(64) | 二重取込検知 |
| status | varchar(20) | `validating` / `failed` / `completed` |
| imported_by | bigint unsigned | 通常DB user_id、FKなし |
| imported_at | timestamp | 取込日時 |
| order_count | unsigned int | 受注件数 |
| detail_count | unsigned int | 明細件数 |
| total_amount | decimal(15,2) | 対象ファイルの受注金額合計 |
| warnings | json nullable | 機密本文を避けた構造化警告 |
| created_at / updated_at | timestamps | 監査 |

推奨index:

- `(department_key, source_year, source_month, version)`
- `(file_sha256)`
- `(status, imported_at)`

### 4.3 売上DB: `sales_active_months`

部署・年月ごとに分析対象となる最新版を1件だけ指す。

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| department_key | varchar(32) | 部署キー |
| sales_year | smallint | 年 |
| sales_month | tinyint | 月 |
| sales_import_id | bigint | 現在有効な取込版 |
| activated_by | bigint unsigned | 通常DB user_id、FKなし |
| activated_at | timestamp | 切替日時 |
| created_at / updated_at | timestamps | 監査 |

制約:

- unique `(department_key, sales_year, sales_month)`
- FK `sales_import_id -> sales_imports.id`

### 4.4 売上DB: `sales_orders`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| sales_import_id | bigint | 取込版FK |
| order_number | varchar(64) | 受注No |
| client_name | varchar(255) | 得意先原文 |
| product_name | varchar(500) | 品名原文 |
| plate_date | date | SB下版日 |
| sales_year | smallint | 集計用 |
| sales_month | tinyint | 集計用 |
| order_amount | decimal(15,2) | 同一受注N列合計、税抜 |
| created_at / updated_at | timestamps | 監査 |

制約・index:

- unique `(sales_import_id, order_number)`
- index `(sales_year, sales_month)`
- index `(client_name)`。長さ制約がある場合はprefix/index戦略を検討。
- index `(plate_date)`

受注Noは業務上全期間一意だが、旧版を保持するためDBのuniqueは取込版との複合にする。現在有効な複数月間で同一受注Noが重複しないことをアプリ検証する。

### 4.5 売上DB: `sales_order_details`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| sales_order_id | bigint | 受注FK |
| source_row_number | unsigned int | Excel行番号 |
| client_name | varchar(255) | 行原文（監査・差分用） |
| product_name | varchar(500) | 行原文 |
| part_name | varchar(255) nullable | 部品名 |
| category | varchar(255) | 分類 |
| item_name | varchar(255) | 項目 |
| progress | varchar(255) nullable | 進行 |
| remarks | text nullable | 備考 |
| format_size | varchar(255) | 判型 |
| color_count | decimal(10,2) | 色数 |
| quantity | decimal(15,2) | 台数 |
| unit_price | decimal(15,2) | 単価 |
| line_amount | decimal(15,2) | M列金額 |
| order_amount_component | decimal(15,2) | N列原値 |
| plate_date | date | 行原値 |
| created_at / updated_at | timestamps | 監査 |

### 4.6 売上DB: `sales_client_groups`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| name | varchar(255) | 統合後表示名 |
| created_by / updated_by | bigint unsigned | 通常DB user_id、FKなし |
| created_at / updated_at | timestamps | 監査 |

### 4.7 売上DB: `sales_client_group_members`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| sales_client_group_id | bigint | グループFK |
| client_name | varchar(255) | Excel原文の完全一致名称 |
| normalized_name | varchar(255) | 候補検索用正規化値 |
| created_at / updated_at | timestamps | 監査 |

制約:

- unique `(client_name)`：一つの原文名称を複数グループへ所属させない。
- 正規化値だけで自動統合しない。

### 4.8 売上DB: `sales_audit_logs`

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | PK |
| user_id | bigint unsigned | 通常DB user_id、FKなし |
| action | varchar(64) | import/export/permission以外の売上操作等 |
| target_type | varchar(64) nullable | import/client_group等 |
| target_id | bigint nullable | 対象ID |
| context | json nullable | 部署・年月等。得意先名や明細本文を入れない |
| created_at | timestamp | 実行日時 |

権限変更は通常DB側のログまたは既存監査方式へ合わせる。

---

## 5. 取込処理設計

### 5.1 画面フロー

```text
[Excel選択]
    ↓
[アップロード・一時保存]
    ↓
[構造/部署/期間/各行/金額整合性を検証]
    ↓
[プレビュー]
  対象部署、期間、件数、受注数、合計、警告、既存最新版との差
    ↓ 利用者が確定
[sales DB transactionで版を保存]
    ↓
[該当月のactive pointerを切替]
    ↓
[一時ファイル削除・結果表示]
```

プレビューと確定の間で一時ファイルを長時間保持しない設計を優先する。推奨は、プレビュー時に検証済みデータを短寿命の暗号化キャッシュまたはセッション連携した一時JSONとして保持し、確定後即削除する方式。容量・Sakura制約を確認して実装する。クライアントから明細JSONを再送して正とする設計は改ざんリスクがあるため避ける。

### 5.2 検証

ファイル全体:

- 読取可能なxlsxか
- 想定シートが1つか（余分なシートがある場合は選択せず警告）
- タイトルに部署名があるか
- 見出し15列が正しいか。ふりがな付き見出しは正規化比較する
- 月次/年次をファイル名だけでなく内容から整合確認できるか
- 空ファイルでないか

各行:

- 合計行を除外
- 完全空行を除外
- 2026-09-03変更（Codexレビュー、6.2 High-1/High-2）: 行検証を **blocking error** と **warning** に分離する。
  - blocking error: 受注No欠損・解析不能、SB下版日欠損・解析不能または実在しない日付（例: 2/31）、同一受注内で得意先名・品名・下版日が矛盾、N列（受注金額）規則違反（後述）、負数（金額・単価を除く。後述）。
  - warning（行は除外せず、対象列はNULLのまま保存して取込は継続する）: 得意先名・品名・色数・台数が空欄、金額・単価が空欄または負数。数値の空欄を0へ変換して保存しない。
  - 項目・判型・分類の空欄は警告も出さない（2026-09-03実機検証・ユーザー確認: デジタル案件では判型・項目・分類が恒常的に空欄になるため）。
- 数値セルのカンマ、文字列数値、全角数字を安全に正規化
- 日付はExcelシリアル値と`YYYY/MM/DD`文字列の両方に対応し、JSTの日付として`Y-m-d`へ変換。`checkdate()`等で実在日を検証する
- `toISOString()`等のUTC変換を使わない
- 2026-09-03変更（実機検証・ユーザー確認）: 金額（M列）・単価は「事故損金」等の値引き・調整行で正当に負数になり得るため負数チェックの対象外とする。色数・台数は引き続き負数を拒否する。
- 2026-09-04変更（ユーザー確認）: 受注金額（N列）も、事故・刷り直し等で受注全体の合計が正当にマイナスになり得るため負数チェックの対象外とする（従来の「N列は負数拒否」から変更）。損益を正しく把握するため、登録データはできる限り除外せず取り込む方針
- 同一受注Noで得意先名、品名、下版日が矛盾する場合は確定前エラー
- N列（受注金額）規則: 同一受注No内で0以外の値（正または負）を持つ行が「ちょうど1行」かつ「その受注グループの最後の行」であることを検証する。0以外の値が0件・複数件・最後の行以外にある場合はblocking error。エラーメッセージには空欄行数・0円行数・正負の内訳等の詳細を含め、原因（空欄による孤立データか、位置違反か等）を判別できるようにする（2026-09-04ユーザー要望）
- 同一受注NoのM列合計とN列受注金額が不一致でも、N列を正式な売上として採用し、warningとして取込を許可する（差額は「未配賦額」として保持し、プレビュー・集計・Excel出力で提示する）
- 月次ファイルでは全行の下版年月がフォーム入力年月と一致すること。範囲指定ファイルでは全行の下版年がフォーム入力年と一致し、下版月が指定範囲内であること
- 年次ファイルでは下版年が対象年と一致すること
- 現在有効な他月に同じ受注Noがないこと
- 2026-09-03変更: Excelタイトル行の年月とフォーム入力の一致検証は行わない（出力側設定によりタイトル月は常に開始月固定で信頼できないと実データで判明）。タイトル行の部署ラベルとフォーム選択部署の一致検証は引き続き行う

**2026-09-03変更（実機検証・ユーザー確認）: 受注単位でのエラー除外**

実帳票にはExcel側の入力ミス（例: 受注金額欄が未入力で正値が1件も無い受注）が一定数含まれ得るが、
1件でもblocking errorがあるとファイル全体（数百件規模）が確定不可になっていた。以下の方式で緩和する。

- blocking errorのうち、**受注Noが読み取れる行に紐づくもの**（N列規則違反、受注内矛盾、他月重複、SB下版日不正 等）は「受注単位のエラー」として`invalid_orders`（受注No＋エラー内容の一覧）に集約する。ファイル全体を止める`errors`とは別枠。
- **受注No自体が読み取れない行**は、どの受注に属するか特定できず個別除外が安全にできないため、引き続きファイル全体を止める（`errors`）。
- 検証結果画面は`invalid_orders`を一覧表示し、ユーザーが受注単位でチェックして「除外して取込む」を選べる。除外を指定して再検証すると、その受注は**完全にスキップ**（DB未保存）され、残りの受注のみで確定できる。
- 除外した受注Noは警告（`warnings`）・取込履歴に記録し、黙って消えないようにする。
- ファイル選び直しを避けるため、フロント側で保持しているファイルを使って除外リスト付きで同じエンドポイントへ再送信する（サーバー側でファイル内容をキャッシュする必要はない）。

### 5.3 差分プレビュー

同じ月に最新版がある場合、少なくとも次を表示する。

- 旧版と新版の取込日時・担当者
- 受注件数、明細件数、合計金額と差
- 追加受注数、削除受注数、金額変更受注数
- 明細レベルの差分詳細は機密画面内で必要に応じて展開
- 確定後は新版を自動的に最新版へする

### 5.4 transaction境界

- 売上DBへの取込版、受注、明細、active month更新を同一 `sales` connection transaction内で実施する。
- 年次ファイルでは対象となる各月を一括で切り替える。途中月だけ切り替わる状態を作らない。
- 通常DBの権限テーブル更新とsales DB transactionを跨いだ分散transactionは作らない。
- 失敗時はactive pointerを変更しない。
- 2026-09-03変更（Codexレビュー6.2 High-4）: active monthの切替対象は「新版に実際に受注データが存在する月」ではなく、**取込指定範囲全体**とする（monthly: 指定月のみ／range: 開始月〜終了月／annual: 1〜12月）。これにより、修正版で受注が0件になった月についても新版へ正しく切り替わり、旧版のポインタが残留しない。

---

## 6. 集計設計

### 6.1 有効データの定義

すべての分析クエリは、`sales_active_months` と年月・import_idが一致する `sales_orders` / `sales_order_details` だけを対象にする。単に最新の`created_at`を取る実装は禁止する。

共通のQuery Service/Repositoryを作り、Controllerごとに有効版条件を重複実装しない。

### 6.2 指標

- 当月売上
- 前月売上、前月比増減額・増減率
- 前年同月売上、前年同月比増減額・増減率
- 年度累計、前年同期累計、差額・増減率
- 月別売上推移（初期5年）
- 得意先別売上・構成比・順位
- 得意先別前月差・前年同月差
- 分類別売上・構成比
- 項目別売上・構成比
- 品名別売上、受注一覧
- 受注件数、平均受注金額

分母が0または比較月未取込の場合、増減率を0と偽装せず`null`/「比較データなし」とする。

### 6.3 得意先統合オン/オフ

- オフ: `client_name`完全一致でgroup by。
- オン: `sales_client_group_members`に一致する名称はグループ名、未所属は原名でgroup by。
- 同じ会社らしいという推測だけで集計を変えない。

### 6.4 性能

- 初期5年、将来20年を想定する。
- 集計はサーバー側SQLで行い、全明細をVueへ渡してブラウザ集計しない。
- フィルタ対象列に必要なindexを付ける。
- 実データ件数を本番DBからAIが確認しない。ユーザーまたはアプリ画面の非機密な件数情報で性能調整する。
- 必要になるまで集計キャッシュ/集計テーブルを導入しない。導入時はactive version切替で確実に無効化する。

---

## 7. UI設計

`z_instructions/CONSOLIDATED_01_layout_and_ui.md`を厳守する。

### 7.1 共通レイアウト

- `AppLayout`を使用。
- ページ側で`main`、`py-12`、`max-w-7xl`を重複させない。
- `ToastUnified`を追加しない。
- Ziggy `route()`は名前付き引数をオブジェクトで渡す。
- 本番が`/members`配下なのでURLをハードコードしない。
- 金額は日本円形式、比較率は小数桁ルールを統一する。
- 表・グラフはスマートフォンでも横崩れしないよう、カード積層とテーブル横スクロールを使う。

### 7.2 ページ

#### A. 売上ダッシュボード

- 最新月を初期表示
- 対象年月、比較条件、得意先、分類、項目、品名検索、会社統合トグル
- KPIカード: 当月、前月、前月差、前年同月、前年同月差、年度累計
- Chart.js:
  - 5年月別推移の折れ線
  - 得意先上位10社の棒グラフ
  - 分類別/項目別の棒グラフまたは構成比（円グラフ乱用を避ける）
- ランキング表と全件表示
- Excel出力ボタン
- 取込画面、履歴、会社統合設定への導線

#### B. Excel取込

- 命名規則を表示
- ファイル選択
- 検証中表示
- プレビュー: 部署、対象月範囲、版、件数、合計、警告、差分
- 確定ボタンは検証成功時のみ有効
- 二重送信防止

#### C. 取込履歴

- 部署、対象期間、版、ファイル名、ハッシュ短縮表示、担当者、件数、合計、状態、日時
- 月ごとの現在有効版を明示
- 旧版内容の表示は許可ユーザーのみ
- 初期版では旧版への手動ロールバックを必須にしない。必要なら後続要件として追加

#### D. 得意先統合設定

- 原名称一覧、正規化候補、グループ名、所属状況
- 候補を確認して手動確定
- グループ作成・名称変更・メンバー追加/解除
- 統合による売上変化を保存前に確認
- 自動統合禁止

#### E. SuperAdmin 利用許可設定

- Admin/Clerk一覧
- 役割バッジ
- 個人ごとのチェックオン/オフ
- Leaderは一覧外
- 保存後トースト

### 7.3 ナビゲーション

- SuperAdmin、許可済みAdmin/Clerkだけに「売上分析」を表示。
- 既存AppLayoutのロール別ナビ構造を調査し、重複リンクを作らない。
- 非表示でもmiddlewareで必ず拒否する。

---

## 8. バックアップ設計

### 8.1 合意済み運用

- 売上専用DB全体を対象とする。
- Excel取込成功後に毎回バックアップ。
- 1日1回の定期バックアップ。
- 日次バックアップは30日保持。
- 年末バックアップは長期保存。
- 公開ディレクトリ外へ保存。

### 8.2 実装要件

- Artisan command例: `sales:backup`、`sales:backup-prune`。
- Sakuraで使用可能なdumpコマンド、cron間隔、容量を実装前に確認する。
- パスワードをコマンドライン引数やログへ出さない。
- dumpは暗号化する。暗号鍵をdumpと同じ場所へ置かない。
- ファイル名には日時・種別（日次/取込後/年末）を含め、得意先名等を含めない。
- 一時dumpも`finally`で削除する。
- バックアップ成功/失敗を監査し、失敗時は許可利用者または管理者へ画面通知できる構造を検討する。
- 復元手順を文書化し、架空DBで復元テストする。本番売上レコードをAIが復元確認しない。
- サーバー障害に備え、同一サーバー外への暗号化コピーは強く推奨。ただし保存先は未決定なので実装前にユーザーへ一問で確認する。

---

## 9. 想定コード構成

実装前に既存の命名・Controller粒度を確認し、必要に応じて調整する。

### 9.1 新規候補

```text
app/Http/Middleware/EnsureSalesAnalysisAccess.php
app/Http/Controllers/SalesAnalysis/DashboardController.php
app/Http/Controllers/SalesAnalysis/ImportController.php
app/Http/Controllers/SalesAnalysis/ExportController.php
app/Http/Controllers/SalesAnalysis/ClientGroupController.php
app/Http/Controllers/SuperAdmin/SalesAnalysisPermissionController.php
app/Http/Requests/SalesAnalysis/UploadSalesWorkbookRequest.php
app/Models/SalesAnalysisPermission.php
app/Models/Sales/SalesImport.php
app/Models/Sales/SalesActiveMonth.php
app/Models/Sales/SalesOrder.php
app/Models/Sales/SalesOrderDetail.php
app/Models/Sales/SalesClientGroup.php
app/Models/Sales/SalesClientGroupMember.php
app/Models/Sales/SalesAuditLog.php
app/Services/SalesAnalysis/SalesWorkbookReader.php
app/Services/SalesAnalysis/SalesImportValidator.php
app/Services/SalesAnalysis/SalesImportService.php
app/Services/SalesAnalysis/SalesQueryService.php
app/Services/SalesAnalysis/SalesExportService.php
app/Services/SalesAnalysis/ClientNameNormalizer.php
app/Console/Commands/SalesBackupCommand.php
app/Console/Commands/PruneSalesBackupsCommand.php
resources/js/Pages/SalesAnalysis/Dashboard.vue
resources/js/Pages/SalesAnalysis/Import.vue
resources/js/Pages/SalesAnalysis/ImportHistory.vue
resources/js/Pages/SalesAnalysis/ClientGroups.vue
resources/js/Pages/SuperAdmin/SalesAnalysisPermissions.vue
tests/Feature/SalesAnalysis/*
tests/Unit/SalesAnalysis/*
```

### 9.2 変更候補

```text
AGENTS.md
.env.example
config/database.php
bootstrap/app.php                         # middleware alias（現構造確認）
routes/web.php
routes/console.php                        # scheduler
app/Http/Middleware/HandleInertiaRequests.php
resources/js/layouts/AppLayout.vue
resources/js/ziggy.js                     # routes変更後に生成
database/seeders/ChangelogSeeder.php       # 完了時
z_instructions/CONSOLIDATED_09_domain_rules.md
```

### 9.3 migration

- 通常DB: `create_sales_analysis_permissions_table`
- 売上DB: 上記7テーブル
- migration名にsales専用であることを明記し、接続先をコード上で明示する。

---

## 10. フェーズ別実装

### Phase 0: 実装前確定

- 関連コード、AppLayout、権限、既存Chart.js、PhpSpreadsheet利用例を再確認
- Sakuraの別DB作成方法、dump、cron、保存容量をユーザー確認または公式資料で確認
- sales DBの本番作成・認証情報設定はユーザー承認が必要
- 外部バックアップ保存先を一問で確認
- サンプルが匿名化済みかを再確認。未確認なら値をfixtureへ転載せず、別の完全架空fixtureを作成

### Phase 1: DB接続・migration・機密ルール

- sales接続を追加
- 通常DBの個人権限テーブル作成
- sales DBのテーブル作成
- Modelとconnectionを実装
- AGENTS.mdへ本番売上DB閲覧禁止規則を追加
- 架空データ接続でmigration test

### Phase 2: 権限

- middleware/policy
- SuperAdmin許可設定Controller/UI
- Inertia shared propsとナビ表示
- Admin/Clerk許可オンオフ、Leader拒否のFeature test

### Phase 3: Excel読取・検証・プレビュー

- 読取Service
- 15列マッピング
- 月次/年次判定
- 受注グルーピングと金額整合性
- セキュア一時保存と削除
- サンプルfixtureによるUnit/Feature test

### Phase 4: 取込確定・版管理・履歴

- sales transaction
- orders/details保存
- active month切替
- 同一ハッシュ検知
- 同月新版差分
- 年次+月次混在、失敗時ロールバックtest

### Phase 5: 集計API/Controller

- 共通Query Service
- 当月/前月/前年同月/累計/5年推移
- 得意先/分類/項目/品名
- データなし、0分母、会社統合オンオフtest

### Phase 6: ダッシュボードUI

- 最新月初期表示
- フィルタ、KPI、Chart.js、ランキング
- レスポンシブ
- loading/error/empty state

### Phase 6B: データ登録状況画面（2026-09-03 Codexレビュー2回目により追加）

Codex 2回目レビュー（`SALES_ANALYSIS_REVIEW2.md` 9〜13章）を受け、売上分析のホーム画面を
「データ登録状況」に変更する。ファイルの区切り（年次/半期/月次）に関係なく、部署ごとに
月単位でどこまでデータが揃っているかを一覧できるようにする。

- 3部署（企画・制作・オンデマンド）を年度ごとに同時表示
- 各年度の1〜12月を「登録済み・売上あり」「登録済み・売上0円」「未登録」「複数取込あり(要確認)」
  「検証エラーあり」の5状態で色分け表示（0円と未登録を混同しない）
- 年度行を開くと、その年度を構成するファイル一覧（対象期間・有効月数・版・登録日時・担当）を表示
- 月セルから月次分析へ直接遷移
- 詳細は本ファイル9〜13章のワイヤーフレーム・JSON例を参照（実装前にレビュー予定）

### Phase 6C: 年次分析・同月比較・左右比較画面（2026-09-03 Codexレビュー2回目により追加）

- 年次分析: 部署・年を選択。年間KPI（年間売上/前年差/受注件数/1案件平均/未配賦額）＋1〜12月表とグラフ
  （選択年と前年の2本、5年分の長期比較は別画面へ分離）＋得意先・分類・項目・品名の年間上位
- 進行中の年（例: 2026年が8月まで登録済み）は前年の同期間（1〜8月）と比較し、前年12ヶ月合計とは比較しない
- 同月比較: 部署・対象月（例: 9月）を選び、直近5〜10年を横並びで比較。得意先の新規/離脱、増減額上位を表示
  （得意先統合ON/OFFと連動）
- 左右比較: 任意の期間A/Bを選び、差額・増減率を表示（年対年・月対月・同月前年）
- 「全部署合計」を部署選択肢に追加（3部署の値をそのまま合算。同一受注Noが複数部署にまたがっても
  部署ごとに1件として計上し、名寄せしない）
- 得意先分析画面（Phase 7と接続）: 得意先統合を反映したランキング・推移・新規/減少

### Phase 6B/6C 詳細設計（実装前レビュー用、2026-09-03作成）

Codexレビュー2回目12章の指示により、実装前にワイヤーフレームと返却JSON例をここへ記載する。
「データ登録状況」「年次分析」の2画面をまず設計・実装し、同月比較・左右比較・得意先分析は
その後に着手する。

#### 6B/6C-0. ルーティング方針（要確認）

現行の`sales_analysis.dashboard`（`/sales-analysis`）は「フィルタ+KPI+5年グラフ」画面
（`DashboardController` / `Dashboard.vue`）だが、これを次のように再編する。

| 変更後 | ルート名 | 画面 | 実装 |
|---|---|---|---|
| ホーム（URL・ルート名は変更しない） | `sales_analysis.dashboard` | データ登録状況（新規） | `RegistrationStatusController`（新規）+ `RegistrationStatus.vue`（新規） |
| 新規追加 | `sales_analysis.annual_analysis` | 年次分析（現行Dashboard.vueを踏襲・拡張） | 既存`DashboardController`を`AnnualAnalysisController`へリネームし、`summary`/`clients`/`categories`/`items`/`products`を流用。`trend`（5年推移）は同月比較画面へ移設予定のためAnnualAnalysisからは呼ばない |

- ナビゲーションタブ（SuperAdmin/Admin/ClerkNavigationTabs.vue）の`sales_analysis.dashboard`リンクは
  そのまま「データ登録状況」の入口になるため変更不要
- Import.vue／ImportHistory.vueの「ダッシュボードへ」リンクも変更不要（URLは同じ、中身が変わるだけ）
- 既存テスト（`SalesDashboardIndexTest.php`）は「データ登録状況」の内容に合わせて書き換えが必要になる
  （現行の`hasAnyData`/`initialYear`等のInertia propsは登録状況画面には不要になるため、新しいprops構成へ移行）

#### 6B-1. データ登録状況 ワイヤーフレーム

```
[企画] [制作] [オンデマンド]  ← 部署タブ切替（3部署同時表示だが縦に並べると長いため部署タブ＋
                                  「全部署」タブで切替可能にする。初期表示は最初の部署タブ）

年度   登録状況        1  2  3  4  5  6  7  8  9  10 11 12   期間売上      件数   ファイル数  最終登録
─────────────────────────────────────────────────────────────────────────────────
▼2026  8/12 登録済み   ■ ■ ■ ⚠ ■ □ ■ ■ ・ ・  ・  ・   ¥12,800,000  356件   3      09/03 山田
  └ 企画_2026年01-06月.xlsx   1〜6月  v1  5/6有効  09/01 山田   [ファイル一覧へ]
  └ 企画_2026年07月.xlsx      7月    v1  1/1有効  09/02 山田
  └ 企画_2026年08月.xlsx      8月    v1  1/1有効  09/03 山田
 2025  12/12 登録済み  ■ ■ ■ ■ ■ ■ ■ ■ ■ ■  ■  ■   ¥18,100,000  512件   4      03/10 佐藤
 2024  10/12 (2ヶ月欠落) ■ ■ □ ■ ■ ■ ■ ■ ■ ■  □  ■  ¥15,200,000  480件   6      02/20 佐藤
 ...

凡例: ■緑=登録済み(売上あり) ■青=登録済み(売上0円) □灰=未登録 ・=まだ来ていない月(将来月)
      ⚠黄バッジ=同じ月に複数回取込あり(現在の有効版を確認) 赤枠=明細と受注金額に差額あり(未配賦額)
```

- 月セルはクリックで月次分析へ遷移（月次分析はPhase 6C後半で実装。それまでは取込履歴の該当月フィルタへ暫定遷移）
- 年度行の「年次分析」ボタンでAnnualAnalysisへ遷移（department_key・yearをクエリで渡す）
- 「登録状況」欄は「X/12 登録済み」を基本形とし、Xが`total_due_month_count`未満かつ進行中の年でなければ
  「(Y ヶ月欠落)」を付記して欠落を強調する

#### 6B-2. データ登録状況 状態定義（新規スキーマ不要、既存テーブルの値から算出）

| 状態 | 判定 | 表示 |
|---|---|---|
| `no_data` | 該当`sales_active_months`行が無く、かつ過去月（`is_current_year`でない、または当月以前） | 灰 |
| `future` | 該当行が無く、当年が今年かつ対象月が来月以降 | 空白（グレーより薄い） |
| `zero` | 行あり、有効importのその月の受注合計が0円 | 薄青 |
| `has_sales` | 行あり、有効importのその月の受注合計が0円超 | 緑 |
| バッジ `needs_review` | `sales_active_months.created_at !== updated_at`（=この月のactive pointerが再取込で切り替わったことがある。**新規カラム不要**、Eloquentタイムスタンプの差分だけで判定できる） | 黄バッジ |
| バッジ `has_issue` | その月の有効受注に`unallocated_amount`の合計が0でないものがある | 赤バッジ |

年度単位の集計:
- `registered_month_count` = 状態が`no_data`でも`future`でもない月の数
- `total_due_month_count` = 過去年なら12、当年なら「今月まで」の月数（来月以降は`future`のため分母に含めない）
- `has_any_issue` / `has_any_needs_review` = 12ヶ月のいずれかがtrueかどうか（年度行のサマリ表示用）

#### 6B-3. データ登録状況 JSON例

`GET /sales-analysis/api/registration-status`

```json
{
  "as_of": "2026-09-03",
  "departments": [
    {
      "department_key": "planning",
      "department_label": "企画",
      "years": [
        {
          "year": 2026,
          "is_current_year": true,
          "registered_month_count": 8,
          "total_due_month_count": 8,
          "total_amount": 12800000.0,
          "order_count": 356,
          "file_count": 3,
          "latest_registration": { "at": "2026-09-03T10:00:00+09:00", "by": "山田太郎" },
          "has_any_issue": true,
          "has_any_needs_review": true,
          "months": [
            { "month": 1, "state": "has_sales", "amount": 1500000.0, "order_count": 42, "needs_review": false, "has_issue": false },
            { "month": 4, "state": "has_sales", "amount": 1450000.0, "order_count": 40, "needs_review": true,  "has_issue": false },
            { "month": 6, "state": "no_data",   "amount": null,      "order_count": null, "needs_review": false, "has_issue": false },
            { "month": 8, "state": "has_sales", "amount": 1600000.0, "order_count": 45, "needs_review": false, "has_issue": true, "issue_amount": -3500.0 },
            { "month": 9, "state": "future",    "amount": null,      "order_count": null, "needs_review": false, "has_issue": false }
          ]
        }
      ]
    }
  ]
}
```

`GET /sales-analysis/api/registration-status/files?department_key=planning&year=2026`（年度行を開いたときに取得）

```json
{
  "files": [
    {
      "sales_import_id": 11,
      "original_filename": "企画_2026年01-06月.xlsx",
      "source_type": "range",
      "period_label": "1〜6月",
      "version": 1,
      "active_month_count": 5,
      "total_month_count": 6,
      "is_fully_active": false,
      "imported_at": "2026-09-01T09:00:00+09:00",
      "imported_by": "山田太郎"
    },
    {
      "sales_import_id": 15,
      "original_filename": "企画_2026年07月.xlsx",
      "source_type": "monthly",
      "period_label": "7月",
      "version": 1,
      "active_month_count": 1,
      "total_month_count": 1,
      "is_fully_active": true,
      "imported_at": "2026-09-02T09:00:00+09:00",
      "imported_by": "山田太郎"
    }
  ]
}
```

（`active_month_count`/`total_month_count`は8.1 Medium-2「is_activeが1ヶ月でも有効ならtrueになる」の
解消も兼ねる。取込履歴画面`ImportHistory.vue`の`is_active`もこの形式へ合わせて更新する）

#### 6C-1. 年次分析 ワイヤーフレーム

```
部署: [企画 ▾]（全部署合計を含む4択）   年: [2026 ▾]         [← 2025年 | 2027年 →]

┌─年間売上──────┐┌─前年同期比────┐┌─受注件数──────┐┌─1案件平均─────┐
│ ¥12,800,000    ││ +¥850,000 +7.1%││ 356件          ││ ¥35,955        │
│ (1〜8月・進行中)││ 前年1〜8月比   ││                ││                │
└────────────────┘└────────────────┘└────────────────┘└────────────────┘
※未配賦額がある場合のみ: ⚠ 未配賦額 -¥3,500（3件の受注で明細合計と受注金額に差額）
※参考: 2025年通期(1〜12月)実績 ¥18,100,000（進行中の年とは分母が異なるため参考表示のみ）

月   売上          前年同月       差額         増減率    受注件数   1案件平均
──────────────────────────────────────────────────────────────
1月  ¥1,500,000    ¥1,400,000     +¥100,000    +7.1%     42件       ¥35,714
2月  ¥1,400,000⚠   ¥1,350,000     +¥50,000     +3.7%     39件       ¥35,897
...
8月  ¥1,600,000🔺   ¥1,550,000     +¥50,000     +3.2%     45件       ¥35,555
9月  ─（未登録・進行中の年のため今後登録予定）

[グラフ: 2026年 vs 2025年、月別売上の折れ線2本]

得意先 上位10社                 分類別                項目別               品名検索
┌──────────────┐        ┌──────────────┐    ┌──────────────┐   [___________] [検索]
│1位 A社 ¥2,300,000 (18%)│        │組版 ¥5,200,000│    │新規 ¥4,000,000│
│   前年 ¥2,100,000 +9.5%│        │...            │    │...            │
│...                      │        └──────────────┘    └──────────────┘
└──────────────┘
```

- 🔺は`needs_review`（複数取込あり）、⚠は`has_issue`（未配賦額あり）を月次表の行にも表示し、
  登録状況画面と表現を統一する
- 「全部署合計」選択時は3部署の`amount`/`order_count`を単純合算する。得意先ランキングは
  得意先名でグループ化して部署横断で合算する（要確認事項として13.2節に記載）

#### 6C-2. 年次分析 JSON例

`GET /sales-analysis/api/annual-summary?department_key=planning&year=2026`

```json
{
  "department_key": "planning",
  "year": 2026,
  "is_current_year": true,
  "months_registered": 8,
  "comparison_year": 2025,
  "comparison_mode": "partial",
  "comparison_month_range": [1, 8],
  "kpi": {
    "period_amount": 12800000.0,
    "prior_period_amount": 11950000.0,
    "amount_diff": 850000.0,
    "amount_rate": 7.1,
    "order_count": 356,
    "prior_order_count": 340,
    "avg_order_amount": 35955.0,
    "unallocated_amount": -3500.0,
    "full_prior_year_amount": 18100000.0
  },
  "monthly": [
    { "month": 1, "amount": 1500000.0, "prior_year_amount": 1400000.0, "diff": 100000.0, "rate": 7.1, "order_count": 42, "avg_order_amount": 35714.0, "state": "has_sales", "needs_review": false, "has_issue": false },
    { "month": 9, "amount": null, "prior_year_amount": 1480000.0, "diff": null, "rate": null, "order_count": null, "avg_order_amount": null, "state": "future", "needs_review": false, "has_issue": false }
  ],
  "top_clients": [
    { "client_name": "A社", "amount": 2300000.0, "share_pct": 18.0, "prior_year_amount": 2100000.0, "diff": 200000.0, "rate": 9.5 }
  ],
  "categories": [{ "category": "組版", "amount": 5200000.0, "share_pct": 40.6 }],
  "items": [{ "item_name": "新規", "amount": 4000000.0, "share_pct": 31.3 }]
}
```

- `comparison_mode`: 過去年（12ヶ月すべて登録済み）は`"full"`、進行中の年は`"partial"`。
  `partial`のとき`comparison_month_range`が前年比較の対象月範囲（[開始月, 終了月]）を明示する
- `full_prior_year_amount`はKPIの主比較には使わず、参考情報として画面に小さく表示するのみ

#### 6B/6C-3. 確定事項（2026-09-03ユーザー確認済み）

1. ルーティング方針（6B/6C-0）: **この方針で進める**。現行`Dashboard.vue`を「年次分析」へ転用し、
   ホーム（`dashboard`ルート）を新しい「データ登録状況」に差し替える。URL・ナビタブのリンクは変更不要
2. 「全部署合計」の得意先ランキング: **得意先名で部署横断合算する**（分類・項目ランキングも同様）。
   金額集計の方針（部署別にそのまま合算）と一貫させる
3. `needs_review`（複数取込あり）バッジ: **セルに小さなバッジを出すだけの情報表示に留める**。
   重大ではないため年度行を警告色にはしない

#### 6D-0. 同月比較 ルーティング方針

年次分析（`AnnualAnalysisController`）と同じ構造（index=Inertiaページ、api系=JSON）を踏襲する。
月次分析（`MonthlyAnalysisController`、単月KPI）と名称が紛らわしいため、コントローラ名・画面名を
明確に区別する。

| ルート名 | URL | 画面 | 実装 |
|---|---|---|---|
| `sales_analysis.same_month_comparison` | `/sales-analysis/same-month-comparison` | 同月比較（新規） | `SameMonthComparisonController`（新規）+ `SameMonthComparison.vue`（新規） |
| `sales_analysis.api.same_month_comparison` | `/sales-analysis/api/same-month-comparison` | データ取得API | 同上 |

- ロール別prefix複製は既存の`$registerSalesAnalysisRoutes`クロージャに追加するだけで対応可能（新規の仕組みは不要）
- `SalesQueryService`に`sameMonthComparison(string $departmentKey, int $month, array $years, bool $consolidateClients = false): array`を新設し、
  `activeOrdersQuery(array $departmentKeys)`・`resolveDepartmentKeys()`・`clientDisplayNameResolver()`・
  `needs_review`/`has_issue`判定ロジックを`annualSummary()`と共通利用する（判定ロジックの重複実装を避ける）
- ナビゲーション: データ登録状況・年次分析と同じ`#tabs`（SuperAdmin/Admin/ClerkNavigationTabs.vue）へ「同月比較」タブを追加

#### 6D-1. 同月比較 ワイヤーフレーム

```
部署: [企画 ▾]（全部署合計を含む4択）  対象月: [9月 ▾]  年数: [直近5年 ●] [直近10年 ○]  得意先統合: [OFF ●] [ON ○]

年     状態        売上           前年差         増減率     受注件数   1案件平均
──────────────────────────────────────────────────────────────
2022   登録済み    ¥1,350,000     ─             ─          38件       ¥35,526
2023   登録済み    ¥1,400,000     +¥50,000      +3.7%      40件       ¥35,000
2024   未登録      ─              ─             ─          ─          ─
2025   登録済み    ¥1,550,000     ─（前年未登録）─          44件       ¥35,227
2026   登録済み🔺  ¥1,600,000⚠   +¥50,000      +3.2%      45件       ¥35,555

[グラフ: 年別売上の棒グラフ（登録年のみ、未登録年は棒を表示せず「未登録」ラベル）]

得意先別 年次推移（上位15社＋その他）              新規得意先（前年同月になく今年ある）
┌────────────────────────────┐         ┌──────────────────┐
│      2022    2023   2024  2025   2026 │         │D社  ¥80,000        │
│A社  30万   32万    ─    34万   36万  │         └──────────────────┘
│B社  ...                                │
│その他 21万                             │         離脱得意先（前年同月にあり今年ない）
└────────────────────────────┘         ┌──────────────────┐
                                          │E社  前年¥60,000     │
増加額上位                                └──────────────────┘
┌──────────────────┐
│A社 +¥20,000 (+5.9%)│    減少額上位
└──────────────────┘    ┌──────────────────┐
                          │C社 -¥15,000 (-8.0%)│
                          └──────────────────┘

分類別・項目別 増減（基準年＝直近登録年に対し、1年前・3年前・5年前と比較。年次マトリクスは作らない）
┌─分類別─────────────────────────────────────────────┐
│組版  今年¥700,000 | 1年前¥650,000 +7.7% | 3年前¥600,000 +16.7% | 5年前 データなし │
└─────────────────────────────────────────────────────┘
┌─項目別─────────────────────────────────────────────┐
│新規  今年¥500,000 | 1年前¥480,000 +4.2% | 3年前¥420,000 +19.0% | 5年前¥400,000 +25.0%│
└─────────────────────────────────────────────────────┘
```

凡例: 🔺=`needs_review`（複数取込あり）、⚠=`has_issue`（未配賦額あり）— 年次分析と表現を統一する。
「─（前年未登録）」は比較対象年が未登録のため増減率を`null`（比較データなし）として扱うことを示す。

#### 6D-2. 同月比較 JSON例

`GET /sales-analysis/api/same-month-comparison?department_key=planning&month=9&years=5&consolidate_clients=false`

```json
{
  "department_key": "planning",
  "month": 9,
  "years_requested": 5,
  "years": [2022, 2023, 2024, 2025, 2026],
  "consolidate_clients": false,
  "yearly": [
    { "year": 2022, "state": "has_sales", "amount": 1350000.0, "order_count": 38, "avg_order_amount": 35526.0, "prior_year_diff": null, "prior_year_rate": null, "needs_review": false, "has_issue": false },
    { "year": 2023, "state": "has_sales", "amount": 1400000.0, "order_count": 40, "avg_order_amount": 35000.0, "prior_year_diff": 50000.0, "prior_year_rate": 3.7, "needs_review": false, "has_issue": false },
    { "year": 2024, "state": "no_data", "amount": null, "order_count": null, "avg_order_amount": null, "prior_year_diff": null, "prior_year_rate": null, "needs_review": false, "has_issue": false },
    { "year": 2025, "state": "has_sales", "amount": 1550000.0, "order_count": 44, "avg_order_amount": 35227.0, "prior_year_diff": null, "prior_year_rate": null, "needs_review": false, "has_issue": false },
    { "year": 2026, "state": "has_sales", "amount": 1600000.0, "order_count": 45, "avg_order_amount": 35555.0, "prior_year_diff": 50000.0, "prior_year_rate": 3.2, "needs_review": true, "has_issue": true, "issue_amount": -3500.0 }
  ],
  "client_matrix": {
    "years": [2022, 2023, 2024, 2025, 2026],
    "clients": [
      { "client_name": "A社", "amounts": { "2022": 300000.0, "2023": 320000.0, "2024": null, "2025": 340000.0, "2026": 360000.0 }, "latest_amount": 360000.0, "prior_year_amount": 340000.0, "diff": 20000.0, "rate": 5.9 }
    ],
    "others_amount": 210000.0
  },
  "new_clients": [
    { "client_name": "D社", "amount": 80000.0 }
  ],
  "departed_clients": [
    { "client_name": "E社", "prior_year_amount": 60000.0 }
  ],
  "top_increase": [
    { "client_name": "A社", "diff": 20000.0, "rate": 5.9, "current_amount": 360000.0, "prior_year_amount": 340000.0 }
  ],
  "top_decrease": [
    { "client_name": "C社", "diff": -15000.0, "rate": -8.0, "current_amount": 172500.0, "prior_year_amount": 187500.0 }
  ],
  "category_item_comparison": {
    "reference_year": 2026,
    "compare_offsets": [1, 3, 5],
    "categories": [
      {
        "label": "組版",
        "amount": 700000.0,
        "comparisons": [
          { "years_ago": 1, "compare_year": 2025, "amount": 650000.0, "diff": 50000.0, "rate": 7.7 },
          { "years_ago": 3, "compare_year": 2023, "amount": 600000.0, "diff": 100000.0, "rate": 16.7 },
          { "years_ago": 5, "compare_year": 2021, "amount": null, "diff": null, "rate": null }
        ]
      }
    ],
    "items": [
      {
        "label": "新規",
        "amount": 500000.0,
        "comparisons": [
          { "years_ago": 1, "compare_year": 2025, "amount": 480000.0, "diff": 20000.0, "rate": 4.2 },
          { "years_ago": 3, "compare_year": 2023, "amount": 420000.0, "diff": 80000.0, "rate": 19.0 },
          { "years_ago": 5, "compare_year": 2021, "amount": 400000.0, "diff": 100000.0, "rate": 25.0 }
        ]
      }
    ]
  }
}
```

- `years`は「登録済みの最新年」を終点に`years_requested`（5または10）年分を機械的に生成する。未登録年も`no_data`として配列に含め、登録状況画面と同じ「0円と未登録を混同しない」原則を維持する
- `client_matrix`・`new_clients`・`departed_clients`・`top_increase`・`top_decrease`はいずれも**得意先軸**の複数年比較。`new_clients`/`departed_clients`/`top_increase`/`top_decrease`は「`years`配列内の最新2年（直近の登録済み年とその前年）」のみを比較対象とする（3年以上のペアワイズ比較は行わない）
- `category_item_comparison`は年次マトリクスにせず、**基準年（`years`配列内の最新登録年）に対し1年前・3年前・5年前の3点だけ**を比較する（ユーザー確認: 「去年だけよかった時などあるので、できれば1・3・5年比較があるとよい」）。該当年が未登録の場合は`amount`/`diff`/`rate`を`null`にし、0円と誤表示しない
- `consolidate_clients=true`のとき、`client_matrix`/`new_clients`/`departed_clients`/`top_increase`/`top_decrease`はすべて`sales_client_group_members`の統合後名称で再集計する（年次分析の`clientRanking(consolidate: true)`と同じ`clientDisplayNameResolver()`を使う）
- `department_key='all'`のとき、`yearly`の金額・件数は3部署の単純合算、`client_matrix`等の得意先軸は得意先名で部署横断合算する（年次分析の「全部署合計」ルールと同一）

#### 6E-0. 左右比較 ルーティング方針

年次分析・同月比較と同じ構造（index=Inertiaページ、api系=JSON）を踏襲する。

| ルート名 | URL | 画面 | 実装 |
|---|---|---|---|
| `sales_analysis.side_by_side_comparison` | `/sales-analysis/side-by-side-comparison` | 左右比較（新規） | `SideBySideComparisonController`（新規）+ `SideBySideComparison.vue`（新規） |
| `sales_analysis.api.side_by_side_comparison` | `/sales-analysis/api/side-by-side-comparison` | データ取得API | 同上 |

- `SalesQueryService`に`sideBySideComparison(string $departmentKey, array $periodA, array $periodB, bool $consolidateClients = false): array`を新設する。
  `$periodA`/`$periodB`は`['type' => 'year', 'year' => int]`または`['type' => 'month', 'year' => int, 'month' => int]`の形。
- 「同月前年」は独立APIモードにせず、**月対月モードの入力補助（UI側で対象月・年を選ぶとA=年-1・B=年を自動セットするショートカット）**として実装する（API・Serviceは月対月と共通）
- 得意先/分類/項目の内訳取得は既存の`periodOrdersGroupedByClient()`/`periodDetailBreakdown()`（`department_keys, year, startMonth, endMonth`形式）をそのまま再利用する。year型は`startMonth=1, endMonth=そのperiodの登録済み最終月`、month型は`startMonth=endMonth=対象月`として渡す
- 得意先の新規判定・分類/項目の内訳集計など、既存メソッドをそのまま両側（A・B）に対して呼び出し、結果をラベルで突き合わせる

#### 6E-1. 左右比較 ワイヤーフレーム

```
部署: [企画 ▾]（全部署合計含む）        得意先統合: [OFF ●] [ON ○]

比較モード: [年対年 ●] [月対月 ○]
年対年:   A [2024年 ▾]   対   B [2025年 ▾]
月対月:   A [2025年 ▾][8月 ▾]   対   B [2025年 ▾][9月 ▾]   [同月前年にする]←Bと同じ月・前年をAへ自動セット

┌─A: 2024年────────────┐  ┌─B: 2025年────────────┐  ┌─差額(B−A)────────┐
│ ¥11,800,000  12/12ヶ月 │  │ ¥12,800,000  12/12ヶ月 │  │ +¥1,000,000  +8.5% │
│ 340件 / 平均¥34,705    │  │ 356件 / 平均¥35,955    │  │ 受注 +16件          │
└─────────────────────────┘  └─────────────────────────┘  └──────────────────────┘
※registered_month_countが両側で異なる場合（例: 進行中の年を選んだ場合）は画面上に
  「Aは12/12ヶ月、Bは8/12ヶ月分の合計です」と明記し、揃えずにそのまま両側の実績を出す

得意先別（上位15社＋その他、Bの金額降順）
得意先    Aの金額       Bの金額       差額          増減率
A社      ¥2,100,000    ¥2,300,000    +¥200,000     +9.5%
D社      ¥0            ¥80,000       +¥80,000      新規（前期実績なし）
E社      ¥60,000       ¥0            -¥60,000      消滅（今期実績なし）

分類別                              項目別
┌──────────────────────┐  ┌──────────────────────┐
│組版  A¥5,000,000 B¥5,200,000 +4.0%│  │新規  A¥3,800,000 B¥4,000,000 +5.3%│
└──────────────────────┘  └──────────────────────┘
```

凡例: 片方にしか実績が無い行も0円のまま残す（除外しない）。

#### 6E-2. 左右比較 JSON例

`GET /sales-analysis/api/side-by-side-comparison?department_key=planning&period_a[type]=year&period_a[year]=2024&period_b[type]=year&period_b[year]=2025&consolidate_clients=false`

```json
{
  "department_key": "planning",
  "consolidate_clients": false,
  "period_a": {
    "type": "year", "year": 2024, "month": null, "label": "2024年",
    "amount": 11800000.0, "order_count": 340, "avg_order_amount": 34705.0,
    "registered_month_count": 12, "total_month_count": 12,
    "unallocated_amount": -1200.0, "needs_review": false, "has_issue": true
  },
  "period_b": {
    "type": "year", "year": 2025, "month": null, "label": "2025年",
    "amount": 12800000.0, "order_count": 356, "avg_order_amount": 35955.0,
    "registered_month_count": 12, "total_month_count": 12,
    "unallocated_amount": -3500.0, "needs_review": true, "has_issue": true
  },
  "diff": {
    "amount": 1000000.0, "rate": 8.5,
    "order_count": 16, "avg_order_amount": 1250.0
  },
  "clients": {
    "rows": [
      { "client_name": "A社", "amount_a": 2100000.0, "amount_b": 2300000.0, "diff": 200000.0, "rate": 9.5 },
      { "client_name": "D社", "amount_a": 0.0, "amount_b": 80000.0, "diff": 80000.0, "rate": null },
      { "client_name": "E社", "amount_a": 60000.0, "amount_b": 0.0, "diff": -60000.0, "rate": -100.0 }
    ],
    "others_amount_a": 210000.0,
    "others_amount_b": 230000.0,
    "all_count": 42
  },
  "categories": [
    { "label": "組版", "amount_a": 5000000.0, "amount_b": 5200000.0, "diff": 200000.0, "rate": 4.0 }
  ],
  "items": [
    { "label": "新規", "amount_a": 3800000.0, "amount_b": 4000000.0, "diff": 200000.0, "rate": 5.3 }
  ]
}
```

- `period_a`/`period_b`は`type='year'`のとき`month`が`null`、`type='month'`のとき`year`+`month`両方を持つ。**その期間が一件も登録されていない場合は`amount`等をすべて`null`にする**（0円と誤表示しない。`registered_month_count`は0になる）
- `type='year'`の`amount`は「その年のうち登録済みの月だけ」を合算する（年次分析と同様、未登録月は無視して合算し0埋めしない）。**AとBの登録済み月数を揃える処理はしない**（例: 進行中の年とすでに12ヶ月確定した年を比較すると、双方とも「実際に登録されている実績の合計」がそのまま出る）。`registered_month_count`/`total_month_count`を両側に必ず表示し、比較対象期間の長さが異なる場合はユーザー自身が画面表示で判断する
- `diff.rate`は`period_a.amount`が0または未登録の場合`null`（比較データなし）。`clients.rows`内の`rate`も同様に、`amount_a=0`の行（新規得意先相当）は`null`、`amount_b=0`の行（前期のみ実績、今期消滅）は`-100.0`になる
- `clients.rows`は`amount_b`降順で上位15件のみを返し、残りは`others_amount_a`/`others_amount_b`（それぞれの合計）にまとめる。`categories`/`items`は既存の`categoryBreakdown`/`itemBreakdown`と同様に件数を絞らない
- `consolidate_clients=true`のとき`clients`は`clientDisplayNameResolver()`による統合後名称で集計する
- `department_key='all'`のとき、`period_a`/`period_b`の金額・件数は3部署の単純合算、`clients`は得意先名で部署横断合算する（既存の「全部署合計」ルールと同一）

---

### Phase 7: 得意先統合

- 正規化候補
- 手動グループCRUD
- 統合プレビュー
- 自動統合をしないことのtest

#### 7-0. 詳細設計（2026-09-04作成、実装前レビュー用）

Codexレビュー2回目11章の実装順（Phase7=得意先統合ON/OFFと統合管理、10.1節の「得意先分析」画面と接続）に
従い、次の2画面を同時に実装する。

| 画面 | ルート名 | 内容 |
|---|---|---|
| 得意先統合設定 | `sales_analysis.client_groups.index` | 原名称一覧・正規化候補・グループCRUD・統合プレビュー（SuperAdmin/許可Admin/Clerk共通、既存権限のまま） |
| 得意先分析 | `sales_analysis.client_analysis` | 得意先ランキング（統合反映）→個別得意先を選ぶと年別推移・受注一覧を表示 |

##### 7-1. 正規化アルゴリズム（`ClientNameNormalizer`、候補提示のみ・自動確定しない）

決定的な変換のみ行う。法人格表記（株式会社/(株)等）や括弧内の文言は**除去しない**
（PLAN 2.7「括弧内の区分が違う名称を勝手に統合しない」を候補生成の段階から守るため）。

1. 前後の空白をtrim
2. `mb_convert_kana($name, 'KVa')` — 半角カナ→全角（濁点結合込み）、全角英数字→半角
   （`SalesWorkbookReader::parseTitle()`の部署ラベル正規化と同じ関数・同じ考え方を踏襲）
3. 全角スペース・半角スペースをすべて除去（社名表記のスペース有無ゆれを吸収）
4. 全角括弧`（）`を半角`()`へ統一（中身の文言は変更しない）
5. 英数字部分のみ大文字へ統一（`mb_strtoupper`、日本語部分は影響なし）

この結果を`normalized_name`として保存する。**normalized_nameが一致する複数の原名称」を
「候補」として提示するだけで、確定はユーザーの手動操作のみ**（グループ作成・メンバー追加）。

##### 7-2. 得意先統合設定 ワイヤーフレーム

```
[企画] [制作] [オンデマンド] [全部署]  ← 部署タブ（候補生成は全部署の受注から原名称を集める。
                                          タブは「その部署で使われている名称に絞り込む」表示フィルタ）

■ 正規化候補（同じ正規化結果を持つが未統合の名称グループ）
┌────────────────────────────────────────────┐
│ 候補1: 「株式会社サンプル」/「株式会社ｻﾝﾌﾟﾙ」/「株式会社 サンプル」        │
│        [グループを作成して統合]                                        │
│ 候補2: 「A商事(東京)」/「A商事（東京）」                                │
│        [グループを作成して統合]                                        │
└────────────────────────────────────────────┘
※候補が無い場合は「候補はありません」

■ 既存グループ
┌────────────────────────────────────────────┐
│ 株式会社NON（3名称統合）              [編集] [削除]                    │
│   └ 株式会社NON（2） / 株式会社NON（3） / NON商事                      │
└────────────────────────────────────────────┘

■ 原名称一覧（検索可、未所属のみ表示 切替可）
得意先名           所属          直近取引額     件数    操作
サンブラザ工業      未所属        ¥120,000       3件     [グループに追加]
株式会社NON（2）    株式会社NON   ¥80,000        2件     [グループから外す]

[新規グループを作成]（原名称一覧からチェックした複数名称→グループ名入力→作成）

■ 統合プレビュー（グループ作成・メンバー変更を保存する前に表示）
「株式会社NON」へ統合すると:
  2026年 のグループ合計: ¥3,500,000 → 統合前は3名称に分散
  影響する部署: 企画、制作
  [保存する] [キャンセル]
```

##### 7-3. 得意先統合設定 JSON例

`GET /sales-analysis/api/client-groups`

```json
{
  "candidates": [
    {
      "normalized_name": "カブシキガイシャサンプル",
      "client_names": ["株式会社サンプル", "株式会社ｻﾝﾌﾟﾙ", "株式会社 サンプル"]
    }
  ],
  "groups": [
    {
      "id": 3,
      "name": "株式会社NON",
      "members": [
        { "client_name": "株式会社NON（2）", "normalized_name": "カブシキガイシャNON2" },
        { "client_name": "株式会社NON（3）", "normalized_name": "カブシキガイシャNON3" }
      ]
    }
  ],
  "unassigned_clients": [
    { "client_name": "サンブラザ工業", "order_count": 3, "latest_amount": 120000.0, "latest_order_date": "2026-08-20" }
  ]
}
```

- `unassigned_clients`は`sales_orders.client_name`（`sales_active_months`経由の有効データのみ）から
  distinctで抽出し、`sales_client_group_members`に無い名称だけを返す
- `POST /sales-analysis/api/client-groups`（グループ作成: `name`, `client_names[]`）
- `PATCH /sales-analysis/api/client-groups/{group}`（グループ名変更）
- `DELETE /sales-analysis/api/client-groups/{group}`（グループ削除。メンバーもcascade削除）
- `POST /sales-analysis/api/client-groups/{group}/members`（メンバー追加: `client_name`。既に他グループ
  所属なら422、`sales_client_group_members.client_name`のUNIQUE制約が最終防御）
- `DELETE /sales-analysis/api/client-groups/{group}/members/{member}`（メンバー除外）
- `POST /sales-analysis/api/client-groups/preview`（保存前プレビュー: `client_names[]`を受け取り、
  それらを1グループとして統合した場合の直近年合計・影響部署をJSONで返す。DBへは書き込まない）

##### 7-4. 得意先分析 ワイヤーフレーム

期間・期間内の得意先ランキングをまず見せ、そこから個別得意先を選ぶと推移画面に切り替わる
2段階構成（同月比較・左右比較が「期間を選んで得意先内訳を見る」なのに対し、この画面は
「得意先を選んでその推移を見る」という逆方向のため、新規実装する意味がある）。

```
部署: [全部署合計 ▾]   期間: [2022年1月 ▾] 〜 [2026年8月 ▾]   得意先統合: [ON ●]   [得意先名で検索_____]

■ 得意先ランキング（期間内合計・降順）
順位  得意先          期間内合計        構成比    受注件数
1位   A社            ¥12,300,000      18.2%     142件      [推移を見る]
2位   株式会社NON     ¥8,100,000       12.0%     98件       [推移を見る]
...

──「推移を見る」を押すと下に表示──

■ A社 の年別推移（2022〜2026年、統合ON時は統合後の全名称合算）
[棒グラフ: 年別売上]
年     売上           前年差       増減率     受注件数
2022  ¥2,100,000     ─            ─          28件
...

■ A社 の受注一覧（期間内、新しい順）
年月       受注No      品名        分類      金額
2026-08   J-12345     名刺セットA  組版      ¥35,000
```

##### 7-5. 得意先分析 JSON例

`GET /sales-analysis/api/client-analysis/ranking?department_key=all&start_year=2022&start_month=1&end_year=2026&end_month=8&consolidate_clients=true&keyword=`

```json
{
  "total_amount": 67500000.0,
  "ranking": [
    { "client_name": "A社", "amount": 12300000.0, "share_pct": 18.2, "order_count": 142 },
    { "client_name": "株式会社NON", "amount": 8100000.0, "share_pct": 12.0, "order_count": 98 }
  ]
}
```

`GET /sales-analysis/api/client-analysis/detail?department_key=all&client_name=A社&start_year=2022&end_year=2026&consolidate_clients=true`

```json
{
  "client_name": "A社",
  "yearly": [
    { "year": 2022, "amount": 2100000.0, "prior_year_diff": null, "prior_year_rate": null, "order_count": 28 },
    { "year": 2023, "amount": 2300000.0, "prior_year_diff": 200000.0, "prior_year_rate": 9.5, "order_count": 30 }
  ],
  "orders": [
    { "sales_year": 2026, "sales_month": 8, "order_number": "J-12345", "product_name": "名刺セットA", "category": "組版", "order_amount": 35000.0 }
  ]
}
```

- `ranking`は指定期間（年月〜年月、複数年またぎ可）の`activeOrdersQuery`集計。既存の
  `periodOrdersGroupedByClient`/`clientDisplayNameResolver`をそのまま複数年へ拡張して再利用する
  （年をまたぐ場合は年ごとにループしてマージする）
- `detail`の`yearly`は指定した得意先（統合ON時はグループ名、OFF時は原名）に一致する受注だけを
  年別に集計。`orders`は明細ではなく受注一覧を新しい順で返す（上限200件、既存の`searchByProductNameForYear`
  と同様の安全なLIKE/絞り込みパターンを踏襲）
- 新規/離脱の集計はこの画面では実装しない（同月比較・左右比較で既に提供済みのため重複させない）

##### 7-6. 確認事項（実装前にユーザー確認予定）

1. 得意先統合設定画面へのアクセス権限は既存の売上分析権限（SuperAdmin常時・許可Admin/Clerk）と同一でよいか
2. 得意先分析のランキング初期期間は「登録済み全期間」でよいか、それとも直近1年等に絞るか

---

### Phase 8: Excel出力

- 現在の検索条件を反映
- 複数シート
- formula injection対策
- 大量データ時のメモリ確認

### Phase 9: バックアップ

- 取込後バックアップ
- 日次scheduler
- 30日prune、年末保持
- 暗号化、失敗通知、架空DB復元test

### Phase 10: 総合検証・文書・リリース準備

- 全自動test
- `npm run build`
- 権限回帰、通常DBへの誤保存がないこと
- 通常機能の回帰確認
- ChangelogSeeder更新
- `CONSOLIDATED_09_domain_rules.md`更新
- Sakura migration/config/cache/build手順作成
- 本番SSH前に正確なコマンドをユーザーへ提示し、確認を得る
- 完了後にPLAN/MANAGER/PROMPTを`z_instructions/archived/`へ移動

### Phase 11: REVIEW3対応 — High優先度3件（可視化改修着手前の土台修正）

3回目Codexレビュー（`SALES_ANALYSIS_REVIEW3.md` 11.2節）で指摘されたHigh 3件を、14章以降の
大規模UI改修（共通期間ナビゲーター等）に着手する前に先行修正する。

**11-1. 得意先詳細の期間絞り込み**
- `ClientAnalysisController::detail()`に`start_month`/`end_month`を追加（`ranking()`と同じ形）。
- `SalesQueryService::clientDetail()`は、開始年は開始月〜12月・終了年は1月〜終了月・中間年は
  通期という`clientRankingForPeriod()`と同じ月境界ロジックで年別`yearly[]`を集計する。
- `yearIsRegistered`判定も年全体ではなく、その年の対象月範囲内に`SalesActiveMonth`があるかで
  判定する。
- 受注一覧（最大200件）も`(sales_year*100+sales_month)`の範囲で絞り込む（年だけでなく
  月境界も超えない）。
- フロント`ClientAnalysis.vue`は既存の`startMonth`/`endMonth`のrefをdetail取得のparamsへ
  追加するのみ（新規UI要素は無し）。

**11-2. 年次分析の登録月数・欠落月の可視化**
- `annualSummary()`のレスポンスに`registered_months`（登録済み月の配列）・`missing_months`
  （1〜最終登録月の間で欠落している月の配列）を追加する。
- `months_registered`は「登録月数（件数）」の意味に変更する。既存の「最終登録月」の役割は
  新フィールド`last_registered_month`が引き継ぐ。
- `SalesExportService::buildOrdersSheet()`の呼び出し引数を`last_registered_month`に
  差し替える（意味変更に追従。動作は変えない）。
- **確定方針（ユーザー確認済み・2026-09-04）**: 欠落月があっても期間合計
  （`kpi.period_amount`等）にはその月までの実データをそのまま含め、比較不可にはしない。
  UIは`missing_months`が空でない場合に警告バッジを表示する（比較数値は隠さない）。

**11-3. 「全部署合計」の部署別登録状況（coverage）**
- `monthlyFiguresForYear()`が返す月ごとのデータに`registered_departments`（配列）・
  `expected_departments`（配列）を追加する。`department_key='all'`以外（単一部署選択時）は
  常に完全登録として扱う。
- `annualSummary()`の`monthly[]`各要素に`coverage: { registered_departments,
  expected_departments, is_complete }`を追加する。
- **確定方針**: coverageが不完全（`is_complete=false`）でも金額はそのまま合算して表示し、
  UIに「一部登録」バッジを出す（11-2と同じ方針で統一）。
- 影響範囲は`annualSummary()`のみ（`RegistrationStatusController`は単一部署固定のため
  対象外と確認済み）。

**回帰テスト（REVIEW3 16.1節対応、追加）**
- 得意先詳細: 開始月・終了月の範囲外データが混入しない（境界年の一部月のみ）。
- 年次: 欠落月がある年で`registered_months`/`missing_months`が正しく返り、
  `months_registered`が件数と一致する。
- 年次: 全部署合計で1部署だけ未登録の月が`coverage.is_complete=false`になる。

### Phase 12: 可視化改修 Priority A（月次分析を完成見本として改修）

REVIEW3 12〜15章の大規模UI/API改修案のうち、17章の実装依頼順3〜5番目
（ワイヤーフレーム→共通部品→月次分析を完成見本として改修）に対応する。
年次・同月比較・得意先分析・左右比較への横展開は別ステップで行う（17章6番目）。

**共通部品（新規、`resources/js/`配下）**
- `Composables/useSalesChart.js` — `yen()`/`pct()`/`pctClass()`/金額tickフォーマッタ等の共通フォーマッタ
- `Components/SalesAnalysis/PeriodNavigator.vue` — 部署・会社統合・年月のv-model、前後月移動
  （12月→翌年1月等の境界越え込み）、「最新登録月」ボタン、未登録月の案内＋前後の登録済み月への
  リンク（`period_status`を利用）
- `Components/SalesAnalysis/RankingPanel.vue` — 得意先/分類/項目で共用するTop10/20切替＋
  「全件を見る」ドロワー（検索300msデバウンス・並べ替え・サーバー側ページング）。
  `fetchPage(params)`関数をpropで受け取るだけの疎結合設計で、他画面への展開時もAPIラッパー関数を
  差し替えるだけで再利用できる

**月次分析画面の構成変更（13.2節準拠、D/Eの重複は統合して解消）**
- A. KPI帯: 当月売上（sparkline付き）／前月比／前年同月比／**同月3年平均との差**（新規）／年度累計
- B. 月の推移グラフ: 従来の「5年通し1本線」を廃止し、**直近13ヶ月＋3ヶ月移動平均**に変更
- C. 同月の複数年比較（新規）: 選択月だけを直近5年の棒グラフ＋3年平均の参照線で表示
- D. 得意先比較: `RankingPanel`＋横棒グラフ。「当月／前月増減／前年同月増減」の3モード切替
  （発散棒グラフ相当、`diff`/`rate`で表現）。行クリックで得意先分析画面へ遷移
- E. 内訳（分類/項目のみ、得意先はDで扱うためタブから除外）: タブ切替＋`RankingPanel`
- 品名検索・受注明細は「詳細を調べる」の折りたたみへ移動（既存機能を維持）
- B/Cのグラフクリックで表示月・年を直接切り替えられる（ページ遷移なしのドリルダウン）

**バックエンド変更（`SalesQueryService`/`MonthlyAnalysisController`）**
- `monthlyTrend()`を`monthSeries()`private helperへ共通化し、`recentMonthlyTrend()`
  （直近Nヶ月＋`moving_avg_3m`）を追加。既存`monthlyTrend()`の動作・シグネチャは変更なし
- `sameMonthAcrossYears()`（選択月×直近N年、未登録年はnull）を追加
- `nearestRegisteredMonths()`（`summary()`の`period_status`として返す）・
  `latestRegisteredMonth()`（`api/latest-period`）を追加
- `monthlyClientPanel()`／`monthlyBreakdownPanel()`＋共通`paginateRankingRows()`を追加し、
  `clients()`/`categories()`/`items()`アクションの応答形状を`{rows,total_count,total_amount,page,limit}`
  へ統一（旧`{ranking,all_count}`/`{breakdown}`形状から変更。呼び出し元は月次分析のみのため影響なし）
- `trend`エンドポイントのクエリパラメータを`years`→`months`（既定13）に変更（破壊的変更、
  月次分析以外に呼び出し元が無いことを確認済み）
- 新規ルート: `api/same-month-history`・`api/latest-period`

**得意先分析への深いリンクは未実装（既知の制限）**: Dセクションの得意先クリックは
現状`client_analysis`ページへ遷移するのみで、クリックした得意先を自動選択する機能は
`ClientAnalysisController`側の対応が必要なため、得意先分析画面への展開ステップ（17章6番目）で
合わせて実装する。

### Phase 13: 可視化改修 Priority A 横展開（年次分析）

REVIEW3 17章6番目の1画面目。共通部品（PeriodNavigator/RankingPanel）を年次分析へ展開する。
月次分析と異なり、月次特有の指標（3ヶ月移動平均・sparkline・同月複数年比較）は14章の
Priority分類では月次専用のPriority A項目であり、年次分析では該当しないため移植しない
（14章の分類に厳密に従うスコープ判断。詳細はMANAGER1.md判断ログ参照）。

**PeriodNavigator拡張**
- `granularity`prop（`'month'`|`'year'`）を追加。`'year'`では月選択・境界越え処理を省略し、
  「← 前年 / 選択年 / 翌年 → / 最新年」のみになる（13.1節の年次向け仕様）
- `allowAllDepartments`propを追加。年次分析は`department_key='all'`（全部署合計）に対応するため

**バックエンド追加**
- `latestRegisteredYear(departmentKey)`（'all'対応）→ `api/annual-latest-period`
- `annualClientPanel()`/`annualBreakdownPanel()`（`monthlyClientPanel()`/`monthlyBreakdownPanel()`と
  同じTop10/20+全件詳細ドロワー契約）→ `api/annual-clients`/`api/annual-categories`/`api/annual-items`。
  得意先パネルはモード切替を持たず、常に前年同期間との差額（`diff`/`rate`）を返す
  （14章Priority A「得意先別の増減寄与」に対応。年次は"前年"以外の比較対象が無いため）
- 既存`periodClientRanking()`/`periodDetailBreakdown()`（`annualSummary()`が使う10件限定版）は
  変更せず、別メソッドとして`lastRegisteredMonthForYear()`ヘルパー経由で追加

**画面変更（AnnualAnalysis.vue）**
- 部署/年/得意先統合の独自フィルタUIをPeriodNavigator（`granularity="year"`,
  `allow-all-departments`）へ置換。URLクエリ同期を追加
- 月別数値表をグラフ直下で初期折りたたみに変更（「数値表を開く」、13.3節準拠）
- 得意先別/分類別/項目別の静的Top10表をRankingPanelへ置換（分類/項目はタブ化）
- 月別推移グラフの月クリックで月次分析画面へ遷移するdrill-downを追加

回帰テスト7件追加（controller層3件・service層2件、既存annualSummary系は変更なし）。

### Phase 14: 可視化改修 Priority A 横展開（同月比較）

REVIEW3 17章6番目の2画面目。同月比較は「年」を持たず1〜12月だけを巡回する画面のため、
PeriodNavigatorに`granularity='month-cyclic'`（年表示なし、12⇄1月の巡回時に年を変更しない）を
追加して対応した。

**スコープ判断（Phase 13と同じ基準）**: 得意先マトリクス（年×得意先）・新規/離脱リスト・
分類/項目の1・3・5年前比較は、RankingPanelが前提とする単純な`{label,amount}`ランキング形状とは
異なる多次元データ構造であり、14章のPriority A「Top10/20＋その他＋詳細」を機械的に適用すると
データモデルごとの作り直しが必要になる。これは13.4節が示す将来のタブ/カード化（Priority B/C寄り）に
近いため、今回はPeriodNavigator（Priority A「期間ナビゲーターと条件引継ぎ」）の展開のみに留め、
既存の得意先マトリクス等はそのまま維持した。

**バックエンド追加**
- `latestRegisteredMonthNumber(departmentKey)`（'all'対応、年を返さず月だけを返す）→
  `api/same-month-comparison-latest-period`

**画面変更（SameMonthComparison.vue）**
- 部署/対象月/得意先統合の独自フィルタUIをPeriodNavigator（`granularity="month-cyclic"`,
  `allow-all-departments`）へ置換。年数（5/10年）トグルは別ブロックとして維持。URLクエリ同期を追加

回帰テスト2件追加。

---

### Phase 15: 可視化改修 Priority A 横展開（得意先分析）＋深いリンク解消

REVIEW3 17章6番目の3画面目。月次/年次分析のDセクション（得意先クリック）から得意先分析画面への
深いリンク（クリックした得意先を自動選択、Phase 12で未実装として先送りしていた分）もここで解消する。

**スコープ判断（Phase 13/14と同じ基準）**: 13.5節が示す「月別売上折れ線を主表示にする」「表示期間
プリセット（直近12ヶ月/3年/5年/全期間）」「前年同月・3ヶ月移動平均・同月3年平均の比較線」は
14章のPriority分類に無く、月次分析専用の指標（3ヶ月移動平均・同月3年平均）を年次単位の推移に
転用するには集計方式の再設計が要る。今回は既存の「年別推移（棒グラフ）」はそのまま維持し、
14章Priority A「Top10/20＋その他＋詳細」（得意先ランキング）と「期間ナビゲーターと条件引継ぎ」
（深いリンク）のみを対象とした。受注一覧（最大200件）も同じ理由でRankingPanel化せず現状維持。

**バックエンド追加**
- `ClientAnalysisController::index()`にクエリパラメータ`department_key`/`client_name`を追加。
  有効な部署キーであれば`initialDepartmentKey`、`client_name`があれば`initialClientName`として
  Inertia propsへ渡す（値が無ければ従来どおり`department_key='all'`・選択なし）
- `SalesQueryService::clientAnalysisPanel()`（既存`clientRankingForPeriod()`の集計ロジックを
  `mergeClientAggregatesForRange()`ヘルパーへ共通化した上で新設）→ `api/client-analysis/ranking-panel`。
  既存`clientRankingForPeriod()`/`ranking()`エンドポイントの出力形状・挙動は変更していない
  （既存テストとの互換性を維持するため、置き換えではなく追加とした）

**画面変更（ClientAnalysis.vue）**
- 得意先ランキングの静的テーブルをRankingPanelへ置換（ページ内単独のキーワード入力欄は
  RankingPanelの全件詳細ドロワー内検索に統合したため削除）
- `initialDepartmentKey`/`initialClientName` propsを受け取り、`initialClientName`があれば
  マウント時に自動で該当得意先の推移を表示する
- `MonthlyAnalysis.vue`/`AnnualAnalysis.vue`の得意先クリック（Dセクション/得意先別RankingPanel）が
  `department_key`・`client_name`をクエリ引き継ぎで渡すように変更（Phase 12/13で先送りしていた分）

回帰テスト5件追加（controller層3件・service層1件、既存`clientRankingForPeriod()`系は変更なし）。

---

### Phase 16: 左右比較の横展開検討（対象外と判断）

REVIEW3 17章6番目の最終画面。検討の結果、左右比較へは共通部品を展開しない方針とした。

**理由**
- PeriodNavigator/RankingPanelはいずれも「単一の期間・単一の金額列」を前提に設計している。
  左右比較はA/B**2つの独立した期間**（年 or 年月、それぞれ別々に選択）を同時に扱う画面であり、
  月次/年次/同月比較（単一期間）とは構造が異なるため、PeriodNavigatorをそのまま適用できない
- 得意先・分類・項目の比較表は`amount_a`/`amount_b`の**2列を並べて見せる**ことが画面の目的であり、
  RankingPanelの「1金額列＋差額/増減率」という行形状に押し込めると片方の金額列が失われる
  （13.6節が示す「横棒の比較チャートでAとBを同じ行で並べる」は、RankingPanelの再利用ではなく
  新規のデュアル期間・デュアル金額専用コンポーネントが必要になり、Priority B/C相当の再設計）
- 得意先比較は既にTop N＋「その他」集計（`others_amount_a`/`others_amount_b`）を備えており、
  14章Priority A「Top10/20＋その他＋詳細」の趣旨は既存実装でも一定満たしている

結論として`SideBySideComparisonController`/`SideBySideComparison.vue`には変更を加えていない。
デュアル期間ナビゲーター・デュアル金額RankingPanelが必要になった場合は、Priority B着手時
（17章7番目）に新規コンポーネントとして設計する。

これでREVIEW3 17章6番目（年次・同月比較・得意先分析・左右比較への横展開）が完了した。

---

### Phase 17: 期別分析（4月〜翌3月の会計年度）を新規画面として独立

実機フィードバック対応（2026-09-04）。月次分析の「年度累計」KPIカードに設けていた
暦年/年度(4月)のトグル、および後にナビゲーションタブへ移設したスイッチについて、
ユーザーから「年と期の分類、意味がないので削除（見えなくするのでもよい）」との指摘を受けた。
対応として、スイッチ自体は撤去し、代わりに会計年度（4月始まり）専用の分析画面
「期別分析」を、既存の「年次分析」とフル機能で対応する形（ユーザー選択）で新設した。

**設計方針**
- 「年次分析」（暦年）とは意図的に完全に別のコントローラー・サービスメソッド群として実装し、
  既存の暦年ロジック（`annualSummary()`系）には一切手を入れない（低リスク・DRYよりも
  独立した安全性を優先。年またぎ集計という別種の複雑さを持つため）
- 期（fiscal_year=F）はF年4月〜F+1年3月。月配列は`fiscal_month`（1=4月〜12=翌3月）で
  管理し、各要素に実際の`calendar_year`/`calendar_month`を持たせる
- 得意先・分類・項目の年またぎ集計は、既存の`mergeClientAggregatesForRange()`（得意先分析用に
  Phase 15で新設済み）をそのまま再利用し、分類/項目向けに新規`mergeDetailBreakdownForRange()`を
  追加した

**バックエンド追加（`SalesQueryService`）**
- `fiscalYearSummary()`（annualSummary()の期別版。registered_months/missing_months/
  coverage等の仕組みはPhase11と同じ設計を踏襲）
- `fiscalYearClientPanel()`/`fiscalYearBreakdownPanel()`（Top10/20+全件詳細ドロワー、
  annualClientPanel()/annualBreakdownPanel()と同じ契約）
- `multiYearFiscalMonthlySeries()`（月別売上グラフの複数期重ね表示、2/3/5期切替）
- `latestRegisteredFiscalYear()`（期間ナビゲーターの「最新期」ボタン用。3月は前年度、
  4月は当年度として扱う）
- `searchByProductNameForFiscalYear()`/`fiscalYearOrders()`（品名検索・Excel該当明細用）
- 新規`FiscalYearAnalysisController`（`AnnualAnalysisController`と対応する構成。
  index/summary/latestPeriod/clients/categories/items/multiYearTrend/products/export）
- `SalesExportService::fiscalYearAnalysisWorkbook()`（Excel出力。概要・月別推移・該当明細は
  期別専用メソッドを新設、得意先別/分類別/項目別は`annualAnalysisWorkbook()`と同じ
  `buildClientSheet()`/`buildBreakdownSheet()`を共用）

**画面（新規`FiscalYearAnalysis.vue`）**
- `AnnualAnalysis.vue`と同じ構成（KPI帯・複数期重ね折れ線・得意先別/分類/項目パネル・
  品名検索・Excel出力）。月配列は4月始まりの`monthLabels`を使う
- `PeriodNavigator.vue`に`yearLabel`propを追加（既定`'年'`、期別分析では`'年度'`を指定。
  前後移動ボタンも「前{{yearLabel}}」等に汎用化）
- ナビゲーションタブに「期別分析」を追加

**削除**
- `SalesAnalysisNavigationTabs.vue`の暦年/年度スイッチ、`useFiscalMode.js`composableを撤去
- `MonthlyAnalysis.vue`の「年度累計」カードは暦年（1〜12月）固定の「年間累計」に簡素化
  （期別の数値は期別分析画面で見る設計に統一）

回帰テスト14件追加（controller層10件・service層4件）。

---

### Phase 18: 商品分析画面を新規追加（新規/取扱終了商品パネル含む）

ユーザーからの要望（2026-09-05）: 「分析画面で今実装しているもののほかにあると便利なもの」を
問われ、得意先分析と対称な「商品分析」が無いことを提案し合意。さらに事務・経理からの要望として
「前年比較で大きく差があったときに何がなくなったのか、追加になったのかを調べたい」を受け、
新規/取扱終了商品の可視化も同時に実装した。

**設計方針**
- `ClientAnalysisController`/`clientAnalysisPanel()`/`clientDetail()`と対称構造で実装。
  商品には「得意先統合」に相当する名寄せ概念が無いため consolidate 系引数は持たない
- 「新規/取扱終了商品」パネルは、ランキングの自由な期間指定とは独立させ、常に
  「直近登録年 対 前年」で固定比較する（ユーザー確認済み。同月比較の
  `buildSameMonthClientComparison()`と同じ考え方を年間集計に適用）
- 前年が未登録（開業初年度等）の場合は`has_comparison_pair=false`を返し、0円との差分を
  あたかも「新規」であるかのように誤表示しない

**バックエンド追加（`SalesQueryService`）**
- `productRankingForPeriod()`/`productAnalysisPanel()`（Top10/20+全件詳細ドロワー、
  `clientRankingForPeriod()`/`clientAnalysisPanel()`と同じ契約）
- `mergeProductAggregatesForRange()`/`rangeProductAggregates()`（品名別集計、
  `mergeClientAggregatesForRange()`/`rangeClientAggregates()`の商品版）
- `productDetail()`（年別推移・受注一覧に加え、得意先分析には無い「この商品を購入している
  得意先ランキング」上位10件を追加。商品分析ならではの視点）
- `productYearOverYearComparison()`（新規/取扱終了商品Top10、増加額/減少額上位Top10）
- 新規`ProductAnalysisController`（index/rankingPanel/detail/yearOverYear）

**画面（新規`ProductAnalysis.vue`）**
- `ClientAnalysis.vue`と同じ構成（部署選択・自由期間・RankingPanel・推移グラフ・受注一覧）
- 先頭に「新規/取扱終了商品」パネルを常設（ランキング期間指定とは独立、直近登録年対前年で固定）
- 個別商品の推移表示に「購入している得意先」ミニランキングを追加
- ナビゲーションタブに「商品分析」を追加（得意先分析の直後）

回帰テスト15件追加（service層6件・controller層9件）。Excel出力は対象外
（得意先分析と同様、初期スコープでは持たない）。

**追記（2026-09-05）: 新規/取扱終了商品の年度誤検知を修正**

実機フィードバックで、「2027年度用中学入試問題集組版代」対「2026年度用中学入試問題集組版代」の
ような、年度表記だけが違う同一商品（教材・テキスト類は年度だけ変えて毎年作られる）が
新規/取扱終了として誤表示される問題が判明。新規`ProductNameNormalizer`
（2000〜2040年の4桁数値＋「年度用/年度/年」接尾辞のみを除去、学年表記「3.4.5.6年」等の
1桁数値は対象外）を追加し、`productYearOverYearComparison()`内でのみ年度除去後の名称を
比較キーとして使う（新規`groupByNormalizedProductName()`。同一キーに複数原名が集まった場合は
金額最大の原名を代表表示名とする）。**ランキング・個別商品推移は原名のまま**（対象は
新規/取扱終了パネルのみ、ユーザーに範囲を確認済み）。回帰テスト9件追加（unit 8件・service 1件）。

---

## 11. テスト受入基準

### 権限

- SuperAdminは許可設定を管理できる。
- 許可済みAdmin/Clerkだけが全売上機能へアクセスできる。
- 未許可Admin/Clerk、Leader、Coordinator、Userは直URLでも403。
- ナビ表示とサーバー権限が一致する。

### 取込

- サンプルxlsxを企画として読み取れる。
- 年次1シートを下版日で12か月へ分けられる。
- 月次はフォーム入力年月を正とし、行年月との矛盾を検出できる（タイトル年月は使わない）。
- 複数行受注のM合計/N合計から正しい受注金額を作れる。
- 重複受注、必須欠落、負数、日付不正、部署不一致、金額不一致を拒否できる。
- 元ファイルと一時データが取込後に残らない。

### 版管理

- 再取込後は該当月だけ新版が分析対象になる。
- 旧版は履歴に残る。
- 年次取込後の月次取込で、対象月以外が変化しない。
- 取込失敗でactive monthが切り替わらない。

### 集計

- 月間売上は受注金額の合計と一致する。
- 前月、前年同月、年度累計、5年推移がfixtureの期待値と一致する。
- 会社統合オフは原名別、オンは確定グループだけ合算する。
- 未取込月を0円と誤表示しない。
- 品名検索・全フィルタがExcel出力にも一致する。

### セキュリティ

- 通常DBに売上明細が保存されない。
- ログに得意先名、品名、備考、金額明細、認証情報が出ない。
- 本番売上DBをAIエージェントが照会しない。
- エクスポートやバックアップが公開ディレクトリへ残らない。

---

## 12. スコープ外

- 制作・オンデマンドの実運用
- AI文章分析、Local LLM接続、クラウドAI送信
- 売上予測
- 原価・粗利・入金・消費税込み分析
- 会計ソフトとの自動API連携
- 旧版への手動ロールバックUI（初期版では履歴保持まで）
- PDF定型帳票

---

## 13. 未決事項

Claude Codeは実装前または該当Phase開始前に、一度に複数質問せず一問ずつ確認する。

1. 暗号化バックアップのサーバー外保存先。
2. 実ファイル最大容量を確認したうえでのアップロード上限。
3. 年末バックアップの具体的保持年数。
