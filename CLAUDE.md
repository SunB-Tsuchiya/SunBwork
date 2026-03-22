# CLAUDE.md - SunBWork プロジェクト ルール

このファイルはすべての会話の開始時に必ず参照すること。コードの作成・修正はここに記載されたルールに従う。

---

## プロジェクト概要

**SunBWork** は Laravel + Inertia.js (Vue 3) + Vite + Tailwind CSS で構築された SPA 型の業務管理システム。

主な機能:
- ユーザー管理（SuperAdmin / Admin / Coordinator / Leader / 一般）
- ProjectJob 管理・割当（JobBox）
- カレンダー・イベント（FullCalendar）
- チャット・Bot（AI チャット）
- 日報（Diary）
- メッセージ・添付ファイル
- ワークロード解析

**技術スタック:**
- バックエンド: Laravel (PHP), MySQL, Laravel Sanctum, Fortify, Jetstream
- フロントエンド: Vue 3, Inertia.js, Vite, Tailwind CSS, Ziggy
- AI: OpenAI API (環境変数管理)
- ストレージ: `Storage::disk('public')` の `attachments/`
- セッション: Sanctum + Cookie ベース（SPA）
- リアルタイム: Laravel Echo (WebSocket)

**Artisan コマンドは必ずコンテナ内で実行:**
```bash
docker compose exec laravel bash -lc "php artisan ..."
```

---

## フォルダ構成

```
SunBWork/
├── app/
│   ├── Actions/
│   │   ├── Fortify/          # ユーザー登録・パスワード更新など
│   │   └── Jetstream/        # チーム管理アクション
│   ├── Console/Commands/     # カスタム Artisan コマンド
│   ├── Events/               # イベント (ChatMessageSent, MessageCreated 等)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/        # Admin 向けコントローラ
│   │   │   ├── Api/          # ファイルアップロード等 API
│   │   │   ├── Auth/         # 認証関連
│   │   │   ├── Bot/          # AI Bot (BotController, BotFileController, AiHistoryController)
│   │   │   ├── Chat/         # チャット (ChatController)
│   │   │   ├── Coordinator/  # Coordinator 向け (ProjectJob, Schedule, WorkItem 等)
│   │   │   ├── Diaries/      # 日報
│   │   │   ├── Leader/       # ワークロード解析
│   │   │   ├── ProjectJobs/  # JobBox, ProjectJobAssignment
│   │   │   ├── Settings/     # プロフィール・パスワード設定
│   │   │   ├── SuperAdmin/   # SuperAdmin 向け
│   │   │   └── User/         # 一般ユーザー向け
│   │   ├── Middleware/       # 認証・ロール別ミドルウェア
│   │   └── Requests/         # FormRequest
│   ├── Jobs/                 # キュージョブ (ProcessUploadJob 等)
│   ├── Listeners/            # イベントリスナー
│   ├── Models/               # Eloquent モデル
│   ├── Policies/             # 認可ポリシー
│   ├── Providers/            # サービスプロバイダ (AppServiceProvider, FortifyServiceProvider 等)
│   └── Services/
│       ├── AttachmentService.php   # 添付ファイル管理（保存・サムネイル・メタ）
│       ├── HtmlSanitizer.php       # HTML サニタイズ
│       └── AiHistoryConsolidator.php
├── database/
│   └── migrations/           # マイグレーション (backups/ は読み飛ばす)
├── resources/
│   ├── js/
│   │   ├── Assets/           # SVG 等のアセット
│   │   ├── Components/       # 共通コンポーネント（大文字始まり）
│   │   │   ├── Tabs/         # ロール別ナビゲーションタブ
│   │   │   │   ├── AdminNavigationTabs.vue
│   │   │   │   ├── CoordinatorNavigationTabs.vue
│   │   │   │   ├── LeaderNavigationTabs.vue
│   │   │   │   ├── SuperAdminNavigationTabs.vue
│   │   │   │   └── UserNavigationTabs.vue
│   │   │   ├── ToastUnified.vue    # 全体トースト通知
│   │   │   ├── Calendar.vue
│   │   │   ├── RichEditor.vue (Quill)
│   │   │   └── ...
│   │   ├── Composables/      # Vue コンポーザブル
│   │   │   ├── useToasts.js
│   │   │   └── useInertiaFetch.js
│   │   ├── Helpers/
│   │   │   └── attachment.js # 添付 URL 正規化ヘルパー
│   │   ├── Pages/            # Inertia ページ（ロール別サブディレクトリ）
│   │   │   ├── Admin/
│   │   │   ├── Bot/
│   │   │   ├── Calendar/
│   │   │   ├── Chat/
│   │   │   ├── Coordinator/
│   │   │   │   └── ProjectJobs/
│   │   │   ├── Diaries/
│   │   │   ├── Events/
│   │   │   ├── JobBox/       # 一般ユーザー用 JobBox
│   │   │   ├── Leader/
│   │   │   ├── Messages/
│   │   │   ├── MyJobBox/     # 自己割当 JobBox
│   │   │   ├── SuperAdmin/
│   │   │   ├── WorkloadAnalyzer/
│   │   │   ├── WorkloadSetting/
│   │   │   └── ...
│   │   ├── layouts/
│   │   │   ├── AppLayout.vue     # メインレイアウト（全ページ共通）
│   │   │   └── AuthLayout.vue
│   │   ├── components/       # shadcn/ui 系コンポーネント（小文字始まり）
│   │   │   └── ui/           # Avatar, Button, Card, Dialog, Sidebar 等
│   │   ├── config/
│   │   │   └── quillToolbar.js
│   │   ├── app.js            # Inertia アプリ初期化
│   │   └── ziggy.js          # Ziggy ルート定義
│   └── views/
│       └── app.blade.php     # Inertia のエントリポイント
├── routes/
│   ├── web.php               # メインルート（SPA 用ストリームも含む）
│   ├── chat.php              # チャット関連ルート
│   ├── api.php               # API ルート（トークン認証）
│   ├── auth.php              # 認証ルート
│   ├── settings.php          # 設定ルート
│   └── channels.php          # Echo チャンネル定義
└── z_instructions/           # プロジェクトルール・ドキュメント（参照用）
```

