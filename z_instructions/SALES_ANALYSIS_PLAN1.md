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
| N | 受注金額 | order_amount_component | 同一受注の途中行は0またはNULL、まとまりの最後（1行だけ）に合計が入る。**受注単位で必須**（同一受注内に正値が1つも無い、または最後の行以外にある場合はブロッキングエラー） |
| O | SB下版日 | plate_date | `YYYY/MM/DD`、月またぎなし。実在日であることを検証（例: 2/31は拒否）。**必須（欠損・解析不能はブロッキングエラー）** |

**空欄を許容する列（B, C, E, F, I, J, K, L, M）は行を除外せず、NULLのまま保存し警告として提示する。数値の空欄を0へ勝手に変換して保存しない（NULLと0は意味が異なる）。**

### 2.4 売上計算ルール

2026-09-03 Codexレビューにより更新（同6.2 High-3 / Medium-1、6.3、ユーザー確認済み）。

- 月間売上は、受注ごとの `受注金額`（N列由来）の合計。
- 同一受注Noには複数明細があり得る。
- 同一受注内では途中行の受注金額は0またはNULL、最後の行に受注全体の金額（正値）が入る。**0より大きいN列は原則その受注内で1行だけ、かつ受注グループの最後の行であることを検証する**（複数行にある・最後の行でない・正値が1つも無い、はいずれもブロッキングエラー）。
- DB上の受注金額 `order_amount` は、上記規則で確定した「最後の行の正値」を採用する（単純な N 列合計ではない）。
- 同一受注Noの M 列合計と N 列受注金額が不一致でも、**N列を正式な売上として採用し、警告付きで取込を許可する**（ブロッキングエラーにしない）。差額は「未配賦額」（受注金額合計 − 明細内訳合計）としてプレビュー・ダッシュボード・Excel出力で常に確認できるようにし、隠さない。
- 売上金額は税抜。
- 値引き・返品・取消によるマイナス受注金額はない。
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
- 2026-09-03変更（実機検証・ユーザー確認）: 金額（M列）・単価は「事故損金」等の値引き・調整行で正当に負数になり得るため負数チェックの対象外とする。色数・台数・受注金額（N列）は引き続き負数を拒否する。
- 同一受注Noで得意先名、品名、下版日が矛盾する場合は確定前エラー
- N列（受注金額）規則: 同一受注No内で0より大きい値を持つ行が「ちょうど1行」かつ「その受注グループの最後の行」であることを検証する。正値が0件・複数件・最後の行以外にある場合はblocking error。
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

---

### Phase 7: 得意先統合

- 正規化候補
- 手動グループCRUD
- 統合プレビュー
- 自動統合をしないことのtest

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
