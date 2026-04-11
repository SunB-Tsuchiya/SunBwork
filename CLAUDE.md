# CLAUDE.md - SunBWork プロジェクト ルール

このファイルはすべての会話の開始時に必ず参照すること。コードの作成・修正はここに記載されたルールに従う。

---

## プロジェクト基本情報

- **スタック:** Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS（SPA構成）
- **開発体制:** 単独開発
- **本番環境:** さくらレンタルサーバー（`https://sun-brain.co.jp/members`）
- **APP_NAME:** `SB`（ブラウザタブ「ページ名 - SB」で表示。さくら本番 .env も同様）

---

## 作業ルール

- **作業前に必ず関連コードを読む。** 既存の実装を確認してから変更・追加する
- Vue / JS ファイルを変更したら必ず最後に `npm run build` を実行（許可済み）
- `npm run build` はプロジェクトルート（`/home/tchirosb/SunBWork`）で実行
- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- Docker が使えない場合はホスト上の npm（node v24, npm v11）で直接ビルド
- 設定変更後: `php artisan config:clear && php artisan cache:clear`
- storage パーミッションエラー時: `docker compose exec -u root laravel bash -lc "chmod -R 777 storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache"`
- EACCES エラー時（Docker ビルド後に public/build が root 所有になる）: `sudo chown -R $USER:$USER public/build/ && sudo chmod -R 755 public/build/assets`

**「git にアップ」「さくらにデプロイ」を求められたとき:**
→ `z_instructions/DEPLOY_SAKURA.md` の手順に従う
⚠️ **VITE_APP_BASE_PATH の切り替えを必ず行い、コミット後にローカル用（空）に戻すこと**

---

## プロジェクト概要

**SunBWork** は Laravel + Inertia.js (Vue 3) + Vite + Tailwind CSS で構築された SPA 型の業務管理システム。

- ロール: SuperAdmin / Admin / Coordinator / Leader / User
- 機能: ProjectJob 管理・割当（JobBox/MyJobBox）、カレンダー、チャット、日報、ワークロード解析
- DB: MySQL / Sanctum + Cookie SPA 認証 / Laravel Echo (WebSocket)

**主要フォルダ:**

```
app/Http/Controllers/
  Admin/  Coordinator/  Leader/  ProjectJobs/  User/  Bot/  Chat/  Diaries/
app/Models/
database/migrations/          # backups/ は読み飛ばす
resources/js/
  Pages/                      # Inertia ページ（ロール別サブディレクトリ）
  Components/                 # 大文字始まり = プロジェクト固有
  components/ui/              # 小文字始まり = shadcn/ui 系
  layouts/AppLayout.vue       # メインレイアウト（全ページ共通）
  ziggy.js                    # Ziggy ルート定義
routes/web.php                # SPA ルート（api.php には置かない）
z_instructions/               # 詳細ドキュメント（backups/ は読み飛ばす）
```

---

## UI / レイアウト ルール（最優先）

AppLayout は `py-12 > max-w-7xl` を内部に提供済み。ページ側はデフォルトスロットに直接カードを置く:

```vue
<AppLayout title="ページタイトル">
  <template #header><h2>見出し</h2></template>
  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

**NG:** `<main>` タグ、`py-2/py-12` の重複ラップ、`mx-auto max-w-7xl` の重複ラップ、`shadow-xl sm:rounded-lg`

**AppLayout スロット:** `#header` / `#headerExtras` / `#tabs` / デフォルト

**AppLayout が provide する値:** `authUser`（ログインユーザー）/ `user`（ページの user prop）

**ToastUnified は AppLayout 内にグローバル配置済み。各ページで重複させない。**

**ロール別カラー:** SuperAdmin=黄 / Admin=赤 / Leader=オレンジ / Coordinator=緑 / User=青

**テーブル:** `min-w-full divide-y divide-gray-200`、ヘッダ `bg-gray-50`、行 `hover:bg-gray-50`

**Ziggy の route() 使用時はパラメータ名をオブジェクトで渡す:**

```js
route('coordinator.project_jobs.show', { projectJob: job.id });
```

---

## ロール共有ページのルーティングルール

AppLayout.vue の `currentRouteContext` computed はルート名プレフィックスでタブを決定:

- `superadmin.*` → superadmin / `admin.*` → admin
- `leader.*` or `workload_setting.*` → leader
- `coordinator.*` or `project_jobs.*` → coordinator
- それ以外 → 必ず `'user'` を返す（user_role にフォールバックしてはいけない）

複数ロール共有ページでは `routePrefix` computed（Vue）と `routePrefix()` メソッド（PHP）でロール別にルートを解決する。

**Clients テーブルの注意:** DBカラムは `notes`。フォームフィールド名 `detail` との乖離に注意。

---

## 権限・ロール設計ルール

**ロール階層:** SuperAdmin > Admin > Leader / Coordinator > **Clerk** > User

**Clerk ロール（2026-04-02 追加）:**
- `user_role = 'clerk'` / バッジカラー: 紫（`bg-purple-100 text-purple-800`）/ 表示名: 「事務・経理」
- Coordinator と同等以上の権限（経理・事務機能へのアクセス）
- `AdminUserController` のバリデーション `in:admin,leader,coordinator,clerk,user` に含まれる
- CSV 一括登録でも `clerk` は有効（日本語: 「クラーク」で自動変換）
- `UserTable.vue` / `Admin/Users/Index.vue` の `getAssignmentText()` / `getAssignmentBadgeClass()` 両方に定義が必要

**Admin 権限フラグ（`admin_permissions`）:** `company_management` / `user_management` / `team_management` / `diary_management` / `client_management` / `workload_analysis` / `worktype_setting` / `work_record_management`