---

## UI / レイアウト ルール（最優先）

### AppLayout のスロット構造

`AppLayout` (`resources/js/layouts/AppLayout.vue`) は以下の構造を内部に持つ。
`py-12` と `max-w-7xl` はレイアウト側で提供済み。ページの `<slot />` に入る内容を書く。

```vue
<!-- AppLayout 内部 (参考) -->
<div class="py-12">
  <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
    <slot name="tabs" /> <!-- タブ -->
    <main>
      <slot />           <!-- ← ページのコンテンツはここに入る -->
    </main>
  </div>
</div>
```

**ページ側の書き方:**
```vue
<AppLayout title="ページタイトル">
  <template #header>
    <h2>ページ見出し</h2>
  </template>
  <!-- デフォルトスロット: py-12 / max-w-7xl はすでにレイアウト側にある -->
  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

### レイアウト統一ルール（必須）

全ページで以下のパターンに統一すること。新規ページ作成・既存ページ修正の際は必ずこのルールに従う。

**NG パターン（使ってはいけない）:**
```vue
<!-- AppLayout がすでに py-12 / max-w-7xl を提供しているため、以下は二重になる -->
<main>
  <div class="py-2">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="bg-white p-6 shadow-xl sm:rounded-lg">
        <!-- コンテンツ -->
      </div>
    </div>
  </div>
</main>
```

**OK パターン（統一形式）:**
```vue
<!-- デフォルトスロットに直接カードを置く -->
<div class="rounded bg-white p-6 shadow">
  <!-- コンテンツ -->
</div>
```

**チェックリスト（コード修正時に確認）:**
- [ ] `<main>` タグをデフォルトスロット直下に置いていないか
- [ ] `py-2` / `py-12` の重複ラップをしていないか
- [ ] `mx-auto max-w-7xl sm:px-6 lg:px-8` の重複ラップをしていないか
- [ ] カードクラスが `rounded bg-white p-6 shadow` になっているか（`shadow-xl sm:rounded-lg` ではない）

**AppLayout が提供するスロット:**
- `#header` - ページ上部の白いヘッダーバー
- `#headerExtras` - ヘッダーバー右端に追加コンテンツ
- `#tabs` - ロール別ナビゲーションタブ（省略時はロールに応じて自動表示）
- デフォルト - メインコンテンツ

**AppLayout が provide する値（inject で取得可能）:**
- `authUser` - 現在ログイン中のユーザー
- `user` - ページの user prop

**ToastUnified** は AppLayout 内にグローバル配置済み。各ページで重複して置かない。

**ロール別カラー:**
- SuperAdmin: 黄 (`text-yellow-600`)
- Admin: 赤 (`text-red-600`)
- Leader: オレンジ (`text-orange-600`)
- Coordinator: 緑 (`text-green-600`)
- User: 青 (`text-blue-600`)

**UI コンポーネントの使い分け:**
- `resources/js/Components/` (大文字始まり): プロジェクト固有コンポーネント
- `resources/js/components/ui/` (小文字始まり): shadcn/ui 系の汎用 UI

**テーブル:** `min-w-full divide-y divide-gray-200`、ヘッダ `bg-gray-50`、行 `hover:bg-gray-50`
**カード:** `rounded bg-white p-6 shadow`
**レスポンシブ:** Tailwind の `sm/md/lg` を使用
**ファイル名の大文字小文字は一貫させる**

**Ziggy の route() 使用時はパラメータ名をオブジェクトで渡す:**
```js
route('coordinator.project_jobs.show', { projectJob: job.id })
```

---

## ロール共有ページのルーティングルール

### タブメニューとルートプレフィックスの対応

`AppLayout.vue` の `currentRouteContext` computed は現在のルート名のプレフィックスでタブを決定する:

```js
if (r.startsWith('superadmin.')) return 'superadmin';
if (r.startsWith('admin.'))      return 'admin';
if (r.startsWith('leader.') || r.startsWith('workload_setting.')) return 'leader';
if (r.startsWith('coordinator.') || r.startsWith('project_jobs.')) return 'coordinator';
return 'user'; // ← 必ず 'user' を返す。user_role にフォールバックしてはいけない
```

**重要:** フォールバックを `user_role` にすると、上位ロールのユーザーが User ページを開いたとき自分のロールのタブが出てしまう。必ず `'user'` を返すこと。

### 複数ロールで共有するページの実装パターン

Admin と Leader が同じ Vue ページ（例: `Clients/` 配下）を共有する場合、リンク先・フォーム送信先・リダイレクト先をロールに応じて動的に解決する。

**フロントエンド（Vue）— routePrefix computed:**
```js
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    return ['admin', 'superadmin'].includes(role) ? 'admin' : 'leader';
});
```

**テンプレート内での使い方（`.value` は不要）:**
```vue
<!-- テンプレート内: computed は自動アンラップされるので .value なし -->
<Link :href="route(`${routePrefix}.clients.index`)">一覧へ戻る</Link>
```

**スクリプト内での使い方（`.value` が必要）:**
```js
function submit() {
    form.post(route(`${routePrefix.value}.clients.store`));
}
```

> NG: テンプレート内で `${routePrefix.value}` と書くと `'admin'.value` = undefined になる

**バックエンド（PHP）— routePrefix() ヘルパー:**

共有コントローラ（例: `ClientController`）にプライベートメソッドを置き、リダイレクト先をロールで解決する:
```php
private function routePrefix(): string
{
    $role = Auth::user()->user_role ?? 'leader';
    return match ($role) {
        'admin', 'superadmin' => 'admin',
        default => 'leader',
    };
}

// store/update/destroy のリダイレクトで使用
return redirect()->route("{$this->routePrefix()}.clients.index");
```

### Clients テーブルの注意点

