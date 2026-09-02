# SunBWork — システム概要（外部AI向けコンテキスト資料）

> **このファイルの使い方**
> 外部のAIサービス（Genspark、ChatGPT、Gemini などのブラウザ側エージェント）に対して、
> 「このシステムに新機能を追加したい」「こういう画面を設計してほしい」と依頼するときに、
> **依頼文の冒頭にこのファイル全体を貼り付ける**ことを想定した資料です。
>
> AIへの指示: 以下は既存の実稼働システムの技術仕様です。**このシステムの制約を必ず守った上で**提案・設計してください。
> ここに書かれていない前提を勝手に導入しないでください（例: Next.js を使う、Node.js の常駐プロセスを立てる、Redis を使う 等）。
> 判断に迷う点があれば、勝手に決めずに「確認が必要な点」として明示してください。
>
> ※このファイルには接続情報・認証情報・実ドメイン・取引先名などの機密情報は含めていません。

---

## 0. 30秒サマリー（最重要）

| 項目 | 内容 |
|---|---|
| 種別 | 社内業務管理システム（BtoB社内利用、一般公開なし） |
| 業種 | 印刷・組版会社 |
| 構成 | **Laravel 12 (PHP 8.2+) + Vue 3 + Inertia.js のモノリシックSPA** |
| ビルド | Vite / Tailwind CSS |
| DB | MySQL |
| 認証 | Laravel Jetstream + Sanctum（Cookieセッション方式のSPA認証） |
| ホスティング | **さくらのレンタルサーバー（共有ホスティング）** ← 制約が非常に大きい。第9章必読 |
| 公開パス | 独自ドメインの **サブディレクトリ配下**（ルート直下ではない） |
| タイムゾーン | **Asia/Tokyo (JST)** で稼働。UTCとの差でバグが頻発する領域あり（第10章必読） |
| 規模感 | Eloquentモデル 約130、コントローラ 約200、マイグレーション 約340、Inertiaページ 40カテゴリ超 |
| 開発言語 | コメント・UI文言はすべて**日本語** |

**このシステムは「小さなアプリ」ではなく、数年運用されている中〜大規模の業務システムです。**
新機能は「ゼロから作る」のではなく「既存の構造に沿って足す」ことが求められます。

---

## 1. アーキテクチャの基本（Inertia.js を理解していないと設計を誤る）

このシステムは **Inertia.js** を採用しています。これは「SPAだがREST APIを作らない」という特殊な構成です。

### 1-1. リクエストの流れ

```
ブラウザ
  ↓ 通常のリンククリック（Inertia がインターセプトして XHR 化）
Laravel の routes/web.php
  ↓
Controller（認可・DB取得）
  ↓ return Inertia::render('Coordinator/ProjectJobIndex', ['jobs' => $jobs])
Inertia がページコンポーネント名 + props を JSON で返す
  ↓
resources/js/Pages/Coordinator/ProjectJobIndex.vue が props を受け取って描画
（フルページリロードは発生しない）
```

### 1-2. これが意味する設計上の重要ポイント

- **REST API を新規に作らないのが原則。** データ取得は「Controller が Inertia::render の props で渡す」。
  `fetch('/api/xxx')` を書くのは、非ページ遷移の部分更新（オートコンプリート、ポーリング、モーダル内の追加読み込み等）に限る。
- **フロントエンドに独立したルーターは存在しない。** Vue Router は使っていない。ルーティングは Laravel 側の `routes/web.php` が唯一の正。
- **状態管理ライブラリ（Pinia / Vuex）は使っていない。** サーバから毎回 props が来るのでクライアント側グローバルストアは基本不要。
  必要なら Vue の `provide/inject` か `composable` を使う。
- フォーム送信は Inertia の `useForm()` を使う。バリデーションエラーは Laravel の `$errors` が自動で props に載る。
- 「SSRサーバーを立てる」構成は**採用していない**（ビルドスクリプトに `build:ssr` はあるが本番未使用）。

### 1-3. 主要ファイルの役割