**Leader 権限フラグ（`leader_permissions`）:** `client_management` / `diary_management` / `workload_analysis` / `workload_setting` / `work_record_management` / `dispatch_management` / `project_job_overview`

**権限チェック Trait:** `ChecksAdminPermission` / `ChecksLeaderPermission`。レコードなしは全権限 ON。

**Leader スコープ:** 部署リーダー（`team_type='department'`）→ 自部署全体 / ユニットリーダー → 自チームのみ / サブリーダー（`team_sub_leaders` ピボット）→ 担当チームのみ

**タブ表示制御:** `HandleInertiaRequests` で `auth.adminPermissions` / `auth.leaderPermissions` を共有。`perm === null`（レコードなし）は全フラグ ON として扱う。

**チーム管理・権限管理ページはフラグ制御対象外。**

**SuperAdmin が Admin 権限設定ページにアクセスする場合は `'admin'` プレフィックスを返す（`'superadmin'` ではない）。**

---

## project_job_assignments 統合テーブル設計（重要）

### 設計概要

`project_job_assignments` が **JobBox・MyJobBox 両方の唯一のテーブル**。
`project_job_assignment_by_myself` テーブルは migration 500001 で統合・削除済み。

**区別ルール:**

- `sender_id = user_id` → **自己割当**（旧 project_job_assignment_by_myself）
- `sender_id ≠ user_id` or NULL → **Coordinator 割当**

**スコープ（ProjectJobAssignment モデル）:**

```php
scopeSelfAssigned()        // whereColumn('sender_id', 'user_id')
scopeCoordinatorAssigned() // sender_id != user_id OR sender_id IS NULL
```

**統合時に追加されたカラム:** `completed` / `scheduled` / `scheduled_at` / `read_at` / `start_time`

**`desired_start_date` は存在しない（意図的に除外）。**
**`WorkloadAnalyzerController` など `project_job_assignments` に対して期間フィルターをかける場合は `desired_end_date` を使うこと（`desired_start_date` を参照すると Column not found エラー）。**

### ProjectJobAssignmentByMyself モデル

`ProjectJobAssignment` を継承したエイリアス（後方互換のため残存）。
`$table` は親クラスの `'project_job_assignments'` を使用（**旧テーブルは参照しない**）。
`booted()` で `whereColumn('sender_id', 'user_id')` のグローバルスコープを自動適用。
**新コードでは `ProjectJobAssignment::selfAssigned()` スコープを使うこと。**

> **migration 2026_04_03_300001** により、旧テーブル `project_job_assignment_by_myself` の
> 全データ（48件）を `project_job_assignments` へ移行済み。`sender_id = user_id` が自己割当マーカー。
> 旧テーブルはバックアップとして残存しているが、アプリからは参照しない。
> `events.project_job_assignment_id` も新テーブルのIDに更新済み。

### JobBox vs MyJobBox の違い

| 機能     | データソース                                                   | 完了ルート                      |
| -------- | -------------------------------------------------------------- | ------------------------------- |
| JobBox   | `job_assignment_messages` を `project_job_assignments` に JOIN | `jobbox.assignments.complete`   |
| MyJobBox | `project_job_assignments` 直接（`selfAssigned()` スコープ）    | `myjobbox.assignments.complete` |

`events.complete` ルートは `project_job_assignments.completed` のみ更新。

### MyJobBox インデックス仕様

- **Inertia props のローカルコピー:** `const toPlain = (arr) => arr.map(item => ({...item}))` で各要素を shallow copy（要素レベルで `{...item}` が必要。`[...arr]` では各要素が proxy のまま）
- **完了後のローカル更新:** `localAssignments.value.splice(idx, 1, {...obj, completed: true})` （`push/代入` では Vue リアクティビティがトリガーされない）
- **期間フィルター:** `COALESCE(desired_end_date, created_at)` でデフォルト当月
- **行クリック:** `events` テーブルは `project_job_assignment_by_myself_id` カラムを持たない。直接 `user.myjobbox.show` へ遷移する
- **ステータス優先順:** `completed` boolean フラグ → `status_model.key` → `scheduled` フラグ

### ステータス値とバッジカラー

| ステータス        | バッジ                          |
| ----------------- | ------------------------------- |
| 完了              | `bg-yellow-100 text-yellow-800` |
| セット済み        | `bg-blue-100 text-blue-800`     |
| 確認済み          | `bg-green-100 text-green-800`   |
| 進行中 / 受信済み | `bg-indigo-100 text-indigo-800` |
| その他            | `bg-gray-100 text-gray-700`     |

---

## セキュリティ・認証ルール

- SPA 向けエンドポイントは必ず `web` ミドルウェア（`routes/web.php`）に置く。`routes/api.php` には置かない
- HTML/Markdown は `HTMLPurifier`（サーバ）/ `DOMPurify`（フロント）でサニタイズ。`App\Services\HtmlSanitizer` を経由すること
- ファイルメタは最小情報のみ返す（`original_name`, `mime`, `size`, `path`, `url`）

---

## 添付ファイルルール