`clients` テーブルのカラムは `notes`（詳細テキスト）。フォームフィールド名 `detail` との乖離に注意:
- Vue フォーム初期値: `detail: props.client.notes`（DBカラム名で読む）
- コントローラ保存時: `$data['notes'] = $data['detail'] ?? null; unset($data['detail']);`
- `$fillable` に `'notes'` が含まれていることを確認（`'detail'` は存在しない）

---

## CSV アップロード実装パターン

Admin/Users と Clients で確立した標準パターン。新たに CSV 一括登録を実装する場合はこれに従う。

**ルート定義（リソースルートより前に配置）:**
```php
Route::get('xxx/csv/upload',  [XxxController::class, 'csvUpload'])->name('xxx.csv.upload');
Route::post('xxx/csv/preview',[XxxController::class, 'csvPreview'])->name('xxx.csv.preview');
Route::post('xxx/csv/store',  [XxxController::class, 'csvStore'])->name('xxx.csv.store');
Route::get('xxx/csv/sample',  [XxxController::class, 'csvSampleDownload'])->name('xxx.csv.sample');
Route::resource('xxx', XxxController::class)->only([...]);
```

**Vue ページ構成:**
1. `CsvUpload.vue` — ファイル選択 + サンプル CSV ダウンロードボタン
2. `CsvPreview.vue` — プレビューテーブル + 確認後に store へ POST

**サンプル CSV は BOM 付き UTF-8 で返す（Excel 文字化け対策）:**
```php
return response("\xEF\xBB\xBF" . $csv)
    ->header('Content-Type', 'text/csv; charset=UTF-8')
    ->header('Content-Disposition', 'attachment; filename="sample.csv"');
```

**SuperAdmin 向け会社選択:**
- `csvUpload()` でロールが superadmin の場合のみ `companies` を props に渡す
- Vue 側で `v-if="isSuperAdmin"` のセレクトを表示し、選択必須にする

---

## セキュリティ・認証ルール

- SPA + Sanctum: `StartSession` と CSRF フローが前提
- SPA 用エンドポイントは必ず `web` ミドルウェアで提供 (`routes/web.php` または `routes/chat.php`)
- `routes/api.php` に SPA 向けストリームを置かない（セッションが開始されず 401/404 になる）
- 401 が出た場合: エンドポイントが `web` ミドルウェア経由か確認し、必要なら `routes/web.php` へ移動
- `getaddrinfo for mysql failed` が出た場合: コンテナ外で artisan を実行している → コンテナ内で実行

**サニタイズ必須:**
- HTML/Markdown: `HTMLPurifier` (サーバ) / `DOMPurify` (フロント) を必ず通す
- `App\Services\HtmlSanitizer` を経由すること
- 外部 URL は allow-list で検査する
- ファイルメタは最小情報のみ返す (`original_name`, `mime`, `size`, `path`, `url`)

**CORS:**
- `config/cors.php` と `.env` の `CORS_ALLOWED_ORIGINS` を本番ドメインで限定
- `.env.example` に `CORS_ALLOWED_ORIGINS`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` を明記

---

## 添付ファイルルール

**保存先:** `storage/app/public/attachments/`

**配信:** 直接 `/storage` リンクは使わない。ストリーミングエンドポイント経由で配信:
- `/chat/attachments` (チャット)
- `/bot/attachments` (Bot)
- `/attachments/signed` (署名付き)

**ファイル命名:** `<uuid>_<original_name>`
- UUID は `Str::uuid()` で生成
- `..`, `/`, `\` は `_` に置換して安全化
- 全アップロード経路 (chat, bot, diary, messages) で統一

**重要ファイル:**
- `app/Services/AttachmentService.php`: 保存・サムネイル・メタ整形
- `app/Http/Controllers/AttachmentController.php`: ストリーミング配信
- `resources/js/Helpers/attachment.js`: フロント URL 正規化

**署名付き URL:**
```php
URL::temporarySignedRoute('attachments.signed', $expires, ['path' => $path])
```
- コントローラ内で `URL::hasValidSignature($request)` を再確認
- 未認証ユーザーは署名なしでアクセス不可
- フロントは署名 URL を再エンコードしない

**サムネイル:** `attachments/thumbs/` に保存。`AttachmentService::createThumbnailFromDiskPath` に集約。

**認可:**
- 添付はアップロードした `user_id` または admin のみ削除可能
- メッセージ紐付き添付は送信者・受信者のみ閲覧可（署名 URL は例外）

---

## カレンダー・JobBox ルール

- FullCalendar には Vue の reactive Proxy をそのまま渡さない → `structuredClone` などで plain オブジェクトを渡す
- JobBox の "予定を編集" からカレンダーを開く際は `?date=YYYY-MM-DD&user_id=...` を URL に付与し、Calendar 側で `gotoDate` を呼ぶ
- 日付: サーバは UTC、フロントは JST で変換に注意

**サーバ側:**
- `EventController::store`: `job_id` の有無に応じて `ProjectJobAssignment` を参照し通知を作成
- `CalendarController/index`: events/diaries/jobs をマージした props を返す

---

## JobBox / MyJobBox インデックス仕様

### モデルの違い（重要）

| 機能 | テーブル | モデル | 用途 |
|---|---|---|---|
| JobBox | `project_job_assignment_messages` | `ProjectJobAssignmentMessage` | Coordinator が割り当てたジョブのメッセージ一覧 |
| MyJobBox | `project_job_assignment_by_myself` | `ProjectJobAssignmentByMyself` | ユーザー自身が登録したジョブの一覧 |

**これらは別テーブル・別モデルであり、完了 API も別ルートを使う:**
- JobBox 完了: `jobbox.assignments.complete` → `ProjectJobAssignment` を更新
- MyJobBox 完了: `myjobbox.assignments.complete` → `ProjectJobAssignmentByMyself` を更新

`events.complete` ルートは `ProjectJobAssignment` のみ更新するため **MyJobBox には使えない**。

### テーブルレイアウト（両画面共通の構造）

```
日付グループヘッダー（bg-gray-100）
└─ <table class="min-w-full border">
   ├─ <thead class="bg-gray-50"> 各カラム
   └─ <tbody> 行クリックで詳細 or イベント画面へ遷移