| パス | 役割 |
|---|---|
| `routes/web.php` | **全SPAルートの定義（約1400行）**。新機能のルートはここに追加 |
| `routes/api.php` | ファイルアップロード等、限定用途のみ |
| `app/Http/Controllers/` | ロール別サブディレクトリに分かれている（後述） |
| `app/Models/` | Eloquentモデル |
| `app/Http/Middleware/HandleInertiaRequests.php` | 全ページ共通で共有される props（ログインユーザー、通知件数など）を定義 |
| `resources/js/Pages/` | Inertiaページコンポーネント |
| `resources/js/layouts/AppLayout.vue` | 全ページ共通レイアウト |
| `resources/js/Components/` | プロジェクト固有コンポーネント（**大文字始まり**） |
| `resources/js/components/ui/` | shadcn/ui 系の汎用UI部品（**小文字始まり**） |

---

## 2. 技術スタック 詳細

### バックエンド（composer）
```
php                      ^8.2
laravel/framework        ^12.0
inertiajs/inertia-laravel ^2.0
laravel/jetstream        ^5.3      認証・チーム機能のスキャフォールド
laravel/sanctum          ^4.0      SPA Cookie 認証
laravel/reverb           ^1.5      WebSocket サーバー（※ホスティング制約により本番稼働は限定的）
tightenco/ziggy          ^2.0      Laravel のルート定義を JS 側に公開
barryvdh/laravel-dompdf  ^3.1      PDF 生成
phpoffice/phpspreadsheet ^5.7      Excel 入出力
intervention/image       ^3.11     画像処理
ezyang/htmlpurifier      ^4.18     リッチテキストのサニタイズ
```
開発用: Pest（テスト）, Laravel Pint（整形）, Laravel Sail（Docker）

### フロントエンド（npm）
```
vue                    ^3.3
@inertiajs/vue3        ^2.0
vite                   ^7.0
tailwindcss            ^3.4 / ^4.1（移行過渡期のため両系統が同居している）
typescript             ^5.2      ※ .vue は基本 JS。TS は部分的
ziggy-js               ^2.5      JS から route() を使う
laravel-echo + pusher-js         リアルタイム購読
@fullcalendar/*        ^6.1      カレンダーUI（daygrid / timegrid / interaction）
chart.js               ^4.5      グラフ
frappe-gantt           ^1.0      ガントチャート
@tiptap/vue-3, quill, @vueup/vue-quill   リッチテキストエディタ（複数系統が同居）
xlsx (SheetJS), papaparse        Excel / CSV クライアント処理
jspdf, pdfjs-dist, jszip         PDF 生成・表示、ZIP
dompurify                        XSS 対策
reka-ui, class-variance-authority, clsx, tailwind-merge   shadcn/ui 系の基盤
lucide-vue-next, @fortawesome/*  アイコン（2系統が同居）
vue-final-modal        ^4.5      モーダル
@vueuse/core           ^12.8     コンポーザブル集
```

> **注意:** リッチエディタ・アイコン・Tailwind のように**同種のライブラリが複数入っている**箇所があります。
> 新機能を提案する際は「新しいライブラリを足す」より「既存にあるものを使う」を優先してください。

### 開発環境
- Docker Compose（`laravel` コンテナ内で artisan を実行）
- WSL2 上の Linux で開発

---

## 3. ユーザーロールと権限モデル

`users.user_role` カラムの単一文字列でロールを判定しています（Spatie 等の権限パッケージは未使用）。

| ロール値 | 名称 | 役割 | UIカラー |
|---|---|---|---|
| `superadmin` | SuperAdmin | システム全体管理。会社をまたいで操作可能 | 黄 |
| `admin` | Admin | 自社のユーザー・マスタ管理 | 赤 |
| `leader` | Leader | 部署リーダー。部署メンバー管理・案件参照・工数分析 | オレンジ |
| `coordinator` | Coordinator | **案件オーナー**。案件を作りメンバーにジョブを割り当てる中核ロール | 緑 |
| `proof_coordinator` | ProofCoordinator | 校正（プルーフ）工程専門のコーディネーター | 緑系 |
| `clerk` | Clerk | 事務・経理。Coordinator 相当の権限 | 紫 |
| `user` | User | 実作業者。ジョブを受け取り、日報・工数を入力する | 青 |