**保存先:** `storage/app/public/attachments/` 。命名: `<uuid>_<original_name>`（`..`, `/`, `\` は `_` に置換）

**配信:** ストリーミングエンドポイント経由（`/chat/attachments` / `/bot/attachments` / `/attachments/signed`）

**重要ファイル:** `AttachmentService.php`（保存・サムネイル）/ `AttachmentController.php`（配信）/ `Helpers/attachment.js`（フロント URL 正規化）

---

## カレンダー・イベント ルール

- FullCalendar には Vue の reactive Proxy をそのまま渡さない → `structuredClone` などで plain オブジェクトを渡す
- 日付: サーバは UTC、フロントは JST で変換に注意
- `EventController::complete()`: `project_job_assignments.completed` のみ更新
- Events/Show.vue パンくずバー: `events.project_job_assignment_id` は `project_job_assignments` への FK

---

## CSV アップロード実装パターン

ルート定義はリソースルートより前に: `csv/upload` / `csv/preview` / `csv/store` / `csv/sample`

Vue ページ: `CsvUpload.vue`（ファイル選択）→ `CsvPreview.vue`（確認後 store へ POST）

サンプル CSV は BOM 付き UTF-8 で返す（`"\xEF\xBB\xBF"` プレフィックス）。

---

## AI / チャット ルール

- OpenAI キーは環境変数 `OPENAI_API_KEY` で管理
- AI 生成コンテンツは必ず DOMPurify / HTMLPurifier でサニタイズ
- `BotController.php`: `totalCharsIncluded` の初期化漏れに注意

---

## ワークロード解析

**関連ファイル:** `WorkloadAnalyzerController.php`（全ロジック）/ `AnalysisPanel.vue`（カテゴリ別パネル）/ `WorkloadAnalyzer/` 配下の Index / Show / CategoryRank / Settings

**ルーティング:** 静的ルートをパラメータ化ルートより前に配置。`category-rank` と `index` は同じ `index()` メソッドを使用し、ルート名で Inertia コンポーネントを切り替える。

### ポイント計算の仕組み

**生スコア:**

```
ステージ/サイズ/種別 = Σ (ページ × 係数 × 難易度係数)
難易度 = Σ (ページ × 難易度係数)
イベント = Σ (時間[h] × イベント種別係数)
残業(通常, ≤180分/日) = 合計分 × 通常残業係数
残業(超過, >180分/日) = 合計分 × 超過残業係数
```

**職種グループ別パーセンタイル変換（0〜100）:**

- 同部署内で `assignment_name` ごとにグループ化
- グループ N ≥ 3 → 同職種内でパーセンタイル計算（`comparison_level = 'role'`）
- グループ N < 3 → 部署全体でフォールバック（`comparison_level = 'department'`）
- パーセンタイル式: `(N - (above + (tied+1)/2)) / (N-1) × 100`（N=1 は 100 固定）

**総合ポイント（0〜600）:** 6カテゴリのパーセンタイル合算

**偏差値（参考値）:** 同会社全体を母集団として `50 + 10z` で計算

**計算変更時の注意:** 生スコア計算式は `$calcAggregates`（index用）と `$computeUserCategoryScores`（show用）の**両方**を変更。残業閾値（180分）は3箇所に存在。

### 作業種別・サイズのグループ分類

`work_item_types` と `sizes` に `group` カラムあり。コントローラーで取得する際は **`group` カラムを必ず含めること**（`->get(['id','name'])` のように列指定する場合も同様）。

`AssignmentForm.vue`: `_type_filter`（作業種別）と `_medium_filter`（サイズ: paper/digital）でグループフィルタリング。

---

## CI（GitHub Actions）ルール

- `.github/workflows/lint.yml`: PHP Pint + Prettier + ESLint
- `.github/workflows/tests.yml`: Pest テスト（SQLite使用）

**MySQL固有マイグレーションには SQLite ガード必須:**

```php
if (DB::getDriverName() === 'sqlite') return;
```

`MODIFY`・`DROP FOREIGN KEY`・`AUTO_INCREMENT`・`information_schema` 参照を含むマイグレーションに追加。

**スキップ中のテスト:** `routes/settings.php` / `routes/auth.php` 未登録、Fortify emailVerification 無効、`project_schedule_assignments` / `password_reset_tokens` テーブル未実行、DiaryFactory 未作成。

---

## さくらレンタルサーバー 設定と制約

**デプロイ手順:** → `z_instructions/DEPLOY_SAKURA.md` を参照

**サーバー構成:**
- `~/SunBWork/` — Laravel ルート / `~/www/members/` — 公開ディレクトリ
- `~/www/members/build/` — `~/SunBWork/public/build/` へのシンボリックリンク

**本番 .env（重要設定）:**
`APP_URL` / `ASSET_URL` = `https://sun-brain.co.jp/members` / `VITE_APP_BASE_PATH=/members` / `APP_DEBUG=false`

**制約:** `nano` 不可（`vi` を使う）/ `.htaccess` に `php_flag`/`php_value` 不可 / Reverb 不可 / さくら上の `sed -i` は BSD版のため `-i ''` が必要

**ローカル .env（重要設定）:**
`APP_URL=http://localhost:8000` / `VITE_APP_BASE_PATH=`（空）/ `SESSION_SECURE_COOKIE=false` / `SESSION_SAME_SITE=lax`

**⚠️ さくら本番でのコーディング必須ルール:**

① **ナビゲーションは必ず `route()` を使う** — JS 内でパスをハードコードすると `/members` ベースパスで 404 になる
```js
// NG
window.location.href = `/events/${id}`;
// OK
router.get(route('events.show', { event: id }));  // Inertia 遷移推奨
window.location.href = route('events.show', { event: id });  // try/catch 内も同様
```

② **CSRF トークンは meta tag から取得する** — さくらでは `XSRF-TOKEN` クッキーが発行されず 419 エラーになる
```js
// NG
const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
// OK
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
```

③ **`php artisan migrate` を忘れると本番が壊れる** — ローカルは正常でも prop が空になるなど無音で壊れる（実績あり）

---

## Migration / Seeder ルール

- 全本番 Seeder は冪等性必須: `firstOrCreate` / `updateOrCreate` / `upsert`（`insert` は使わない）
- `event_item_types` と `worktime_item_types` は Migration 内で初期データ INSERT
- **「その他」レコード:** `clients`（name='その他'）/ `project_jobs`（title='その他'）/ `work_item_types`（slug='other'）/ `event_item_types`（slug='other'）/ `assignments`（code='other'）は `OtherClientProjectSeeder` または Migration で事前登録済み
- 開発サンプルデータ: `DatabaseSeeder.php` の `$sampleData = true` で有効化