```

**日グループ:** `desired_start_date` / `desired_end_date` / `desired_at` / イベント開始日 の優先順でグループキーを決定。日付降順、同日内は開始時刻昇順。

**JobBox カラム:** 送受信 / 相手 / 時間 / タイトル / クライアント / 既読 / ステータス（完了ボタン付き）

**MyJobBox カラム:** 時間 / タイトル / クライアント / ステータス（完了ボタン付き）

### ステータス表示ロジック

**JobBox (`getAssignmentStatus`):**
1. `assignment.status.key` / `jam.status.key` → switch で日本語変換
2. `completed` boolean フラグ
3. `scheduled` / `scheduled_at` フラグ
4. `read_at` + `accepted` の組み合わせ

**MyJobBox (`getAssignmentStatus`):**
1. `m.completed` boolean フラグを**最優先**（完了ボタンで即時更新するため）
2. `m.status.key` / `m.status_model.key` → switch（`status_model` は DB eager load 時の JSON キー名）
3. `m.scheduled` / `m.scheduled_at` フラグ

**ステータス値とバッジカラー:**

| ステータス | バッジ | 意味 |
|---|---|---|
| 完了 | `bg-yellow-100 text-yellow-800` | 作業完了 |
| セット済み | `bg-blue-100 text-blue-800` | カレンダーに予定セット済み |
| 確認済み | `bg-green-100 text-green-800` | 受信者が確認 |
| 受信済み / 進行中 | `bg-indigo-100 text-indigo-800` | 受信・進行中 |
| 既読済み / - | `bg-gray-100 text-gray-700` | その他 |

### 完了ボタンの実装パターン

完了後は **ローカル状態をスプライスで即時更新**（DB への再フェッチなし）する。
`push/代入` では Vue リアクティビティがトリガーされないため **`splice(idx, 1, newObj)` を使う**。

```js
// JobBox: m.project_job_assignment を更新
msg.project_job_assignment.status = { key: 'completed', name: '完了' };
msg.project_job_assignment.completed = true;

// MyJobBox: m 自体を新オブジェクトで置換
localAssignments.value.splice(idx, 1, {
    ...localAssignments.value[idx],
    completed: true,
    status: { key: 'completed', name: '完了' },
    status_model: { key: 'completed', name: '完了' },
});
```

### Inertia props のローカルコピー（MyJobBox）

Inertia の props は readonly proxy。直接変更できないため以下のパターンでローカルコピーを作る:

```js
const toPlain = (arr) => (Array.isArray(arr) ? arr.map((item) => ({ ...item })) : []);
const localAssignments = ref(toPlain(props.myAssignments?.data));

// DB更新後のInertia props変化を反映
watch(() => props.myAssignments?.data, (newData) => {
    localAssignments.value = toPlain(newData);
});
```

`[...props.data]` のスプレッドでは各要素が proxy のままなので NG。要素レベルで `{ ...item }` が必要。

### フィルター仕様

- **完了非表示チェック:** デフォルト `true`（完了を隠す）。`hideCompleted` ref で管理
- **月セレクター:** `page.props.period_model` で管理。`all` を選ぶと全期間（paginate 上限 500 件）、月指定時は対象月のみ（上限 500 件）
- **テキスト検索:** `page.props.q_model`。Inertia `router.get` でサーバ側検索

### 行クリック動作

1. ボタン・リンク上のクリックは `event.target.closest('a,button')` で無視
2. 割当 ID で `events.index?user_id=...&job=...` を fetch し、紐付いたイベントがあれば `events.show` に遷移
3. イベントがなければ詳細ページ（Show.vue）に遷移

### `completed` カラムについて（MyJobBox）

`project_job_assignment_by_myself` テーブルに `completed boolean default false` カラムが必要。
マイグレーション: `2026_03_20_221931_add_completed_to_project_job_assignment_by_myself_table.php`

コントローラ側で `Schema::hasColumn('project_job_assignment_by_myself', 'completed')` でチェック後にセットすること（カラム未存在時の fallback）。

### Show.vue（MyJobBox 詳細画面）の注意点

- `projectJob` は `$assignment->projectJob ?? null` で null になりうる。テンプレート内で `projectJob.id` を使う箇所は必ず `projectJob?.id` または `v-if="projectJob?.id"` でガードすること
- テンプレート内の `route()` 呼び出しは `import { route } from 'ziggy-js'` を使わず、グローバル `route` に統一:
  ```js
  typeof route === 'function' ? route('route.name', params) : '/fallback-url'
  ```
- `submitComplete` は `myjobbox.assignments.complete` に POST し、成功後 `router.get(route('user.myjobbox.index'))` でインデックスへ遷移（DB の最新ステータスを取得するため）

---

## AI / チャット ルール

- OpenAI キーは環境変数で管理 (`OPENAI_API_KEY`)
- AI が生成する Markdown/HTML は必ず `DOMPurify` / `HTMLPurifier` でサニタイズ
- チャットエクスポートは `storage/app/exports` に出力し、専用ルートでダウンロード
- `BotController.php` (app/Http/Controllers/Bot/): `totalCharsIncluded` の初期化漏れに注意
- ファイルメタは最小情報のみ返す

---

## ワークロード解析

### 関連ファイル一覧

| ファイル | 役割 |
|---|---|
| `app/Http/Controllers/Leader/WorkloadAnalyzerController.php` | 全ロジックの中心。index/show/settings/saveSettings/categoryRank を持つ |
| `app/Models/WorktimeItemType.php` | 残業種別モデル（name, coefficient, sort_order, type） |
| `resources/js/Pages/WorkloadAnalyzer/Index.vue` | 一覧・総合ランキングページ |
| `resources/js/Pages/WorkloadAnalyzer/Show.vue` | 個人詳細ページ（カテゴリ別パネル・レーダーチャート） |
| `resources/js/Pages/WorkloadAnalyzer/CategoryRank.vue` | カテゴリ別ランキングページ |
| `resources/js/Pages/WorkloadAnalyzer/Settings.vue` | 係数設定ページ |
| `resources/js/Components/AnalysisPanel.vue` | カテゴリ別分析パネルコンポーネント（円グラフ＋詳細テーブル） |

---

### ルーティング

**ルート定義は admin / superadmin / leader の 3 グループにそれぞれ同名で定義されている。**
静的ルートをパラメタライズドルート（`{user}`）より前に必ず配置すること。

```php
Route::get('workload-analyzer',              [..., 'index'])        ->name('workload_analyzer.index');
Route::get('workload-analyzer/category-rank',[..., 'index'])        ->name('workload_analyzer.category_rank');
Route::get('workload-analyzer/settings',     [..., 'settings'])     ->name('workload_analyzer.settings');
Route::post('workload-analyzer/settings',    [..., 'saveSettings']) ->name('workload_analyzer.settings.save');
Route::get('workload-analyzer/{user}',       [..., 'show'])         ->name('workload_analyzer.show');
```

`category-rank` と `index` は **同じコントローラメソッド `index()`** を使用し、
メソッド内でルート名を見て Inertia コンポーネントを切り替える：

```php
$routeName = $request->route()?->getName() ?? '';
$component = str_ends_with($routeName, 'category_rank')
    ? 'WorkloadAnalyzer/CategoryRank'
    : 'WorkloadAnalyzer/Index';
