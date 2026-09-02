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
- 初期実装の分析対象は `企画` のみ。
- 3部署は別々の Excel ファイルとして出力される。
- 将来 `制作`、`オンデマンド` を追加できるよう、DB と処理は部署キーを持たせる。ただし初期 UI では企画以外を選択・取込できないようにする。

### 2.2 入力ファイル

- 形式: `.xlsx`
- 年次ファイル名: `企画_2025年.xlsx`
- 月次ファイル名: `企画_2026年09月.xlsx`
- ファイル名は利用者の確認補助。部署・対象期間は Excel 内部のヘッダーと明細を検証して確定する。
- 年次ファイルは1シートに1月〜12月の明細が下版日順で入る。
- 月次ファイルはタイトル年月を売上月とする。
- 年次ファイルは各行の `SB下版日` から売上月を決める。
- 列構成は年次・月次とも同じ15列。
- 現行サンプル: `z_instructions/sanbrain_meisai_sample.xlsx`。実装前に匿名化済みかをユーザーへ確認し、確認できない場合は構造確認だけに留め、値をテストfixtureへ転載しない。
- 元 Excel は経理側に原本が保存される。アプリ側では取込処理中だけ非公開一時領域へ保存し、成功・失敗にかかわらず処理後に削除する。

### 2.3 Excel 列

| 列 | 帳票見出し | DB上の意味 | 型・注意 |
|---|---|---|---|
| A | 受注No | order_number | 文字列。全期間で一意 |
| B | 得意先名 | client_name | 経理マスタ由来。完全一致を基本 |
| C | 品名 | product_name | 検索・集計対象 |
| D | 部品名 | part_name | nullable |
| E | 分類 | category | 集計対象 |
| F | 項目 | item_name | 集計対象 |
| G | 進行 | progress | nullable |
| H | 備考 | remarks | nullable |
| I | 判型 | format_size | 文字列 |
| J | 色数 | color_count | 0以上の整数を基本とするが原文も保持可能な設計 |
| K | 台数 | quantity | 0以上の数値 |
| L | 単価 | unit_price | 税抜、0以上 |
| M | 金額 | line_amount | 明細金額、税抜、0以上 |
| N | 受注金額 | order_amount_component | 同一受注の途中行は0、まとまりの最後に合計 |
| O | SB下版日 | plate_date | `YYYY/MM/DD`、月またぎなし |

### 2.4 売上計算ルール

- 月間売上は、受注ごとの `受注金額` の合計。
- 同一受注Noには複数明細があり得る。
- 同一受注内では途中行の受注金額は0、最後の行に受注全体の金額が入る。
- DB上の受注金額は、同一受注Noに属する全行の N 列合計として算出する。これにより記載位置に依存しない。
- 同一受注Noの M 列合計と N 列合計が一致することを取込検証する。不一致なら自動確定せず、行番号・受注No・差額をプレビューへ警告またはエラーとして表示する。
- 売上金額は税抜。
- 値引き・返品・取消によるマイナス受注金額はない。
- 途中経過ファイルでも、掲載済み受注は受注金額まで確定している。
- 受注Noは全期間で一意で、月またぎしない。

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
| source_type | varchar(16) | `annual` / `monthly` |
| source_year | smallint | ファイル対象年 |
| source_month | tinyint nullable | 月次のみ |
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
- 受注No、得意先名、品名、分類、項目、判型、色数、台数、単価、金額、受注金額、下版日を検証
- 数値セルのカンマ、文字列数値、全角数字を安全に正規化
- 日付はExcelシリアル値と`YYYY/MM/DD`文字列の両方に対応し、JSTの日付として`Y-m-d`へ変換
- `toISOString()`等のUTC変換を使わない
- 負数を拒否
- 同一受注Noで得意先名、品名、下版日が矛盾する場合は確定前エラー
- 同一受注NoのM合計とN合計の一致
- 月次ファイルでは全行の下版年月とタイトル年月が一致すること。例外が実データで判明した場合は勝手に許容せず確認
- 年次ファイルでは下版年が対象年と一致すること
- 現在有効な他月に同じ受注Noがないこと

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
- 月次はタイトル年月を正とし、行年月との矛盾を検出できる。
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