### 権限の実装
- **ミドルウェア** で経路単位に制限: `AdminMiddleware`, `LeaderMiddleware`, `CoordinatorMiddleware`, `ClerkMiddleware`, `ProofCoordinatorMiddleware`, `SuperadminMiddleware`, `OwnerMiddleware` など
- **細粒度の権限** は別テーブル: `admin_permissions`, `leader_permissions`（機能ごとのON/OFFフラグ）
- コントローラは `app/Http/Controllers/{Admin,Leader,Coordinator,ProofCoordinator,Clerk,SuperAdmin,User}/` とロール別に物理分割されている
- ページも `resources/js/Pages/{Admin,Leader,Coordinator,...}/` とロール別に分割

**→ 新機能を設計するときは「どのロールが使うのか」を最初に決めること。** それによって配置ディレクトリ・ミドルウェア・ページ配置がすべて決まります。

### マルチテナント
`companies` / `company_groups` / `departments` / `teams` / `units` の階層があり、
ユーザーは会社・部署に所属します。SuperAdmin は「コンテキスト切り替え」で操作対象会社を切り替えられます。

---

## 4. 主要な機能ドメイン（既存機能の全体像）

新機能を考えるときは、既にこれだけの機能があることを前提にしてください。

### 4-1. 案件・ジョブ管理（システムの中核）
- **ProjectJob（案件）** — 受注案件。Coordinator が登録する
- **ProjectJobAssignment（ジョブ割当）** — 案件を分割して作業者に割り当てた単位。**この1テーブルが2つの概念を兼ねている**:
  - `sender_id === user_id` → **自己割当（MyJobBox）**: 作業者が自分で立てた作業
  - `sender_id !== user_id` または NULL → **Coordinator割当（JobBox）**: 上から降ってきた仕事
- **JobBox / MyJobBox** — 作業者から見た「受信箱」UI
- 続きジョブのチェーン（`source_assignment_id`）、依頼ジョブの置き換え（`supersedes_assignment_id`）
- ジョブ依頼（JobRequest）、ジョブ通知（JobNotification）
- CSV一括インポート、テンプレートからの一括作成

### 4-2. スケジュール・カレンダー
- FullCalendar ベースの月/週/日ビュー、ドラッグ&ドロップでの時間変更
- `events` テーブルが実際の予定枠。ジョブ割当と連動する
- 個人カレンダー / 部署カレンダー / オペレーター稼働カレンダー / 会議室予約
- 社内予定・外出予定・客先予定などのイベント種別
- **注意: カレンダー周りはこのシステムで最もバグが出やすい領域です（第10章参照）**

### 4-3. 進行管理シート / ワークフローシート
- `progress_sheets` / `progress_rows` / `progress_cells` — 表形式の進行管理表
- `workflow_sheets` / `workflow_rows` / `workflow_cells` — 工程管理表
- テンプレートから生成、セル単位の編集、Coordinator のお気に入り登録

### 4-4. 日報（Diary）
- 作業者が日々の作業内容を投稿、コメント・既読管理
- 部署（DiaryTeam）単位での閲覧・一括既読

### 4-5. 工数・稼働分析（WorkloadAnalyzer）
- `work_records` に工数を記録し、Leader が部署の負荷を分析
- 昼休憩・勤務時間設定（`user_settings` / `user_monthly_breaks` / `user_monthly_schedules`）を加味して実働時間を算出
- Chart.js でのグラフ表示、カスタムフィールド設定

### 4-6. 校正（Proof / Prepress）
- 校正依頼・校正予約・校正チーム・ディスパッチャ
- 製版チケット（PrepressTicket）とステージチェック
- OCR 機能（ローカル Tesseract 連携）

### 4-7. コミュニケーション
- **チャット**（ChatRoom / ChatMessage、既読管理、Laravel Echo によるリアルタイム）
- **メッセージ**（社内メール的な1対多送信、宛先ブック、添付）
- **お知らせ**（Announcement、対象者指定・既読管理）
- **チームルーム（TeamRoom）** — カンバンボード、当番表、議事録、週次投稿、メモ