return Inertia::render($component, ['companies' => ..., 'selected_ym' => ...]);
```

---

### 係数設定テーブル（DB）

| テーブル | モデル | 主なカラム |
|---|---|---|
| `stages` | `Stage` | name, coefficient |
| `sizes` | `Size` | name, coefficient |
| `work_item_types` | `WorkItemType` | name, coefficient, slug |
| `difficulties` | `Difficulty` | name, coefficient |
| `event_item_types` | `EventItemType` | name, coefficient |
| `worktime_item_types` | `WorktimeItemType` | name, coefficient, sort_order, type('over'/'short') |

`worktime_item_types` の初期データ:
- 残業（type=over, coefficient=1.00, sort_order=1） ← 通常残業（≤3時間/日）
- 早退（type=short, coefficient=1.00, sort_order=2）
- 超過残業（type=over, coefficient=0.80, sort_order=3） ← 超過残業（>3時間/日）

設定ページは XHR (axios) 保存 → JSON レスポンス → toast 通知。

---

### ポイント計算の仕組み（最重要）

#### ① 各カテゴリの生スコア計算

```
ステージ  = Σ (ページ × ステージ係数 × 難易度係数)
サイズ    = Σ (ページ × サイズ係数   × 難易度係数)
種別      = Σ (ページ × 種別係数     × 難易度係数)
難易度    = Σ (ページ × 難易度係数)
イベント  = Σ (イベント時間[h] × イベント種別係数)
残業(通常)= 合計残業分[min, ≤180/日] × 通常残業係数
残業(超過)= 合計残業分[min, >180/日] × 超過残業係数
残業生スコア = 残業(通常) + 残業(超過)
```

- 残業の通常/超過の閾値は **180分（3時間）/日**
- `work_records.overtime_minutes` の値を1日ごとに判定

#### ② パーセンタイルランク変換（部署内）

生スコアをそのまま合算すると、ページ数が多いカテゴリが支配的になるため、
**部署内でパーセンタイル（0〜100）に変換**してから合算する。

```
比較対象: 同部署の全メンバー（N 人）
above = 自分より生スコアが高いメンバー数
tied  = 自分と同じ生スコアのメンバー数
avgRank = above + (tied + 1) / 2      ← 同値タイは平均順位
パーセンタイル = (N − avgRank) / (N − 1) × 100
※ N = 1 の場合は 100 固定
```

- index() では `$calcAggregates` で生スコアを計算後、**部署単位で2次処理**してパーセンタイルを付与
- 結果は `member.aggregates.percentile_scores.{stage|size|type|difficulty|event|overtime|overall}` に格納
- `member.aggregates.points.overall` はパーセンタイルの overall（0〜600）で上書きされる

#### ③ 総合ポイント（0〜600）

```
総合ポイント = ステージ + サイズ + 種別 + 難易度 + イベント + 残業
            （各 0〜100 のパーセンタイル値を合算）
```

#### ④ 偏差値（参考値）

```
比較グループ: 同会社の全ユーザー
z = (自分の総合ポイント − グループ平均) / グループ標準偏差
偏差値 = 50 + 10 × z
```

偏差値の比較母集団は「会社全体」。パーセンタイルの母集団「部署」とは異なる。

#### ⑤ カテゴリ別順位（show ページ用）

```
比較対象: 同会社のアクティブユーザー（当月に作業データがある全員）
各カテゴリの生スコアで降順ソート → 1位から順位付与
同値タイ: 全員に same rank（above のカウント方式）
```

show ページの `category_ranks.{cat}` に格納し、`group_count` が比較人数。

---

### index() のデータフロー

```
1. $calcAggregates クロージャ定義
   - 各ユーザーの 6カテゴリ生スコア + 残業統計を計算
   - points.{stage|size|type|difficulty|event|overtime|overall} に格納
   - points.overtime = normalMin × normalCoeff + excessMin × excessCoeff
   - points.overall（この時点では生スコア合算）
2. $companiesArray 構築（company > dept > team > member）
3. 各メンバーに $calcAggregates($m->id) の結果を付与
4. 【パーセンタイル計算】部署単位で生スコア → パーセンタイル変換
   - percentile_scores を付与
   - points.overall をパーセンタイル overall で上書き
