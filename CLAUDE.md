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
- Job の完了操作は `events.complete` 等の API に集約し、サーバ側で状態更新
- 日付: サーバは UTC、フロントは JST で変換に注意

**サーバ側:**
- `EventController::store`: `job_id` の有無に応じて `ProjectJobAssignment` を参照し通知を作成
- `CalendarController/index`: events/diaries/jobs をマージした props を返す

---

## AI / チャット ルール

- OpenAI キーは環境変数で管理 (`OPENAI_API_KEY`)
- AI が生成する Markdown/HTML は必ず `DOMPurify` / `HTMLPurifier` でサニタイズ
- チャットエクスポートは `storage/app/exports` に出力し、専用ルートでダウンロード
- `BotController.php` (app/Http/Controllers/Bot/): `totalCharsIncluded` の初期化漏れに注意
- ファイルメタは最小情報のみ返す

---

## ワークロード解析

- 設定 (stages/sizes/types/difficulties) に `coefficient` を持たせて `estimated_hours` と掛け合わせて集計
- 設定ページは XHR 保存（JSON 応答）+ トースト通知
- ルーティング: 静的な `settings` をパラメタライズされたルートより前に定義すること

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