### 4-8. AI 機能
- AI設定 / AIプリセット / AI会話履歴 / AI要約ジョブ
- チャットボット（ChatBot）
- 外部LLM API を呼び出す構成（`ai_settings` にモデル・キー設定を保持）

### 4-9. その他
- 添付ファイル管理（Attachment、統合ダウンロード、プレビュー）
- 得意先管理（Client）、外注先管理（Subcontractor）、営業担当管理
- 交通費・請求（Billing/Transport）
- ラベル発行システム（配送ルート・学校マスタ等、業務特化）
- 更新ログ（Changelog）— リリースノートをDBで管理し画面表示
- 在席状況ボード（UserPresenceStatus）
- 台本管理（Script）
- NSystem — 別サブシステム（デモページ管理・ゲスト認証）

---

## 5. UI / レイアウト規約

### 5-1. AppLayout が全ページの土台

`resources/js/layouts/AppLayout.vue` が **`py-12` と `max-w-7xl` のラッパーを内部に持っています。**
ページ側は重複ラップしてはいけません。

```vue
<AppLayout title="ページタイトル">
  <template #header><h2>見出し</h2></template>
  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

**AppLayout のスロット:** `#header` / `#headerExtras` / `#tabs` / デフォルト

**AppLayout が provide する値:** `authUser`（ログインユーザー）、`user`（ページの user prop）

**禁止パターン（NG）:**
- ページ内で `<main>` タグを使う
- `py-2` / `py-12` の重複ラップ
- `mx-auto max-w-7xl` の重複ラップ
- トースト通知を各ページで個別に置く（`ToastUnified` が AppLayout にグローバル配置済み）

### 5-2. スタイリング
- **Tailwind CSS ユーティリティクラスで書く。** 独自CSSファイルの新規追加は避ける
- 白カード（`rounded bg-white p-6 shadow`）を基本単位とした業務システム的な見た目
- ロール別カラーが定義済み（第3章の表参照）
- 日本語UI。フォントサイズは情報密度重視でやや小さめ

### 5-3. コンポーネントの使い分け
| ディレクトリ | 用途 |
|---|---|
| `resources/js/Components/`（大文字） | このプロジェクト固有の業務コンポーネント |
| `resources/js/components/ui/`（小文字） | shadcn/ui 系の汎用部品（button, dialog, card, sidebar, tooltip 等） |

---

## 6. ルーティング規約（Ziggy）

### 6-1. JS からは必ず `route()` ヘルパーを使う ⚠️

本番は**サブディレクトリ配下**にデプロイされているため、パスをハードコードすると **404 になります。**

```js
// NG — 本番で 404
window.location.href = `/events/${id}`;
router.get(`/coordinator/project_jobs/${id}`);

// OK — Ziggy がベースパスを解決する
import { router } from '@inertiajs/vue3';
router.get(route('events.show', { event: id }));
```

### 6-2. パラメータはオブジェクトで渡す
```js
route('coordinator.project_jobs.show', { projectJob: job.id });
```

### 6-3. ルート名の命名
`{ロール}.{リソース}.{アクション}` の形式。例: `coordinator.project_jobs.index`, `user.my_project_jobs.show`

---

## 7. データベース設計の考え方

- **MySQL / Eloquent ORM。** マイグレーションは約340ファイル（累積型で、途中で consolidated したものもある）
- 命名は Laravel 標準のスネークケース複数形（`project_job_assignments` など）
- 中間テーブル・ピボットを多用（`team_user`, `company_group_members`, `unit_members` など）
- ソフトデリート、`created_at` / `updated_at` は標準的に付与
- **JSONカラムを設定系で多用**（各種 settings テーブル、カスタムフィールド設定など）

### 設計時の注意
- **本番DBとローカルDBでスキーマが完全一致していない箇所がある。**
  新機能でカラムを追加する場合、必ずマイグレーションを書き、本番にも適用する前提で設計すること
- N+1 が起きやすい構造なので、一覧系では必ず `with()` による eager load を前提に設計する
- 大量データの一覧画面はページネーション前提（`paginate()`）

---

## 8. リアルタイム・非同期処理