5. 【偏差値計算】会社単位で points.overall から偏差値を計算
6. Inertia::render で companies 配列を渡す
```

---

### show() のデータフロー

```
1. 対象ユーザーの詳細計算（stage/type/size/difficulty の内訳ラベル・データ）
2. イベント詳細計算
3. 残業分布計算（5バケット: 〜1h/〜2h/〜3h/〜4h/4h〜）+ パーセンタイル用残業分集計
4. $computeUserPoints クロージャ（比較グループ全員の生総合ポイント用）
5. $computeUserCategoryScores クロージャ（6カテゴリ生スコア返却 / パーセンタイル用）
6. 比較グループ特定（同会社ユーザー or 当月アクティブユーザー）
7. グループ全員の 6カテゴリ生スコアを計算
8. 対象ユーザーのパーセンタイルと順位を計算 → percentile_scores / category_ranks / group_count
9. Inertia::render で全データを渡す
```

---

### フロントエンド（Index.vue）の構造

- **viewMode**: `'total'`（部署全体）/ `'by_role'`（役割ごと）トグル
- **総合ポイント表示**: `row.aggregates.points.overall`（= パーセンタイル合計 0〜600）
- **内容セル**: `row.aggregates.percentile_scores.{cat}` を各カテゴリ表示（0〜100pt）
- **残業セル**: `aggregates.overtime_minutes`（合計）/ `overtime_days_normal`（通常日数）/ `overtime_days_excess`（超過日数）
- **役割**: `row.aggregates` ではなく `row.assignment_name`（コントローラで `m.assignment->name` を付与）

---

### フロントエンド（Show.vue）の構造

- **AnalysisPanel コンポーネント**: 各カテゴリのパネル。`percentile`・`rank`・`group_count` を渡すとサマリーカードに表示される
- **レーダーチャート**: `percentile_scores` の 6カテゴリ値（0〜100）を使用。生スコアは使わない
- **合計ポイント表示**: `overallPoints = percentile_scores.overall`（0〜600）
- **計算方法モーダル**: `showCalcModal` フラグ。モーダル内に概要・詳細を日本語で記載済み

---

### CategoryRank.vue の構造

- `companies` / `selected_ym` を props で受け取る（Index.vue と同一データ）
- カテゴリ選択ボタン（7種）で表示カテゴリを切り替え
- 各カテゴリの **生スコア** で部署内ランキングを表示（パーセンタイルではなく生値）
- `aggregates.points.{cat}` / `aggregates.assigned.pages + self.pages`（総ページ）を使用

---

### 計算方法を変更する際の注意

1. **生スコアの計算式を変える** → `$calcAggregates`（index 用）と `$computeUserCategoryScores`（show 用）の**両方**を変更すること
2. **残業の閾値（180分）を変える** → `$calcAggregates` 内、`$computeUserCategoryScores` 内、`show()` の残業分布ブロックの**3箇所**を変更
3. **カテゴリを追加する** → `$pCats` 配列（index のパーセンタイルブロック）・`$showPCats`（show のパーセンタイルブロック）に追加。Vue の radar chart ラベルも更新
4. **比較母集団を変える** → index はパーセンタイルを「部署単位」で計算。show は「会社単位」。それぞれ独立しているため両方を確認
5. **係数の取得方法** → コード内で `\App\Models\Stage::find($sid)` 等を都度クエリしている。N+1 問題が出る場合は事前に `pluck()` でキャッシュすること（`EventItemType::pluck('coefficient', 'id')` のパターンが既存にある）

---

## 開発ワークフロー

- `php artisan` は必ずコンテナ内で実行
- 設定変更後は `php artisan config:clear && php artisan cache:clear` を実行
- フロントを変更したら `npm run build` を実行
- 署名検証が通らない場合: `APP_URL` と `TrustProxies`、`URL::forceRootUrl` を確認してコンテナ再起動・キャッシュクリア

**デバッグ:**
- `storage/logs/laravel.log` で `StartSession` / `URL::hasValidSignature` のログを確認
- 添付ファイルは `ls -l storage/app/public/attachments` で保存確認

---

## Migration / Seeder ルール

### デプロイ手順（さくらレンタルサーバー）

```bash
php artisan migrate   # スキーマ作成 + event_item_types/worktime_item_types の初期データ投入
php artisan db:seed   # マスターデータ投入（サンプルデータは自動スキップ）
npm run build         # フロントエンドビルド
php artisan config:clear && php artisan cache:clear
```

### DatabaseSeeder の構成

`database/seeders/DatabaseSeeder.php` は本番用マスターデータと開発用サンプルデータに分かれている。

**本番・開発共通（必ず実行）:**

| Seeder | テーブル | 内容 |
|---|---|---|
| `CreateSuperadminCompanySeeder` | companies | Superadmin 専用会社 |
| `CreateSuperadminSeeder` | users | Superadmin ユーザー（環境変数 `SUPERADMIN_EMAIL/PASSWORD`） |
| `CreateSuperadminTeamSeeder` | teams, team_user | Superadmin チーム |
| `CompanySeeder` | companies | 株式会社サン・ブレーン（code=`SUNBRAIN`） |
| `DepartmentSeeder` | departments | 情報出版(INFO)・製版(SEIHAN)・オンデマンド(ONDEMAND) |
| `AssignmentSeeder` | assignments | 各部署の役職（進行管理・オペレーター・校正・営業・そのほか） |
| `TeamSeeder` | teams | 会社全体チーム + 部署別チーム |
| `AiPresetsSeeder` | ai_presets | `config/ai_presets.php` から AI プリセット |
| `WorkItemTypesSeeder` | work_item_types | 作成・修正・校正・赤字照合・編集・確認・**その他**（7種） |
| `SizesSeeder` | sizes | 用紙サイズ A/B シリーズ + Web サイズ（13種） |
| `StagesSeeder` | stages | 初校〜校了（7段階） |
| `DifficultiesSeeder` | difficulties | 軽い・普通・重い・重大（4種） |
| `StatusesSeeder` | statuses | 依頼・進行中・完了（3種） |
| `StatusesTableSeeder` | statuses | 確認済み・セット済み・受信済み・受信済み + legacy upsert |
| `WorkItemPresetsSeeder` | work_item_presets | 作業項目プリセット 3種 |
| `OtherClientProjectSeeder` | clients, project_jobs | 「その他」クライアント・「その他」案件（システム共通） |

**開発環境専用（`$sampleData = false` で制御、本番は false のまま）:**

| Seeder | 内容 |
|---|---|
| `z_SampleAdminUserSeeder` | Admin サンプルユーザー (ito@test.com) |
| `z_SampleUsers22Seeder` | CSV から 22 名のサンプルユーザー |
| `z_SampleDiariesSeeder` | サンプル日報 |
| `z_ClientSeeder` | サンプル得意先（朝日デザイン等） |
| `z_ProjectJobsSeeder` | サンプル案件（クライアントに紐付く） |

開発環境でサンプルデータを入れる場合は `DatabaseSeeder.php` の `$sampleData = false` を `true` に変更してから実行する。

### マイグレーション内に初期データがあるテーブル

以下 2 テーブルは Seeder ではなく Migration で初期データが INSERT される。`php artisan migrate` を実行すれば自動で登録される。

| テーブル | Migration ファイル | 初期データ |
|---|---|---|
| `event_item_types` | `2026_03_19_154720_create_event_item_types_table.php` | 顧客訪問・打合せ(社内)・打合せ(顧客)・会議・外出・**そのほか**（6種） |
| `worktime_item_types` | `2026_03_20_000002_rename_overtime_to_worktime_item_types.php` | 残業(通常)・早退・超過残業（3種） |

### 「その他」レコードの設計

以下の「その他」はシステムが参照する重要なデフォルトレコード。**Seeder または Migration で事前登録されている**。

| テーブル | レコード | 登録方法 | 参照箇所 |
|---|---|---|---|
| `clients` | `name='その他', company_id=null` | `OtherClientProjectSeeder` | `EventController`（`otherClientId`）|
| `project_jobs` | `title='その他', client_id=上記` | `OtherClientProjectSeeder` | `EventController`（`otherProjectId`）|
| `work_item_types` | `slug='other', name='その他'` | `WorkItemTypesSeeder` | ワークロード計算・割当フォーム |
| `event_item_types` | `slug='other', name='そのほか'` | Migration | イベント作成フォーム |
| `assignments` | `code='other', name='そのほか'` | `AssignmentSeeder` | 各部署の役職選択肢 |

**重要:** `EventController` は `firstOrCreate` で「その他」クライアント・案件を実行時に作成するフォールバックを持つが、本番デプロイ後は Seeder で事前登録済みのレコードが参照される。

### Seeder の冪等性（べき等性）

全ての本番 Seeder は **何度実行しても同じ結果**になるよう設計されている:
- `firstOrCreate` / `updateOrCreate` / `updateOrInsert` / `upsert` を使用
- `insert` は使わない（重複エラーが起きるため）
- 新しい Seeder を追加する際は必ず上記のいずれかのメソッドを使うこと

### 新しいマスターテーブルを追加する際のルール

1. Migration でテーブルを作成する
2. 初期データが少量（10件以下）かつ不変ならば Migration 内で `DB::table(...)->insert([...])` してもよい
3. 初期データが多い・または後から追加される可能性があるなら Seeder に分離する
4. `DatabaseSeeder.php` の本番用セクションに追加し、依存するSeederの**後**に配置する
5. 「その他」相当のデフォルトレコードがある場合は `OtherClientProjectSeeder` に追記するか、専用 Seeder を作成する

---

## 詳細ドキュメント参照先

- `z_instructions/CONSOLIDATED_01_layout_and_ui.md` - UI ルール詳細
- `z_instructions/CONSOLIDATED_02_security_and_sessions.md` - セキュリティ・セッション
- `z_instructions/CONSOLIDATED_03_auth_and_cors.md` - 認証・CORS
- `z_instructions/CONSOLIDATED_04_ai_and_chat.md` - AI・チャット
- `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` - カレンダー・JobBox
- `z_instructions/CONSOLIDATED_06_messages_and_files.md` - メッセージ・ファイル
- `z_instructions/CONSOLIDATED_07_workload_and_handover.md` - ワークロード解析
- `z_instructions/CONSOLIDATED_08_attachment.md` - 添付ファイル詳細
- `z_instructions/attachment_guidelines.md` - 添付ファイルガイドライン

> `z_instructions/backups/` 配下のファイルは読み飛ばす。

---

## 日報（Diary）実装ルール

### タイムスタンプに関する注意（重要）

`DiaryController::store()` で日報を保存する際、**`created_at` / `updated_at` を明示的にセットしてはいけない**。
かつて `$diary->created_at = Carbon::parse($data['date'])->startOfDay()` が設定されており、常に 00:00:00 になるバグがあった。Laravel に任せて `now()` を使わせること。

```php
// NG: 時刻が強制的に 00:00:00 になる
$diary->created_at = Carbon::parse($data['date'])->startOfDay();