**本番必須 Seeder（主要）:**
`CompanySeeder` / `DepartmentSeeder` / `AssignmentSeeder` / `TeamSeeder` / `WorkItemTypesSeeder` / `SizesSeeder` / `StagesSeeder` / `DifficultiesSeeder` / `StatusesSeeder` / `OtherClientProjectSeeder` 他

---

## 日報（Diary）実装ルール

- `DiaryController::store()` で `created_at` / `updated_at` を明示的にセットしない（Laravel に任せる）
- 既存日報チェックは `whereDate('created_at', $date)` ではなく `where('date', $date)` を使う
- Quill エディタへの外部コンテンツセット: `form.content` への代入と `editorInstance.setContents(delta)` の両方が必要
- 日報 Show ページ: 2カラムグリッド（本文＋コメント / TimelineDiary）+ 勤務情報バー

---

## TimelineDiary コンポーネント ルール

**ファイル:** `resources/js/Components/TimelineDiary.vue`

- `ResizeObserver` でラッパー幅をリアクティブ取得し `usedPxPerMin = containerWidth / windowMinutes` で自動フィット。`minWidth` をピクセルで強制しない（水平スクロールが発生する）
- `startHour` / `endHour` は `workRecord.start_time` / `end_time` から動的に決定（親から遅れて渡されるため `watch` で追随）
- 夜勤モード: `defaultWorktype.start_time >= 16:00` の場合 `slotMaxTime: '30:00:00'`（翌6時）

---

## Coordinator 割当フロー ルール

**案件選択中間ページ:** `coordinator.project_jobs.assignment_select` → `SelectProject.vue` → assignments.create へ遷移

**ルート定義順序:** パラメータ化ルート（`{projectJob}`）より前に静的ルートを置く。

**AssignmentForm.vue:** `mode='coordinator'`（デフォルト）と `mode='user'`（自己登録）で動作切り替え。

- Coordinator 保存は必ず `router.post()`（`inertiaFetch` では 419 CSRF エラー）
- `desired_end_date` は締め切り日。`desired_start_date` は `project_job_assignments` に存在しない（削除済み）

**ProjectJobs Show ページ:** タイトル行にボタン並置 / スケジュール・メンバーをインライン表示（モーダル廃止）

**案件カレンダー:** `coordinator.project_jobs.calendar` → `CalendarAll.vue` / 12色パレット自動割り当て / 「完了済み非表示」チェック付き

**ProjectJob 登録時の案件名重複チェック:**
- `ProjectJobController::checkDuplicate()` — 同一 `client_id` スコープで `normalizeTitle()` 適用後に比較、類似案件を JSON 返却
- `normalizeTitle()` — `mb_convert_kana 'as'`（全角→半角）+ スペース/`・/-/_` 除去 + `mb_strtolower`
- ルート: `POST coordinator/project_jobs/check-duplicate` → `coordinator.project_jobs.check_duplicate`
- フロント: `Create.vue` の `submit()` を `async` 化。保存前に自動チェックし、類似案件があれば警告モーダルを表示
- モーダルの選択肢: **「それでも登録する」**（オレンジ、任意登録可）/ **「閉じる（タイトルを変更する）」**（灰色、ブロック）
- クライアント重複チェック（`clients.check_duplicate`）と違い、**こちらは警告のみで強制ブロックしない**
- ルートは静的ルートとして `project_jobs.store` より前・パラメータ化ルートより前に配置すること

---

## Admin/Users/Edit フォーム仕様

Create.vue と同等のフルフォーム。フィールド: name / email / password / company_id / department_id / assignment_id / user_role / employment_type / position_title_id。

company_id → department_id → assignment_id のカスケードリセットあり。`Admin/UserController::edit()` で companies（部署・担当付き）と positionTitles を渡す。

---

## ユーザー設定・カレンダー勤務日程ルール

**テーブル:**

- `user_settings` — デフォルト勤務形態（`worktype_id`）・カレンダー表示設定
- `user_monthly_schedules` — 日ごと上書き（`year_month` + `schedule` JSON: `{"01": 2, "15": 3}`）

**解決優先順:** `user_monthly_schedules` > `user_settings.worktype_id` > 一覧の先頭

`worktype_id` 変更時は `user_monthly_schedules` を全削除（日次設定リセット）。

**カレンダー連動:** 勤務形態名をヘッダーに表示 / 始業前グレー背景 / 夜勤モード / 初期スクロールは1時間前 / `scrollTimeReset: false`

**依存マイグレーション:** `user_settings` / `user_monthly_schedules` の2テーブルが未実行だとカレンダーの勤務形態セレクト・シフト名表示が無音で壊れる（ローカルは正常）。`php artisan migrate` を必ず実行。問題発生時は `storage/logs/laravel.log` の `CalendarController` ログを確認。

---

## 雇用形態管理（派遣・業務委託）ルール

**テーブル:** `users.employment_type`（regular/contract/dispatch/outsource）/ `user_employment_settings`（日報義務フラグ上書き）/ `dispatch_profiles`（派遣会社情報）

**日報義務判定:** `user_employment_settings` レコードあり → そのフラグ / なし → regular/contract は必須、dispatch/outsource は任意

**User モデルメソッド:** `isDiaryRequired()` / `employmentTypeLabel()` / `employmentSetting()` / `dispatchProfile()`

**AssignmentForm:** dispatch/outsource ユーザー選択時にオレンジ警告ボックスを表示。コントローラーの members 配列に `employment_type` / `employment_type_label` を含めること。