| 仕組み | 状況 |
|---|---|
| Laravel Echo + Reverb / Pusher | チャット・通知でブロードキャスト購読。**ただし共有ホスティングのため WebSocket 常駐は制約が大きく、ポーリングにフォールバックしている箇所がある** |
| キュー（Queue） | DB ドライバ。**常駐ワーカーを前提にした設計は避けること**（第9章） |
| スケジューラ（cron） | 使用可能だが実行頻度に制約あり |

**→ 「リアルタイムで即座に反映」を必須とする機能は提案時に慎重になること。**
ポーリング（数秒〜数十秒間隔）で許容できる設計にしておくのが安全です。

---

## 9. ホスティング環境の制約 ⚠️ 最重要

**本番は「さくらのレンタルサーバー」（共有ホスティング）です。VPS でもクラウドでもありません。**
ここを理解せずに設計すると、実装不可能な提案になります。

### 9-1. できないこと / 避けるべきこと

| 事項 | 可否 | 備考 |
|---|---|---|
| Docker / コンテナ | ✗ | 本番では使えない（開発環境のみ） |
| Node.js の常駐サーバー（SSR、Socket.io 等） | ✗ | 常駐プロセスを立てられない |
| Redis / Memcached | ✗ | キャッシュ・セッションは file / database ドライバ |
| 常駐キューワーカー（`queue:work` の永続化） | △ | 不安定。cron 起動の `queue:work --stop-when-empty` 等で代替 |
| 任意のシステムパッケージ導入（apt 等） | ✗ | root 権限なし |
| ImageMagick / ffmpeg 等の外部バイナリ | △ | 使えるものが限られる |
| PHP拡張の追加 | ✗ | サーバー提供のものだけ |
| 大量メモリ・長時間処理 | ✗ | PHP の実行時間・メモリ制限が厳しい。**重い処理は分割・非同期化が必須** |
| WebSocket の常時接続 | △ | 制約あり（第8章） |
| ゼロダウンタイムデプロイ | ✗ | git pull ベースの単純デプロイ |

### 9-2. サブディレクトリ配下で動いている ⚠️

本番URLは `https://（ドメイン）/members` のように**サブディレクトリ配下**です。ルート直下ではありません。

これにより以下が必須になります:

1. **Vite のビルド時に base path を指定する必要がある**（`VITE_APP_BASE_PATH`）。
   ローカル用（空パス）でビルドしたアセットを本番に上げると**全画面が 404 になります。**
2. **JS からのパスはすべて `route()` 経由**（第6章）
3. **画像・アセットのパスも `asset()` / Vite ヘルパー経由**でハードコードしない

### 9-3. CSRF トークンの取得方法 ⚠️

本番環境では `XSRF-TOKEN` クッキーが期待通り発行されず、**419 エラー**になる事象があります。

```js
// NG — 本番で 419
document.cookie.match(/XSRF-TOKEN=([^;]+)/)

// OK — meta タグから取得する
document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
```

### 9-4. デプロイ
git pull ベース。マイグレーション適用漏れが起きると**エラーを出さずに静かに壊れる**ことがあるため、
新機能でスキーマ変更を伴う場合は必ずその旨を明示すること。

---

## 10. 日付・時刻の扱い ⚠️ 事故多発領域

**このシステムは JST（Asia/Tokyo）で稼働しています。**（`config/app.php` の `timezone` = `Asia/Tokyo`）
UTC との9時間差に起因するバグが繰り返し発生しているため、**日付・時刻・カレンダーに関わる機能を設計する際は必ず以下を守ってください。**

### 10-1. Eloquent の `date` キャストは UTC 変換される

`'date'` キャストは JSON シリアライズ時に UTC に変換されます。

| キャスト | JSON出力 | フロントで `.slice(0,10)` した結果 |
|---|---|---|
| `'date'` | `"2026-06-03T15:00:00.000000Z"` | `"2026-06-03"` ← **1日ずれる！** |
| `'date:Y-m-d'` | `"2026-06-04"` | `"2026-06-04"` ✓ |

**→ 日付のみを扱うカラムのキャストは必ず `'date:Y-m-d'` にする。**

### 10-2. JS の `toISOString()` は UTC を返す