// OK: Laravel が now() を自動設定する
$diary->date = Carbon::parse($data['date'])->toDateString();
$diary->save(); // created_at / updated_at は触らない
```

### 既存日報チェックは `date` カラムで行う

`DiaryController::create()` で既存日報の存在チェックをする際は `whereDate('created_at', $date)` ではなく `where('date', $date)` を使うこと。
`created_at` は作成した実際の日時（昨日の日報を今日付けで作成した場合などにずれる）。

```php
// NG
$diary = Diary::where('user_id', $userId)->whereDate('created_at', $date)->first();

// OK
$diary = Diary::where('user_id', $userId)->where('date', $date)->first();
```

### Quill エディタへのコンテンツ外部セット

`@vueup/vue-quill` は `v-model` の外部変更だけでは内部状態が更新されない。
過去データ流用などでプログラムからコンテンツをセットする場合は、**`form.content` への代入** と **`editorInstance` への直接更新** の両方が必要。

```js
function applyPastContent(html) {
    form.content = html;         // Inertia form に反映
    content.value = html;        // ref にも反映
    if (editorInstance) {
        try {
            const delta = editorInstance.clipboard.convert(html);
            editorInstance.setContents(delta);  // Quill 内部状態を更新
        } catch (e) {
            editorInstance.root.innerHTML = html;
        }
    }
}
```

`editorInstance` は `@ready="handleEditorReady"` で取得したグローバル変数。

### 日付変更時の既存日報リダイレクト

Create.vue の日付セレクターが変わったとき、`router.visit` でサーバーの `create()` を再度呼ぶ。
`create()` は `where('date', $date)` で既存日報を検出し、あれば自動的に edit へリダイレクトする。

```js
watch(
    () => form.date,
    (newDate, oldDate) => {
        if (!newDate || newDate === oldDate) return;
        router.visit(route('diaries.create') + '?date=' + newDate, {
            preserveScroll: true,
        });
    },
);
```

### 日報 Show ページのレイアウト

`Diaries/Show.vue` および `Diaries/Interactions/Show.vue` は**2カラムグリッド**レイアウト。
- 左列: 日報本文（`max-h-52 overflow-y-auto`）＋ コメント ＋ ボタン
- 右列: TimelineDiary（当日の予定）
- タイトル下に**勤務情報バー**（勤務形態・開始・終了・残業時間）

勤務情報バーのデータは `DiaryController::show()` および `DiaryInteractionController::show()` で
`WorkRecord::with('worktype')` を取得して `workRecord` prop として渡す。

`Interactions/Show.vue` のみ超過残業（240分以上）で赤字表示。`Diaries/Show.vue` は赤字なし。

---

## TimelineDiary コンポーネント ルール

**ファイル:** `resources/js/Components/TimelineDiary.vue`

### 水平スクロールしない実装（重要）

TimelineDiary は `ResizeObserver` でラッパー幅をリアクティブに取得し、
`usedPxPerMin = containerWidth / windowMinutes` で自動フィットする。
**`minWidth` をピクセルで強制してはいけない**（ビューポートより大きくなり水平スクロールが発生する）。

```js
// 正しい実装
const containerWidth = ref(0);
onMounted(() => {
    containerWidth.value = scrollWrapperRef.value.clientWidth;
    resizeObserver = new ResizeObserver((entries) => {
        containerWidth.value = entries[0]?.contentRect.width ?? 0;
    });
    resizeObserver.observe(scrollWrapperRef.value);
});
const usedPxPerMin = computed(() => {
    if (containerWidth.value > 0 && windowMinutes.value > 0) {
        return containerWidth.value / windowMinutes.value;
    }
    return pxPerMinuteRef.value;
});
```

### startHour / endHour を勤務記録から動的に決定

各 Show ページで `workRecord.start_time` / `workRecord.end_time` から表示範囲を計算して渡す。

```js
const timelineStartHour = computed(() => {
    const t = props.workRecord?.start_time; // "HH:MM"
    if (!t) return 8;
    return Math.max(0, Math.floor(parseInt(t.split(':')[0], 10)) - 1);
});
const timelineEndHour = computed(() => {
    const t = props.workRecord?.end_time;
    if (!t) return 20;
    const h = parseInt(t.split(':')[0], 10);
    const m = parseInt(t.split(':')[1] ?? '0', 10);
    return Math.min(24, h + (m > 0 ? 2 : 1));
});
```

### props 変化への追随

`startHour` / `endHour` props が親から遅れて渡される場合があるため、`watch` を設ける。

```js
watch(() => props.startHour, (v) => { startHourRef.value = v; });
watch(() => props.endHour,   (v) => { endHourRef.value   = v; });
```

---

## Coordinator 割当フロー ルール

### 案件選択中間ページ（SelectProject）

Coordinator が新規割当を作成する際は、直接フォームに遷移せず中間ページを経由する。

- **ルート:** `GET coordinator/project_jobs/assignment-select` → `coordinator.project_jobs.assignment_select`
- **コントローラー:** `ProjectJobAssignmentsController::selectProject()`
  - `ProjectJob::where('user_id', $user->id)` でオーナーである案件のみ取得
  - クライアントで絞り込み → 案件選択 → assignments.create へ遷移
- **Vue ページ:** `Pages/Coordinator/ProjectJobs/JobAssign/SelectProject.vue`

### ルート定義の順序（必須）

`project_jobs/{projectJob}` のような**パラメータ化ルートより前に静的ルートを定義**すること。
後ろに置くと文字列（例: `assignment-select`）がパラメータとして捕捉されて 404 になる。

```php
// OK: 静的ルートが先
Route::get('project_jobs/past-assignments',  [...'pastData'])->name('...');
Route::get('project_jobs/assignment-select', [...'selectProject'])->name('...');
Route::get('project_jobs/{projectJob}',      [...'show'])->name('...');  // 後
```

### 「過去データから流用」モーダルの実装パターン

AssignmentForm（Edit.vue）や Diary（Create.vue）で共通して使うパターン。

**フロントエンド:**
1. 「過去データから流用」ボタン → モーダル表示
2. `fetch` API で `?mode=date|project` などのフィルターを付けてデータ取得
3. テーブルで行クリック → `selectRecord(rec)` で親フォームに値を注入

**AssignmentForm への注入:**
`assignments` prop と `:key="formKey"` を使い、formKey をインクリメントしてフォームを再マウント。

```js
const formKey = ref(0);
function selectRecord(rec) {
    formAssignments.value = [{ ...rec, id: null, amounts: null }];
    formKey.value += 1;  // AssignmentForm を再マウントして初期値を反映
    closeModal();
}
```

**バックエンド (`pastData` メソッド):**
- `whereHas('projectJob', fn => q->where('user_id', $user->id))` でオーナー所有案件のみ
- `date` モード: `desired_end_date` 範囲フィルター
- `project` モード: `project_job_id` フィルター + 案件一覧を返す