**CSV 登録:** 7列目が `employment_type`（空欄時は `regular`）。日本語表記→英語キーに自動変換。

**dispatch_profiles:** dispatch/outsource のみ保存。regular/contract に変更時はレコード削除。

---

## ProjectJob 共同管理（2026-03-30 実装）

**ピボットテーブル:** `project_job_coordinators`（`project_job_id`, `user_id`, UNIQUE）

**代表（リーダー）:** `project_jobs.user_id` / **サブCo:** ピボットに複数登録可

**候補者条件:** `user_role = 'coordinator'` **または** `assignments.code = 'shinko'`（進行管理）→ SuperAdmin/Admin/Leader でも担当が進行管理なら選出対象

**ProjectJob モデル:** `coordinators()` → `belongsToMany(User, 'project_job_coordinators')`

**ProjectJobController:**
- `index()` — `user_id = me` OR ピボット登録済みの案件を表示
- `store()/update()` — `sub_coordinator_ids` を受け取り sync（リーダー自身は除外）
- `complete()` — `isJobCoordinator()` でリーダー・サブCo両方に許可

**`coordinatorCandidates()` ヘルパー:** `where('user_role','coordinator')->orWhereHas('assignment', code='shinko')`

---

## Coordinator タブメニュー構成（2026-03-30 変更）

クライアント管理 → 案件一覧 → ジョブ一覧 → 案件カレンダー

**クライアント管理:** Coordinator / Leader 両方からアクセス可（`ClientController` 共用、`routePrefix()` でロール別解決）。

**クライアント削除:** 案件あり → 削除不可。`Edit.vue` の2ステップモーダルで統合先を選択 → `router.post(clients.merge)` で `project_jobs.client_id` / `job_requests.client_id` を一括更新後に元レコード削除。

**重複チェック（`checkDuplicate`）:** 登録前に `normalizeClientName()`（全角→半角・法人格除去・小文字化）で比較。重複あれば**強制ブロック**（「それでも登録」ボタンなし）。ルート: `{admin|leader|coordinator}.clients.check_duplicate`。

**CoordinatorMiddleware:** Leader は `/coordinator/*` にアクセス不可（`isLeader()` を allow-list から除外済み）。

---

## Leader 案件総覧（2026-04-02 実装）

**概要:** Leader が自部署に関係する全案件を読み取り専用で閲覧できる機能。Coordinator の案件一覧（自分が担当のもののみ）とは異なり、部署全員の案件が対象。

**ルート:** `leader/project-jobs` プレフィックス（Coordinator の `coordinator/project-jobs` と混在しない）
- `GET leader/project-jobs` → `leader.project_jobs.index`
- `GET leader/project-jobs/{projectJob}` → `leader.project_jobs.show`
- **コントローラ:** `app/Http/Controllers/Leader/ProjectJobController.php`

**部署フィルター:** `whereHas('user', dept_id)` OR `whereHas('coordinators', dept_id)` — オーナーもサブCoも対象