```js
// NG — JST 00:00〜08:59 の間、前日の日付になる
const today = new Date().toISOString().slice(0, 10);

// OK — ローカル日付（YYYY-MM-DD形式）
const today = new Date().toLocaleDateString('sv-SE');
```

月初・月末の算出、日付の前後移動なども同様に `toISOString()` を使うとずれます。
（例外的に、日付のみを入力とする計算では `toISOString()` が正しく機能するケースもあり、
既存コードには「正しい `toISOString()`」と「誤った `toISOString()`」が混在しています。**一括置換は禁止。**）

### 10-3. `events` テーブルは保存形式が2種類混在している（歴史的経緯）

| イベント種別 | `starts_at` / `ends_at` の保存形式 |
|---|---|
| 通常イベント（社内予定・外出等） | **JST 文字列**をそのまま保存 |
| 校正ジョブイベント（`job_type='proof'`） | **UTC 文字列**で保存 |

そのため:
- **`Carbon::parse($event->starts_at)` を直接使ってはいけない**（校正イベントで9時間ずれる）
- 読み書きは専用トレイト `app/Http/Controllers/Concerns/CalculatesEventTime.php` のメソッドを経由する
  - `resolveJstCarbon($event, 'starts_at')` — 保存値 → JST Carbon（読み出し）
  - `toEventStorageString($event, $jstDateTime)` — JST日時 → 保存形式文字列（書き込み）
- **期間フィルタを DB の比較だけで書いてはいけない。**
  混在しているため、`whereBetween` だけだと校正予定が漏れる。
  **±9時間のバッファで広く取得し、PHP側で JST に正規化してから期間判定する**のが正しいパターン

**→ 新しくカレンダー系・集計系の機能を設計するときは、必ずこのトレイトを使う前提で設計してください。**

### 10-4. カレンダーのドラッグは2テーブルを更新する必要がある

`project_job_assignments` が正で、`events` はそこから導出される構造です。
`events` だけ更新すると、ジョブ修正ページを開いたときに古い時刻が復元されます。

---

## 11. ファイル入出力

### 11-1. CSV インポート
**社内で使われる CSV は Excel 由来のため、Shift-JIS + CRLF + BOM 対応が必須です。**
`app/Http/Controllers/Concerns/NormalizesCsvEncoding.php` トレイトで正規化してから読み込みます。
新規のCSVインポート機能を設計する場合、必ず「文字コード自動判定・正規化」を含めてください。

### 11-2. Excel
- サーバー側: PhpSpreadsheet（テンプレートに流し込んで出力するパターンが多い）
- クライアント側: SheetJS（xlsx）

### 11-3. PDF
- サーバー側: dompdf（**日本語フォント埋め込みに注意が必要**）
- クライアント側: jsPDF / pdf.js（プレビュー）

### 11-4. 添付ファイル
`attachments` テーブルで一元管理。アップロードは `routes/api.php` の専用コントローラ経由。
**共有ホスティングのため、アップロードサイズ・同時処理数に制限があります。**
大容量ファイル・動画などを前提にした機能は避けてください。

---

## 12. 新機能を提案・設計するときのチェックリスト

外部AIに設計を依頼した結果を評価する際、以下が満たされているか確認してください。

- [ ] **どのロールが使う機能か**を明記しているか（配置ディレクトリ・ミドルウェアが決まる）
- [ ] ルートを `routes/web.php` に追加する前提になっているか（勝手に REST API を作っていないか）
- [ ] データ取得が `Inertia::render()` の props 経由になっているか
- [ ] ページコンポーネントが `AppLayout` を使い、重複ラップしていないか
- [ ] JS のナビゲーションが `route()` を使っているか（パスのハードコードがないか）
- [ ] 日付・時刻を扱うなら JST 前提になっているか。`events` を触るならトレイト経由か
- [ ] 既存テーブル（特に `project_job_assignments`, `events`, `progress_sheets`）との関係を考慮しているか
- [ ] 常駐プロセス・Redis・Docker・Node サーバーを前提にしていないか（**共有ホスティングでは不可**）
- [ ] 重い処理（大量データの一括処理、画像変換など）を分割・非同期化しているか
- [ ] 新しいnpmライブラリを増やしていないか（既存で代替できないか）
- [ ] スキーマ変更があるならマイグレーションを明示しているか
- [ ] UI文言・コメントが日本語になっているか

---

## 13. アンチパターン集（過去に実際に事故が起きたもの）

| やってはいけないこと | 起きること |
|---|---|
| JS でパスをハードコード（`/events/1`） | 本番（サブディレクトリ配下）で全リンクが404 |
| ローカル用 base path でビルドしたアセットを本番に上げる | 本番の全画面で 404 |
| CSRF を Cookie から取得 | 本番で 419 エラー |
| `Carbon::parse($event->starts_at)` を直接使う | 校正イベントの時刻が9時間ずれる |
| `events` への書き込みで常に JST 文字列を保存 | 保存のたびに校正予定が9時間ずつ後ろにずれ続ける |
| `whereBetween('starts_at', ...)` だけで期間を絞る | 早朝勤務者の校正予定がカレンダーから消える／集計から漏れる |
| Eloquent の `'date'` キャストのまま Vue に渡す | 日付が1日前になる |
| `new Date().toISOString().slice(0,10)` | 深夜〜早朝に日付が1日前になる |
| マイグレーションを本番に適用し忘れる | エラーを出さずに静かに機能が壊れる |
| `events` だけ更新して `project_job_assignments` を更新しない | 修正ページを開くと古い時刻が復元される |
| ページ内で `max-w-7xl` を重複ラップ | レイアウトが崩れる／横幅が狭くなる |

---

## 14. 用語集

| 用語 | 意味 |
|---|---|
| **ProjectJob（案件）** | 受注した仕事の単位。Coordinator が登録する |
| **Assignment（割当 / ジョブ）** | 案件を分割して作業者に割り当てた作業単位 |
| **JobBox** | 作業者が「上から割り当てられた仕事」を見る受信箱UI |
| **MyJobBox** | 作業者が「自分で立てた作業」を管理するUI |
| **Coordinator（コーディネーター）** | 案件オーナー。仕事を分配する人 |
| **進行表 / 進行管理シート** | 案件の進捗を表形式で管理するシート |
| **ワークフローシート** | 工程の流れを表形式で管理するシート |
| **日報（Diary）** | 作業者が日々の作業を記録・共有する機能 |
| **工数** | 作業に要した時間。分析・原価計算に使う |
| **校正 / プルーフ（Proof）** | 印刷前の内容確認工程 |
| **製版 / プリプレス（Prepress）** | 印刷版を作る前工程 |
| **組版** | 文字・図版を紙面に配置する作業 |
| **在席ボード** | 誰が出社・外出・在宅かを一覧表示する機能 |

---

## 15. AIへの依頼テンプレート例

このファイルを貼った後、以下のように依頼すると精度が上がります。

```
上記は既存システム「SunBWork」の技術仕様です。

【依頼】
（例）作業者が自分の月間工数をひと目で把握できるダッシュボードを追加したい。

【出してほしいもの】
1. 機能の要件定義（誰が・何を・どう使うか）
2. 画面設計（ワイヤーフレームをテキストまたは簡易HTMLで）
3. 必要なDBの追加・変更（既存テーブルで足りるかどうかも判断すること）
4. 追加するルート（routes/web.php のルート名込み）
5. 追加するコントローラとページコンポーネントのファイルパス
6. 上記「チェックリスト」に照らした自己確認結果
7. 前提が不明で判断できなかった点のリスト

【厳守事項】
- 共有ホスティング（さくらのレンタルサーバー）で動く構成にすること
- Inertia.js 構成を崩さないこと（REST API を新設しないこと）
- 新しいライブラリを極力追加しないこと
- 日付・時刻を扱う場合は JST 前提とし、events テーブルの混在ルールを考慮すること
- UI文言はすべて日本語にすること
```

---

*このドキュメントは外部AIへ渡すことを前提に、接続情報・実ドメイン・取引先名・個人情報を除いて作成されています。*
*内容が古くなった場合は、`CLAUDE.md` および `z_instructions/CONSOLIDATED_*.md` を正として更新してください。*