**タブ表示条件:** `(isDepartmentLeader || isAdminOrAbove) && can('project_job_overview')`
- `isAdminOrAbove` = `['admin','superadmin'].includes(user.user_role)` — Admin/SuperAdmin も /leader/* ルート訪問時に表示される
- `isDepartmentLeader` のみでは Admin/SuperAdmin が表示されないため両方必要

**Vueページ:**
- `resources/js/Pages/Leader/ProjectJobs/Index.vue` — 月別グループ、担当Co列あり、行クリックで詳細へ
- `resources/js/Pages/Leader/ProjectJobs/Show.vue` — 読み取り専用詳細（編集・削除・完了ボタンなし）

**権限管理:** `leader_permissions.project_job_overview`（`boolean default true`）
- Migration: `2026_04_02_200001_add_project_job_overview_to_leader_permissions.php`
- `Admin/LeaderPermissions/Edit.vue` でトグル可能（ラベル: 「案件総覧（部署リーダーのみ有効）」）
- Admin/SuperAdmin は `leaderPermissions === null` → `can()` が全 true → 常に表示

---

## Leader ユーザー管理タブ（2026-03-30 変更）

- `LeaderNavigationTabs.vue`: `v-if="isDepartmentLeader && can('user_management')"` → `v-if="isDepartmentLeader"` に変更
- `UserManagementController`: 全メソッドから `requireLeaderPermission('user_management')` を削除（`getDeptTeam()` が認可ゲート）
- 部署リーダーであれば権限フラグ不問でタブ最左端に表示・アクセス可
- ユーザー一覧は `department_id` で自部署に絞り込み済み（変更なし）

---

## ProjectJob 完了/未完了 機能（2026-04-02 実装、2026-04-09 拡張）

- `complete()`: `completed = true` で保存
- `uncomplete()`: `completed = false` で保存（2026-04-02 追加）
- ルート名: `coordinator.project_jobs.complete` / `coordinator.project_jobs.uncomplete`
  - **⚠️ 必ず `coordinator.` プレフィックスを付けること。** `project_jobs.complete` では動作しない（404）
- `Show.vue`: 完了後は `coordinator.project_jobs.index` にリダイレクト
- `Show.vue`: 完了済み案件では「編集」「ジョブ割り当て」ボタンをグレーアウト＆`disabled`
- `Show.vue`: 完了済みバッジ + 「未完了に戻す」ボタン（オレンジ）を表示（完了時のみ）
- `Edit.vue`: 完了済みバッジ + 「未完了にする」ボタン実装済み（完了時のみ表示）
- `Edit.vue`: `jobcode` フィールドに `required` 属性なし（空欄で保存可）

### 完了済み案件のジョブ作成ブロック

- `ProjectJobAssignmentsController::create()` / `store()` の両方で `completed` チェックを最初に行う
  - `create()`: 完了済みなら `Show` にリダイレクト + `error` フラッシュメッセージ
  - `store()`: 完了済みなら 422 JSON エラーを返す
- `User\ProjectJobController::projectsJson()`: 完了済み案件を除外（`where('completed', false)`）
  - 「ジョブ作成（進行表から）」モーダルと `MyJobBox` モーダルに影響
- `EventController::createJob()`: 完了済み案件を除外（`->filter(fn($j) => !$j->completed)`）
  - `events/create-job` ページのプロジェクト選択リストに影響

---

## ProgressSheet（進行管理表）実装ルール（2026-04-03 追加）

**関連ファイル:**
- `app/Http/Controllers/Coordinator/ProgressSheetController.php`
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`
- `resources/js/Components/ProgressTable.vue`
- `resources/js/Components/ProgressCell.vue`
- `resources/js/Pages/User/ProjectJobs/Show.vue`（User向け閲覧）

### canEdit 権限チェック

`canEdit()` は以下のいずれかで true:
- 案件オーナー（`project_jobs.user_id === auth user`）
- サブコーディネーター（`project_job_coordinators` ピボット登録）
- Admin/SuperAdmin
- **シート作成者（`progress_sheets.created_by === auth user`）** ← テンプレート参照で作成された場合もこれが必要

```php
private function canEdit(User $user, ProjectJob $projectJob, ?ProgressSheet $sheet = null): bool
{
    $isOwner   = $projectJob->user_id === $user->id;
    $isSub     = $projectJob->coordinators()->where('users.id', $user->id)->exists();
    $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
    $isCreator = $sheet && $sheet->created_by === $user->id;
    return $isOwner || $isSub || $isAdmin || $isCreator;
}
```

`show()` / `update()` / `destroy()` / `registerAsTemplate()` / `linkJob()` 全てに `$sheet` を渡すこと。

### jobLinkOnly パターン（User向け読み取り専用 + ジョブリンク操作のみ）

`canEdit=false` かつ `jobLinkOnly=true` の組み合わせ:
- `ProgressCell`: チェックボックス・日付・ユーザー・テキスト等は全て読み取り表示
- `ProgressCell`: joblink セルのみ `canEdit || jobLinkOnly` で登録・詳細ボタンを表示
- `ProgressTable`: `jobLinkOnly` prop を受け取り各 `ProgressCell` に渡す

### User 案件確認タブ（2026-04-03 実装）

- タブ: `UserNavigationTabs.vue` に「案件確認」を最左端に追加
- ルート: `GET /user/project-jobs` → `user.project_jobs.index` / `GET /user/project-jobs/{id}` → `user.project_jobs.show`
- コントローラ: `app/Http/Controllers/User/ProjectJobController.php`
  - `index()`: `project_job_assignments` で `user_id` or `sender_id` が自分の案件を一覧
  - `show()`: 詳細表示 + `progressSheets`（行・セル付き）を props で渡す
  - `linkProgressCell()`: 自己割当 ProjectJobAssignment 作成 + ProgressCell 紐付けを1トランザクションで実行

### User側 MyJob 登録フロー（進行表から）

1. User が `Show.vue` で進行表の joblink セルの「＋ 登録」をクリック
2. インラインモーダルでタイトル（行ラベルで事前入力）・期日を入力
3. `POST user/project-jobs/{job}/progress-sheets/{sheet}/link-job` → `user.project_jobs.progress_sheets.link_job`
4. サーバ側: `ProjectJobAssignment::create(sender_id=user_id)` + `ProgressCell::updateOrCreate(assignment_id=...)`
5. Inertia `back()` で `progressSheets` prop が更新される → `localSheets` を watch で同期
6. セルが「詳細」ボタンに切り替わり、クリックで `user.myjobbox.show` へ遷移

**`localSheets` の作り方（reactive proxy 回避）:**
```js
const localSheets = ref(
  (page.props.progressSheets || []).map((s) => ({
    ...s,
    cells: (s.cells || []).map((c) => ({ ...c })),
  }))
);
watch(() => page.props.progressSheets, (fresh) => {
  if (fresh) localSheets.value = fresh.map((s) => ({ ...s, cells: (s.cells || []).map((c) => ({ ...c })) }));
});
```

### シート名編集（editMode 時）

`Show.vue` でシート名を `editMode` 中に編集可能。`saveColumnConfig()` に `name` を含めて PUT する。
`column_config` が空の場合は自動的に `editMode = true` で開く。

### user型セル × joblinkセルのロック連動（2026-04-05 実装）

joblink セルに担当者が登録されると、**同グループ内の user 型セルが自動的に担当者名でロック表示**される。

**実装場所:**
- `ProgressTable.vue`: `getSiblingNodes()` + `lockedUserIdForCell(rowId, colKey, colType)` を追加
- `ProgressCell.vue`: `lockedUserId` prop + `lockedUserName` computed を追加

**ロジック:**
1. `lockedUserIdForCell()` が同グループ内の joblink セルの `assignment_user_id` を探す
2. user 型セルに `:locked-user-id` を渡す
3. `lockedUserId` がある場合 → 担当者名 + 🔒 で固定表示（セレクター非表示）
4. `lockedUserId` がない場合 → 従来通り（Coordinator: セレクター / User: テキスト）

**注意:** joblink セル内には担当者名を表示しない（user 型セルに出るため重複回避）。

---

## project_jobs テーブルの注意事項（さくら本番）⚠️

- **`schedule` カラムはさくら本番に存在しない**（ローカル開発 DB にのみ存在）
- `ProjectJobController::update()` では `Arr::pull($data, 'schedule')` を `$projectJob->update($data)` の前に必ず呼ぶこと（現在実装済み）
- このカラムをバリデーション通過後の `$data` に入れたまま `update()` するとさくら本番で `Column not found: 1054 Unknown column 'schedule'` エラーが発生する
- 同様に本番に存在しないカラムを `$data` に含めた場合は同じ対処（`Arr::pull`）が必要

---

## 依頼ジョブとマイジョブの重複排除ルール（2026-04-10 実装）

### 用語定義

| 種別 | 判定条件 | 表示場所 |
|------|----------|----------|
| **依頼ジョブ** | `project_job_assignments.sender_id ≠ user_id`（Coordinatorが依頼） | JobBox / 案件詳細 jobHistory |
| **マイジョブ** | `sender_id = user_id`（ユーザーが自己登録） | MyJobBox / 案件詳細 jobHistory |

### 重複が発生するケース

Coordinator が依頼ジョブを作成（A-req）→ ユーザーがカレンダー/進行表から同タイトルのマイジョブを自己登録（A-self）すると、案件詳細（`Coordinator/ProjectJobs/Show.vue`）の **jobHistory に両方が出る**。

### 正式実装: `supersedes_assignment_id` FK（2026-04-10 実装済み）

ユーザーがマイジョブを登録する際に「この依頼ジョブとして登録」を選択すると、マイジョブの `supersedes_assignment_id` に依頼ジョブの ID がセットされ、依頼ジョブが明示的に非表示になる。

**DBスキーマ:**
```
project_job_assignments.supersedes_assignment_id  NULLABLE UNSIGNED BIGINT
  → project_job_assignments.id 参照（自己参照FK、nullOnDelete）
```

**マイグレーション:** `2026_04_10_200001_add_supersedes_assignment_id_to_project_job_assignments.php`
- SQLite（テスト環境）ではカラムのみ追加（外部キー制約なし・ガード済み）
- さくら本番では `php artisan migrate` 必須

**モデル（`ProjectJobAssignment.php`）:**
- `$fillable` / `$casts` に `supersedes_assignment_id` 追加
- `supersedesAssignment()` — belongsTo 自己参照（マイジョブ → 依頼ジョブ）
- `supersededBy()` — hasMany 逆引き（依頼ジョブ → 置き換えたマイジョブ一覧）

**APIエンドポイント:**
- `GET /myjobbox/pending-requests?project_job_id=X` → `user.myjobbox.pending_requests`
- コントローラ: `User/MyProjectJobController::pendingRequests()`
- 自分宛の依頼ジョブで未 supersede のものを返す

**UI（フロントエンド）:**
- `Events/Create_Job.vue` と `MyJobBox/Create_user.vue` にヘッダー行「依頼ジョブとして登録」ボタン追加
- クリック → `fetchPendingRequests()` → 選択モーダル表示
- 選択すると `formAssignments[0].supersedes_assignment_id = rec.id` をセット

**コントローラでの保存:**
- `User/ProjectJobAssignmentController::store()` — `supersedes_assignment_id` をバリデーション・保存
- `User/ProjectJobController::linkProgressCell()` — 同様に追加

### ルール（重複排除）

**「`supersedes_assignment_id` で明示的に置き換えられた依頼ジョブ、またはタイトル一致の依頼ジョブを非表示にする」**

- `supersedes_assignment_id` による明示除外が優先度1（確実）
- タイトル一致 fallback が優先度2（後方互換・移行期間対応）
- 削除はしない（履歴として DB に残す）

### 非表示ロジックの実装箇所

| 画面 | 実装ファイル | 方法 |
|------|-------------|------|
| 案件詳細 jobHistory（Coordinator） | `Coordinator/ProjectJobController::show()` | PHP `array_filter` + superseded IDセット |
| ユーザー受信 JobBox | `ProjectJobs/JobBoxController::index()` | SQL `whereNotExists` サブクエリ |
| Coordinator グローバル JobBox | `ProjectJobs/JobBoxController::global()` | SQL `whereNotExists` サブクエリ |

**実装:** JobBoxController は SQL `whereNotExists` サブクエリ、Coordinator `show()` は PHP `array_filter`＋タイトル一致 fallback。詳細はコードを参照。

**注意:** `source_assignment_id`（続きジョブチェーン用）とは別カラム。混同しないこと。

---

## マイジョブ 続きジョブ連動機能（2026-04-04 実装）

### 概要

日をまたいだジョブを「続き」として連動させる機能。`project_job_assignments.source_assignment_id`（自己参照 FK）でチェーンを形成。

### DBスキーマ

```
project_job_assignments.source_assignment_id  NULLABLE UNSIGNED BIGINT
  → project_job_assignments.id 参照（自己参照FK、nullOnDelete）
```

**マイグレーション:** `2026_04_04_200001_add_source_assignment_id_to_project_job_assignments.php`
- SQLite（テスト環境）では外部キー制約なしでカラムのみ追加（ガード修正済み）
- さくら本番では `php artisan migrate` 必須

### 続きジョブの作成フロー

1. 「過去データから流用」モーダルでジョブを選択
2. 「続きとして設定」でフォームに `source_assignment_id: rec.id` をセット
3. `ProjectJobAssignmentController::store()` で `source_assignment_id` を保存

**フロントエンド:** `Events/Create_Job.vue` / `MyJobBox/Create_user.vue` に確認モーダル実装

### 完了カスケードのルール

続きジョブを完了にすると、`source_assignment_id` を辿って全祖先ジョブも自動完了（深さ最大20）。

**コントローラ:** `MyProjectJobController::completeAssignment()` 内で実装。

### チェーンAPI

`GET /myjobbox/assignments/{id}/chain` → `user.myjobbox.assignments.chain`

ルートから全子孫を BFS で収集して返す。`Show.vue` のチェーンパネルが呼び出す。

### ⚠️ sender_id の注意

`ProjectJobAssignmentController::store()` で `sender_id` をフォームから渡さない場合は認証ユーザーをデフォルトにすること（`$a['sender_id'] ?? $user->id`）。`null` になると `selfAssigned()` スコープに一致せず `completeAssignment` が 404 を返す。

### UI

- `MyJobBox/Index.vue`: `↩続き` バッジ（orange-100）
- `MyJobBox/Show.vue`: チェーンパネルで元ジョブ・続きジョブリンク＋シリーズ合計時間
- 元ジョブの `ProgressCell` は続きジョブ作成時に上書きされない

---

## ジョブ通知機能（2026-04-07 実装）

### 概要

Coordinator がジョブを割り当てたとき、ユーザーが完了したとき、進行管理表でジョブを登録・完了したときに通知を発行するシステム。

**マイグレーション:** `2026_04_07_200001_create_job_notifications_table.php`（さくらでは `php artisan migrate` 必須）

### 主要ファイル

| ファイル | 役割 |
|---|---|
| `app/Models/JobNotification.php` | Eloquent モデル |
| `app/Services/JobNotificationService.php` | 通知生成の集中ロジック（static メソッド） |
| `app/Http/Controllers/JobNotificationController.php` | index / show |
| `resources/js/Pages/JobNotifications/Index.vue` | 日別・月別・期間フィルター付き一覧 |

### 通知タイプと発火条件

| type | 発火タイミング | 送信先 |
|---|---|---|
| `new_job` | Coordinator がジョブ割り当て作成 | 受信者本人 |
| `new_job_info` | 同上 | 案件オーナー＋サブCo（自分以外） |
| `completed` | ユーザーがJobBox完了 | 依頼主（sender） |
| `completed_info` | 同上 | 案件オーナー＋サブCo（自分以外） |
| `progress_registered` | 進行表からMyJob登録 | 案件オーナー＋サブCo |
| `progress_completed` | 進行表連動ジョブ完了 | 案件オーナー＋サブCo |

**自己割当（`sender_id = user_id`）の完了は `completed` を送らない。**

### 通知クリック時の挙動（`JobNotificationController::show()`）

1. `job_notifications.read_at` を更新
2. 対応する `ProjectJobAssignment.read_at` と `status_id`（confirmed）を更新
3. `JobAssignmentMessage.read_at` も更新
4. リダイレクト先:
   - `new_job` → `user.project_jobs.jobbox.show`（JobAssignmentMessage 経由）
   - `new_job`（JAM なし） → `user.myjobbox.show`
   - `completed` → `project_jobs.jobbox.show`（Coordinator 側）
   - その他 → ロール別の `project_jobs.show`

### ナビゲーション

`AppLayout.vue` のメールアイコンリンク先を `job-notifications.index` に変更。未読件数バッジを `unreadJobNotifications`（`HandleInertiaRequests` で全ページ共有）で表示。

### 割り当て発信フロー（2026-04-07 変更）

**「保存して送信」ボタン**: `AssignmentForm.vue`（coordinator モード）から `send_immediately: true` を送信すると、`ProjectJobAssignmentsController::store()` が割り当て保存と同時に `JobAssignmentMessage` を作成・`assigned = true`・ブロードキャストまで実行する。一覧からの「発信」ボタンは既存割当の後送用として残存。

### 進行管理表アクセス制御（2026-04-07 修正）

`User/ProgressSheetController::authorizeView()` の条件:
- 案件オーナー / サブCo / **チームメンバー**（`project_job_team_members`）/ Admin / **`project_job_assignments` に割り当て済み**
- `show()` も同メソッドを使用（旧: assignment のみチェックしていた）

### ⚠️ 完了処理の注意

- `completeAssignment()` の引数型は `ProjectJobAssignment`（`ProjectJobAssignmentByMyself` にすると Coordinator 割当ジョブが 404 になる）
- 続きジョブ（`source_assignment_id` チェーン）の完了通知は祖先まで辿って `ProgressCell` の有無を確認する。`EventController` / `MyProjectJobController` / `JobBoxController` の3箇所に同一ロジックあり
- 通知時刻は UTC→JST 変換が必要（`toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' })`）

---

## アサイン編集フロー改善（2026-04-08 Claude 実装）

### アサインの新規/編集判定と送信ボタン分岐

**`AssignmentForm.vue`（coordinator モード）:**
- `isEditMode` computed: `assignments[0].id` があれば既存レコードの編集と判定
- 新規作成: 「ジョブブロックを追加」＋「保存して送信」ボタン
- 編集時: 「保存して再送信」（緑）＋「送信せず保存」（グレー）の2ボタン表示
- `save(sendImmediately: boolean)` で分岐。`router.put(assignments.update)` にフラット形式で送信
- `assignmentPayload` に `title_suffix` を追加（`edit()` コントローラから）

**`ProjectJobAssignmentsController::update()`:**
- `send_immediately: true` の場合: 既存 `JobAssignmentMessage` を全削除 → 最新内容で再作成 → broadcast
- `send_immediately: false` の場合: アサインのデータ更新のみ（送信履歴は変更しない）

### JobBox Show からアサイン編集への遷移

**`JobBox/Show.vue`:**
- 「編集」ボタンを `<Link :href="assignmentEditHref">` に変更（`canDelete` ガードを廃止）
- `assignmentEditHref` computed: `message.project_job_assignment.id` から `coordinator.project_jobs.assignments.edit` URLを生成
- 旧: `jobbox.edit`（メッセージ本文の編集）→ 新: `assignments.edit`（アサイン内容の編集）
- `isPrivilegedUser`（coordinator/leader/admin）であれば遷移可能

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
- `z_instructions/DEPLOY_SAKURA.md` - さくらデプロイ手順（詳細）

> `z_instructions/backups/` 配下のファイルは読み飛ばす。
